<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Entity\User;
use App\Security\SessionOnly;
use App\Service\ApiKeyService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Managing API keys is reserved to a logged-in session: a key can never create or delete keys. */
#[SessionOnly]
final class ApiKeyController
{
    public function __construct(
        private readonly ApiKeyService $service,
        private readonly ApiHelper $api,
    ) {
    }

    #[Route('/api/books/{bookId}/api-keys', requirements: ['bookId' => '\d+'], methods: ['GET'])]
    public function index(Book $book): JsonResponse
    {
        return ApiHelper::json($this->service->list($book));
    }

    /** The response carries the token: it is shown this once and cannot be retrieved afterwards. */
    #[Route('/api/books/{bookId}/api-keys', requirements: ['bookId' => '\d+'], methods: ['POST'])]
    public function create(Book $book, #[CurrentUser] User $user, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->create($user, $book, $this->api->body($request)), 201);
    }

    #[Route('/api/api-keys/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] User $user): Response
    {
        $this->service->delete($user, $id);

        return new Response(null, 204);
    }
}
