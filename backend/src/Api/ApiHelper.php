<?php

namespace App\Api;

use App\Error\ApiException;
use App\Error\ErrorCode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** The HTTP side of the controllers: reading a JSON body and writing a JSON response. */
final class ApiHelper
{
    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return JsonResponse::fromJsonString($json, $status, $headers);
    }

    /** @return array<string, mixed> */
    public function body(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(ErrorCode::INVALID_JSON);
        }

        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw new ApiException(ErrorCode::INVALID_JSON);
        }

        return $data;
    }
}
