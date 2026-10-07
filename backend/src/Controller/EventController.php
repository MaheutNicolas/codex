<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Service\EventService;
use App\Validation\Validate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/books/{bookId}/events', requirements: ['bookId' => '\d+'])]
final class EventController
{
    public function __construct(
        private readonly EventService $service,
        private readonly ApiHelper $api,
        private readonly Validate $validate,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Book $book, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->list(
            $book,
            $this->validate->intQuery($request, 'chapter', 1, 2147483647),
            $this->validate->boolQuery($request, 'revealed'),
            $this->validate->page($request),
        ));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Book $book, string $id): JsonResponse
    {
        return ApiHelper::json($this->service->get($book, $id));
    }

    #[Route('', methods: ['POST'])]
    public function create(Book $book, Request $request): JsonResponse
    {
        $event = $this->service->create($book, $this->api->body($request));

        return ApiHelper::json(
            $event,
            201,
            ['Location' => \sprintf('/api/books/%d/events/%s', $book->getId(), $event['id'])],
        );
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Book $book, string $id, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->update($book, $id, $this->api->body($request)));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Book $book, string $id): Response
    {
        $this->service->delete($book, $id);

        return new Response(null, 204);
    }
}
