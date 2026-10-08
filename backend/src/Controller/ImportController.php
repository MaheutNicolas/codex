<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Service\ImportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Saves a document of knowledge entries, events and participant links in one go (all or nothing). */
final class ImportController
{
    public function __construct(
        private readonly ImportService $service,
        private readonly ApiHelper $api,
    ) {
    }

    #[Route('/api/books/{bookId}/import', requirements: ['bookId' => '\d+'], methods: ['POST'])]
    public function __invoke(Book $book, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->import($book, $this->api->body($request)));
    }
}
