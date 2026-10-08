<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Service\SearchService;
use App\Validation\Validate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Full-text search over the entries and the events a reader may see. */
final class SearchController
{
    private const MAX_LIMIT = 50;

    public function __construct(
        private readonly SearchService $service,
        private readonly Validate $validate,
    ) {
    }

    #[Route('/api/books/{bookId}/search', requirements: ['bookId' => '\d+'], methods: ['GET'])]
    public function __invoke(Book $book, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->search(
            $book,
            $this->validate->viewpoint($request),
            $this->validate->searchTerms($request, 'q'),
            $this->validate->intQuery($request, 'limit', 1, self::MAX_LIMIT) ?? 20,
        ));
    }
}
