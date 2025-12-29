<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor;

use Illuminate\Http\JsonResponse;
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor\HelperResponder;
use PROLANCEE\DYNAMIC\CRUD\Api\Exceptions\Handler\ApiException;
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Authorization\ApiKeyManager;

class AuthorizerArrayFormat extends HelperResponder
{
    /**
     * Returns an error response when the token format is invalid.
     *
     * @return array
     */
    public static function invalidTokenFormat(): array
    {
        return self::formatResponse(
            false,
            400,
            'Bad Request. The token format is invalid. Ensure it contains both IV and encrypted data.',
            [
                'error' => 'token_exception',
                'hint'  => 'Check that the token includes both initialization vector (IV) and encrypted data.'
            ]
        );
    }

    /**
     * Returns an error response when the access token is invalid.
     *
     * @return array
     */
    public static function invalidAccessToken(): array
    {
        return self::formatResponse(
            false,
            401,
            'Unauthorized. The provided access token is invalid. Please verify your credentials.',
            [
                'error' => 'token_exception',
                'hint'  => 'Ensure the access token is correct and has not been tampered with.'
            ]
        );
    }

    /**
     * Returns an error response when the access token has expired.
     *
     * @return array
     */
    public static function accessTokenExpired(): array
    {
        return self::formatResponse(
            false,
            403,
            'Forbidden. The access token has expired. Please generate a new token.',
            [
                'error' => 'token_exception',
                'hint'  => 'Generate a new access token before making requests.'
            ]
        );
    }

    /**
     * Returns an error response when API credentials are missing.
     *
     * @return array
     */
    public static function missingApiCredentials(): array
    {
        return self::formatResponse(
            false,
            401,
            'Unauthorized. API key or secret key is missing. Provide valid credentials to access the API.',
            [
                'error' => 'token_exception',
                'hint'  => 'Check that both API key and secret key are provided in the request.'
            ]
        );
    }

    /**
     * Returns an error response when API credentials are invalid.
     *
     * @return array
     */
    public static function invalidApiCredentials(): array
    {
        return self::formatResponse(
            false,
            401,
            'Unauthorized. The provided API key or secret key is invalid. Check your credentials.',
            [
                'error' => 'token_exception',
                'hint'  => 'Verify the API key and secret key against your credentials.'
            ]
        );
    }

    /**
     * Returns an error response when required token payload is missing.
     *
     * @return array
     */
    public static function tokenPayloadIsMissing(): array
    {
        return self::formatResponse(
            false,
            403,
            'Forbidden. Required token payload is missing or incomplete.',
            [
                'error' => 'token_exception',
                'hint'  => 'Ensure all required fields are included in the token payload.'
            ]
        );
    }

    /**
     * Returns an error response when the token timestamp is invalid.
     *
     * @return array
     */
    public static function accessTokenTimestamp(): array
    {
        return self::formatResponse(
            false,
            403,
            'Forbidden. The token timestamp is invalid. Please generate a new token.',
            [
                'error' => 'token_exception',
                'hint'  => 'Check the token timestamp and regenerate the token if necessary.'
            ]
        );
    }

    /**
     * Returns a generic internal server error response.
     *
     * @param string $message The error message to return.
     * @return array
     */
    protected static function internalServerError(string $message): array
    {
        return self::formatResponse(
            false,
            500,
            "Internal Server Error. {$message}",
            [
                'error' => 'token_exception',
                'hint'  => 'Internal server error occurred while processing the token.'
            ]
        );
    }
}

class AuthorizerResponder extends AuthorizerArrayFormat
{
    /*************************************************** ADMOTUM ************************************************************************************/

    /**
     * Returns response when API key and secret key are missing.
     *
     * @return JsonResponse
     */
    public static function requiredApiCredentials(): JsonResponse
    {
        try {
            throw new ApiException(self::formatResponse(
                false,
                401,
                'Unauthorized. API key and secret key are required for authentication.',
                [
                    'error' => 'token_exception',
                    'hint'  => 'Provide both API key and secret key in request headers or body.'
                ]
            ));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }

    /**
     * Returns response when "Recycle-Key" header is missing.
     *
     * @return JsonResponse
     */
    public static function missingReCycleKeyHeader(): JsonResponse
    {
        try {
            throw new ApiException(self::formatResponse(
                false,
                401,
                'Unauthorized. The "Recycle-Key" header must be provided for token verification.',
                [
                    'error' => 'token_exception',
                    'hint'  => 'Include a valid "Recycle-Key" header when making the request.'
                ]
            ));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }

    /**
     * Returns response when "Access-Token" header is missing.
     *
     * @return JsonResponse
     */
    public static function missingAccessTokenHeader(): JsonResponse
    {
        try {
            throw new ApiException(self::formatResponse(
                false,
                401,
                'Unauthorized. The "Access-Token" header must be included in the request.',
                [
                    'error' => 'token_exception',
                    'hint'  => 'Attach the "Access-Token" header with a valid token.'
                ]
            ));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }

    /**
     * Verifies an Admotum access token against a recycle key and client ID.
     *
     * @param string $reCycleKey
     * @param string $accessToken
     * @param string $clientId
     * @return JsonResponse|bool
     */
    public static function verifyAccessTokenByAdmotum(string $reCycleKey, string $accessToken, string $clientId): string|JsonResponse
    {
        try {
            $verified = ApiKeyManager::checkAccessToken($reCycleKey, $accessToken, $clientId);
            
            if (is_array($verified)) {
                foreach (['query_exception', 'redis_exception'] as $ex) {
                    if (isset($verified[$ex])) {
                        throw new ApiException($verified[$ex]);
                    }
                }

                if (isset($verified['option']['error']) && $verified['option']['error'] === 'token_exception') {
                    throw new ApiException($verified);
                }
            } 
            
            return $accessToken;
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }

    /*************************************************** SANCTUM ************************************************************************************/

    /**
     * Returns response when Sanctum authentication fails.
     *
     * @return JsonResponse
     */
    public static function sanctumAuthentication(): JsonResponse
    {
        try {
            throw new ApiException(self::formatResponse(
                false,
                401,
                'Unauthorized. Invalid or expired Bearer token. Please login again.',
                [
                    'error' => 'token_exception',
                    'hint'  => 'Re-authenticate with a valid Bearer token before retrying.'
                ]
            ));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }

    /**
     * Returns response when Bearer token is missing.
     *
     * @return JsonResponse
     */
    public static function bearerTokenMissing(): JsonResponse
    {
        try {
            throw new ApiException(self::formatResponse(
                false,
                401,
                'Unauthorized. Bearer token is missing from the request.',
                [
                    'error' => 'token_exception',
                    'hint'  => 'Include Authorization: Bearer <token> in the request headers.'
                ]
            ));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }
}