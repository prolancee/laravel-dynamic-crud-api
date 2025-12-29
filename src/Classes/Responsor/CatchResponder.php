<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor;

use Illuminate\Http\JsonResponse;
use PROLANCEE\DYNAMIC\CRUD\Api\Classes\Responsor\HelperResponder;
use PROLANCEE\DYNAMIC\CRUD\Api\Exceptions\Handler\ApiException;

class CatchArrayFormat extends HelperResponder 
{
    /**
     * Formats a runtime exception response.
     *
     * @param string $message
     * @return array
     */
    public static function runtimeException(string $message): array
    {
        return self::formatResponse(
            false,
            422,
            $message,
            [
                'error' => 'runtime_exception',
                'hint'  => 'Review your request data and ensure all required parameters are provided correctly.'
            ]
        );
    }

    /**
     * Formats a throwable exception response.
     *
     * @param string $message
     * @return array
     */
    public static function throwableException(string $message): array
    {
        return self::formatResponse(
            false,
            500,
            $message,
            [
                'error' => 'throw_exception',
                'hint'  => 'Unexpected system error occurred. Please check your input and try again.'
            ]
        );
    }

    /**
     * Formats a generic exception response.
     *
     * @param string $message
     * @return array
     */
    public static function genericException(string $message): array
    {
        return self::formatResponse(
            false,
            500,
            $message,
            [
                'error' => 'exception',
                'hint'  => 'An unexpected error occurred while processing your request.'
            ]
        );
    }
}

class CatchResponder extends CatchArrayFormat
{
    /**
     * Handles runtime exceptions and returns a JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function runtime(string $message): JsonResponse
    {
        try {
            throw new ApiException(self::runtimeException($message));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }

    /**
     * Handles throwable exceptions and returns a JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function throwable(string $message): JsonResponse
    {
        try {
            throw new ApiException(self::throwableException($message));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }

    /**
     * Handles generic exceptions and returns a JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function generic(string $message): JsonResponse
    {
        try {
            throw new ApiException(self::genericException($message));
        } catch (ApiException $e) {
            return $e->toJsonResponse($e);
        }
    }
}