<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Api\Page;
use App\Entity\Book;
use App\Entity\Knowledge;
use App\Service\KnowledgeService;
use App\Service\RelatedService;
use App\Service\RelationStateService;
use App\Validation\Validate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/books/{bookId}/knowledge', requirements: ['bookId' => '\d+'])]
final class KnowledgeController
{
    public function __construct(
        private readonly KnowledgeService $service,
        private readonly RelatedService $related,
        private readonly RelationStateService $relationStates,
        private readonly ApiHelper $api,
        private readonly Validate $validate,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Book $book, Request $request): JsonResponse
    {
        $type = $this->validate->choiceQuery($request, 'type', Knowledge::TYPES);

        return ApiHelper::json($this->service->list($book, $type, $this->validate->page($request)));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Book $book, string $id, Request $request): JsonResponse
    {
        // "?events=true" adds the events of the entry, filtered by the point of view of the reader.
        if ($this->validate->boolQuery($request, 'events')) {
            return ApiHelper::json($this->service->sheet(
                $book,
                $id,
                $this->validate->viewpoint($request),
                $this->validate->page($request),
            ));
        }

        return ApiHelper::json($this->service->get($book, $id));
    }

    /** The entries linked to this one (for now: those that share events with it), seen from a point of view. */
    #[Route('/{id}/related', methods: ['GET'])]
    public function related(Book $book, string $id, Request $request): JsonResponse
    {
        return ApiHelper::json($this->related->related(
            $book,
            $id,
            $this->validate->viewpoint($request),
            $this->validate->intQuery($request, 'limit', 1, 50) ?? 20,
        ));
    }

    /**
     * The relations of this entry as they stand from a point of view: for each other entry, the state that holds at
     * the chapter reached. "?withHistory=true" adds every visible state of each pair (and keeps the ended ones).
     */
    #[Route('/{id}/relations', methods: ['GET'])]
    public function relations(Book $book, string $id, Request $request): JsonResponse
    {
        return ApiHelper::json($this->relationStates->page(
            $book,
            $id,
            $this->validate->viewpoint($request),
            $this->validate->boolQuery($request, 'withHistory') ?? false,
            new Page($this->validate->intQuery($request, 'limit', 1, 200) ?? 30, $this->validate->intQuery($request, 'offset', 0) ?? 0),
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(Book $book, Request $request): JsonResponse
    {
        $knowledge = $this->service->create($book, $this->api->body($request));

        return ApiHelper::json(
            $knowledge,
            201,
            ['Location' => \sprintf('/api/books/%d/knowledge/%s', $book->getId(), $knowledge['id'])],
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
