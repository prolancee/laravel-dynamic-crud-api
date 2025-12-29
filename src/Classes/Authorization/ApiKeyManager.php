<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\Classes\Authorization;

use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor\AuthorizerResponder;
use Illuminate\Database\QueryException as LaravelQueryException;
use PROLANCEE\Support\Exceptions\QueryException;
use Illuminate\Support\Facades\DB;
use Exception;
use JsonException;

final class ApiKeyManager
{
    /**
     * Generate a secure AES-encrypted cross-site access token with HMAC integrity.
     *
     * @param string|null $recircle_key Optional user-defined recycle key.
     * @param string|null $api_key      Client-provided API key.
     * @param string|null $secret_key   Client-provided secret key.
     * @param string|null $clientId     Optional client ID for dynamic credentials.
     * @return string|array             Returns encrypted token or error array.
     */
    public static function generateAccessToken(
        string $recircle_key = null,
        string $api_key = null,
        string $secret_key = null,
        string $clientId = null
    ): string|array {
        try {
            $admotum = config('prolancee.dynamic.crud.api.admotum', []);

            // Fetch credentials: dynamic if clientId is provided, otherwise use static
            if ($clientId) {
                $credentials = self::dynamic_credentials($admotum, $clientId);
                if (isset($credentials['query_exception'])) return $credentials;
                $apiKey = $credentials['api_key'] ?? '';
                $secretKey = $credentials['secret_key'] ?? '';
            } else {
                $apiKey = $admotum['static_credentials']['api_key'] ?? '';
                $secretKey = $admotum['static_credentials']['secret_key'] ?? '';
            }

            // Return error if credentials are missing
            if (!$apiKey || !$secretKey) {
                return AuthorizerResponder::missingApiCredentials();
            }

            // Validate provided API key and secret key
            $expectedKey = $apiKey . $secretKey;
            $providedKey = ($api_key ?? '') . ($secret_key ?? '');
            if (!hash_equals($expectedKey, $providedKey)) {
                return AuthorizerResponder::invalidApiCredentials();
            }

            // Get root domain for token binding
            $domain = parse_url(request()->root(), PHP_URL_HOST);
            $rootDomain = implode('.', array_slice(explode('.', $domain), -2));

            // Build token payload and signature
            $expectedString = $expectedKey . ($recircle_key ?? 'cross-site') . 'secure';
            $expiresAt = time() + 3600; // Token valid for 1 hour
            $sigBase = $expiresAt . '|' . $expectedString . '|' . $rootDomain;
            $signature = hash_hmac('sha256', $sigBase, $expectedKey);

            $payload = json_encode([
                'expires_at' => $expiresAt,
                'check'      => $expectedString,
                'domain'     => $rootDomain,
                'client_id'  => $clientId,
                'sig'        => $signature,
            ], JSON_THROW_ON_ERROR);

            // Encrypt payload using AES-128-CBC with random IV
            $encryptionKey = substr(hash('sha256', $expectedKey, true), 0, 16);
            $iv = random_bytes(openssl_cipher_iv_length('aes-128-cbc'));
            $encrypted = openssl_encrypt($payload, 'aes-128-cbc', $encryptionKey, OPENSSL_RAW_DATA, $iv);

            if ($encrypted === false) {
                return AuthorizerResponder::internalServerError('Encryption failed');
            }

            // Encode token: base64 + rot13 for additional obfuscation
            return str_rot13(base64_encode($iv . $encrypted));

        } catch (Exception $e) {
            return AuthorizerResponder::internalServerError($e->getMessage());
        }
    }

