<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Service\TimelineService;
use App\Validation\Validate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** The timeline as the AI reads it: filtered by the point of view of the reader, with participants. */
final class TimelineController
{
    public function __construct(
        private readonly TimelineService $service,
        private readonly Validate $validate,
    ) {
    }

    #[Route('/api/books/{bookId}/timeline', requirements: ['bookId' => '\d+'], methods: ['GET'])]
    public function __invoke(Book $book, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->timeline(
            $book,
            $this->validate->viewpoint($request),
            $this->validate->stringQuery($request, 'knowledgeId'),
            $this->validate->page($request),
        ));
    }
}
