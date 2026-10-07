<?php

namespace App\Error;

class ApiException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $details extra context exposed in the response ("fields", "parameter"...)
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly array $details = [],
        ?string $message = null,
    ) {
        parent::__construct($message ?? $errorCode->message());
    }
}
