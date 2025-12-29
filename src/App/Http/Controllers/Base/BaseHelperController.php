<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\App\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Authorization\ApiKeyManager;
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor\ServiceResponder;
use PROLANCEE\DYNAMIC\CRUD\Api\Exceptions\Handler\ApiException;
use RuntimeException;
use Exception;

class BaseHelperController
{
    // -------------------------------
    // Handle standardizedResponse DRY
    // -------------------------------
    protected function standardizedResponse($result, string $operation, array $option = []): JsonResponse
    {
        // ---------------------------------
        // Generate access token response
        // ---------------------------------
        if ($result && $operation === 'generate') {
            try {
                if(is_string($result)) {
                    return ServiceResponder::response([
                        'res'     => 'success',
                        'code'    => 200,
                        'message' => "{$operation}-success",
                        'option'  => array_merge($option, [
                            'access_token' => $result,
                            'expires_in'   => '1 hour',
                            'token_type'   => 'Api Key',
                            'operation'    => $operation
                        ])
                    ]);
                }

                if (is_array($result)) {
                    foreach (['query_exception', 'redis_exception'] as $ex) {
                        if (isset($result[$ex])) {
                            throw new ApiException($result[$ex]);
                        }
                    }

                    if (isset($result['option']['error']) && $result['option']['error'] === 'token_exception') {
                        throw new ApiException($result);
                    }
                } 
            } catch (ApiException $e) {
                return $e->toJsonResponse($e);
            }
        }

        // -------------------------------
        // File upload response
        // -------------------------------
        if ($result && $operation === 'file-upload') {
            $successful = array_filter($result, fn($u) => $u['status'] ?? false);
            $failed     = array_filter($result, fn($u) => !$u['status']);
            $code       = $result[0]['code'] ?? 500;

            $publicUrls = array_map(fn($u) => isset($u['stored_path']) ? asset("storage/{$u['stored_path']}") : null, $successful);

            $code    = count($successful) ? 200 : $code;
            $message = count($successful) ? "{$operation}-success" : "{$operation}-error";

            $responseOption = [
                'upload'  => [
                    'stored'     => $successful,
                    'public_url' => $publicUrls,
                    'errors'     => $failed,  
                ],
                'operation'      => $operation
            ];

            return ServiceResponder::response([
                'res'     => count($successful) ? 'success' : 'error',
                'code'    => $code,
                'message' => $message,
                'option'  => array_merge($option, $responseOption)
            ]);
        }

        // -------------------------------
        // File upload Delete response
        // -------------------------------
        if ($result && ($operation === 'file-delete')) {
            return ServiceResponder::response([
                'res'     => 'success',
                'code'    => 200,
                'message' => "{$operation}-success",
                'option'  => array_merge($option, [
                    'file_path' => $result,
                    'operation'   => $operation
                ])
            ]);
        }

        // -------------------------------
        // Default error response
        // -------------------------------
        return ServiceResponder::response([
            'res'     => 'error',
            'code'    => 500,
            'message' => "{$operation}-error",
            'option'  => array_merge($option, [
                'operation' => $operation
            ])
        ]);
    }

    // -------------------------------
    // Handle queryResponse DRY
    // -------------------------------
    protected function queryResponse($result, string $operation): JsonResponse
    {
        $result = ServiceResponder::validateQuery($result);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        // --------------------------------------------
        // Warn response (all false)
        // --------------------------------------------
        if (
            $result === false ||
            (is_array($result) && !empty($result) && count(array_filter($result)) === 0)
        ) {
            return ServiceResponder::response([
                'res'     => 'warn',
                'code'    => 206,
                'message' => "{$operation}-warn",
                'option'  => [
                    'operation' => $operation,
                ],
            ]);
        }

        // --------------------------------------------
        // Normal response
        // --------------------------------------------
        if (
            (is_array($result) && !empty($result)) ||
            (is_object($result) && !empty((array) $result))
        ) {
            $resultArray = (array) $result;

            $data         = $resultArray['data'] ?? null;
            $auth         = $resultArray['auth'] ?? null;
            $notified     = $resultArray['notified'] ?? null;

            $hasData = !empty($data);

            $code = match (true) {
                $hasData && $operation === 'store' => 201,
                $hasData                           => 200,
                in_array($operation, ['login', 'changePassword']) => 401,
                default                            => 404,
            };

            $responseData = [
                'res'     => $hasData ? 'success' : 'error',
                'code'    => $code,
                'message' => $hasData
                    ? "{$operation}-success"
                    : "{$operation}-error",
                'option'  => [
                    'operation' => $operation,
                ],
            ];

            if ($hasData) {
                $responseData['option']['data'] = $data;
            } else {
                $responseData['option']['auth'] = $auth;
            }

            if (!empty($notified)) {
                $responseData['option']['notified'] = $notified;
            }

            return ServiceResponder::response($responseData);
        }

        // --------------------------------------------
        // Fallback error
        // --------------------------------------------
        return ServiceResponder::response([
            'res'     => 'error',
            'code'    => 500,
            'message' => "{$operation}-error",
            'option'  => [
                'operation' => $operation,
            ],
        ]);
    }

    protected function generateAccessToken(
        string $reCycle_key, 
        string $apiKey, 
        string $secretKey, 
        string $clientId
    ): array | string {
        try {
            return ApiKeyManager::generateAccessToken(
                $reCycle_key, 
                $apiKey, 
                $secretKey, 
                $clientId
            );
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }
}