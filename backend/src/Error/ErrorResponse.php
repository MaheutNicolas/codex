<?php

namespace App\Error;

use App\Api\ApiHelper;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Builds the JSON error response, so every part of the application answers errors the same way. */
final class ErrorResponse
{
    /**
     * @param array<string, mixed>  $details extra context ("fields", "parameter"...), omitted when empty
     * @param array<string, string> $headers
     */
    public static function create(ErrorCode $code, ?string $message = null, array $details = [], array $headers = []): JsonResponse
    {
        $error = ['code' => $code->value, 'message' => $message ?? $code->message()];
        if ([] !== $details) {
            $error['details'] = $details;
        }

        return ApiHelper::json(['error' => $error], $code->status(), $headers);
    }
}
