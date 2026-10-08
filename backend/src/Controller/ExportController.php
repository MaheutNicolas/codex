<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Service\ExportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** The whole content of a book in one call: the same document the import accepts. */
final class ExportController
{
    public function __construct(private readonly ExportService $service)
    {
    }

    #[Route('/api/books/{bookId}/export', requirements: ['bookId' => '\d+'], methods: ['GET'])]
    public function __invoke(Book $book): JsonResponse
    {
        return ApiHelper::json($this->service->export($book));
    }
}