    /**
     * Verify an AES-encrypted cross-site access token.
     *
     * @param string|null $recircle_key Optional recycle key.
     * @param string|null $accessToken  Token string from client.
     * @param string|null $clientId     Optional client ID for dynamic credentials.
     * @return bool|array               True if valid, otherwise error array.
     */
    public static function checkAccessToken(
        string $recircle_key = null,
        string $accessToken = null,
        string $clientId = null
    ): bool|array {
        try {
            if (empty($accessToken)) return AuthorizerResponder::invalidTokenFormat();

            $admotum = config('prolancee.dynamic.crud.api.admotum', []);

            // Fetch credentials dynamically or use static
            if ($clientId) {
                $credentials = self::dynamic_credentials($admotum, $clientId);
                if (isset($credentials['query_exception'])) return $credentials;
            } else {
                $credentials = $admotum['static_credentials'] ?? [];
            }

            $apiKey = $credentials['api_key'] ?? '';
            $secretKey = $credentials['secret_key'] ?? '';
            if (!$apiKey || !$secretKey) return AuthorizerResponder::invalidAccessToken();

            $expectedKey = $apiKey . $secretKey;
            $expectedString = $expectedKey . ($recircle_key ?? 'cross-site') . 'secure';

            // Decode token (rot13 + base64)
            $decoded = base64_decode(str_rot13($accessToken), true);
            if ($decoded === false) return AuthorizerResponder::invalidTokenFormat();

            // Extract IV and encrypted payload
            $ivLength = openssl_cipher_iv_length('aes-128-cbc');
            if (strlen($decoded) <= $ivLength) return AuthorizerResponder::invalidTokenFormat();
            $iv = substr($decoded, 0, $ivLength);
            $encrypted = substr($decoded, $ivLength);

            // Decrypt payload
            $encryptionKey = substr(hash('sha256', $expectedKey, true), 0, 16);
            $decrypted = openssl_decrypt($encrypted, 'aes-128-cbc', $encryptionKey, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) return AuthorizerResponder::invalidAccessToken();

            // Decode JSON safely
            try {
                $data = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                return AuthorizerResponder::invalidTokenFormat();
            }

            // Validate required fields in payload
            if (!isset($data['expires_at'], $data['check'], $data['domain'], $data['sig'])) {
                return AuthorizerResponder::tokenPayloadIsMissing();
            }

            // Check token expiration
            if (!ctype_digit((string)$data['expires_at']) || time() > (int)$data['expires_at']) {
                return AuthorizerResponder::accessTokenExpired();
            }

            // Check domain binding
            $domain = parse_url(request()->root(), PHP_URL_HOST);
            $rootDomain = implode('.', array_slice(explode('.', $domain), -2));
            if (!hash_equals($expectedString, (string)$data['check']) ||
                !hash_equals($rootDomain, (string)$data['domain'])) {
                return AuthorizerResponder::invalidAccessToken();
            }

            // Verify HMAC signature
            $sigBase = $data['expires_at'] . '|' . $data['check'] . '|' . $data['domain'];
            $expectedSig = hash_hmac('sha256', $sigBase, $expectedKey);
            if (!is_string($data['sig']) || !hash_equals($expectedSig, $data['sig'])) {
                return AuthorizerResponder::invalidAccessToken();
            }

            return true;

        } catch (Exception $e) {
            return AuthorizerResponder::internalServerError('Token verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch dynamic credentials for a given client from database.
     *
     * @param array $admotum Config array for admotum API.
     * @param string|null $clientId Client ID to fetch credentials.
     * @return array Array with 'api_key' and 'secret_key', or error info.
     */
    private static function dynamic_credentials(array $admotum, ?string $clientId = null): array
    {
        try {
            $table = $admotum['dynamic_credentials']['table'] ?? '';
            $columns = $admotum['dynamic_credentials']['columns'] ?? [];

            if (!$table || empty($columns) || !$clientId) {
                return ['api_key' => '', 'secret_key' => ''];
            }

            $client = DB::table($table)
                ->select($columns['api_key'], $columns['secret_key'])
                ->where($columns['client_id'], $clientId)
                ->where($columns['status'], 1)
                ->first();

            if (!$client) return ['api_key' => '', 'secret_key' => ''];

            return [
                'api_key' => (string)($client->{$columns['api_key']} ?? ''),
                'secret_key' => (string)($client->{$columns['secret_key']} ?? ''),
            ];

        } catch (LaravelQueryException $e) {
            return ['query_exception' => QueryException::queryException($e)];
        }
    }
}