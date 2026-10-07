<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Service\EventParticipantService;
use App\Validation\Validate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Links are addressed by the slugs of their event and knowledge entry. */
#[Route('/api/books/{bookId}/event-participants', requirements: ['bookId' => '\d+'])]
final class EventParticipantController
{
    public function __construct(
        private readonly EventParticipantService $service,
        private readonly ApiHelper $api,
        private readonly Validate $validate,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Book $book, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->list(
            $book,
            $this->validate->stringQuery($request, 'eventId'),
            $this->validate->stringQuery($request, 'knowledgeId'),
            $this->validate->stringQuery($request, 'role'),
            $this->validate->page($request),
        ));
    }

    #[Route('/{eventId}/{knowledgeId}', methods: ['GET'])]
    public function show(Book $book, string $eventId, string $knowledgeId): JsonResponse
    {
        return ApiHelper::json($this->service->get($book, $eventId, $knowledgeId));
    }

    #[Route('', methods: ['POST'])]
    public function create(Book $book, Request $request): JsonResponse
    {
        $participant = $this->service->create($book, $this->api->body($request));

        return ApiHelper::json(
            $participant,
            201,
            ['Location' => \sprintf('/api/books/%d/event-participants/%s/%s', $book->getId(), $participant['eventId'], $participant['knowledgeId'])],
        );
    }

    #[Route('/{eventId}/{knowledgeId}', methods: ['PATCH'])]
    public function update(Book $book, string $eventId, string $knowledgeId, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->update($book, $eventId, $knowledgeId, $this->api->body($request)));
    }

    #[Route('/{eventId}/{knowledgeId}', methods: ['DELETE'])]
    public function delete(Book $book, string $eventId, string $knowledgeId): Response
    {
        $this->service->delete($book, $eventId, $knowledgeId);

        return new Response(null, 204);
    }
}
