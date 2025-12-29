<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\Exceptions\Handler;

use PROLANCEE\DYNAMIC\CRUD\Api\Exceptions\Handler\HelperException;
use Throwable;

class ApiException extends HelperException
{
    /**
     * Constructor.
     *
     * @param array $payload Custom exception data (message, code, extra)
     * @param int $code HTTP status code (default 500)
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(array $payload = [], int $code = 500, ?Throwable $previous = null)
    {
        parent::__construct($payload, $code, $previous);
    }
}
