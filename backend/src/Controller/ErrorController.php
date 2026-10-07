<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Error\ErrorCode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Public documentation of every error code, generated from the ErrorCode enum. */
final class ErrorController
{
    #[Route('/api/errors', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $errors = array_map(static fn (ErrorCode $code) => [
            'code' => $code->value,
            'status' => $code->status(),
            'message' => $code->message(),
            'description' => $code->description(),
            'solution' => $code->solution(),
        ], ErrorCode::cases());

        return ApiHelper::json(['data' => $errors]);
    }
}
