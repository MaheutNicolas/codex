<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Entity\User;
use App\Security\SessionOnly;
use App\Service\BookService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Managing books needs a logged-in session. Reading the book of an API key is the only thing a key can do here. */
#[Route('/api/books')]
final class BookController
{
    public function __construct(
        private readonly BookService $service,
        private readonly ApiHelper $api,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[SessionOnly]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return ApiHelper::json($this->service->list($user));
    }

    #[Route('/{bookId}', requirements: ['bookId' => '\d+'], methods: ['GET'])]
    public function show(Book $book): JsonResponse
    {
        return ApiHelper::json($this->service->get($book));
    }

    #[Route('', methods: ['POST'])]
    #[SessionOnly]
    public function create(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $book = $this->service->create($user, $this->api->body($request));

        return ApiHelper::json($book, 201, ['Location' => \sprintf('/api/books/%d', $book['id'])]);
    }

    #[Route('/{bookId}', requirements: ['bookId' => '\d+'], methods: ['PATCH'])]
    #[SessionOnly]
    public function update(Book $book, Request $request): JsonResponse
    {
        return ApiHelper::json($this->service->update($book, $this->api->body($request)));
    }

    #[Route('/{bookId}', requirements: ['bookId' => '\d+'], methods: ['DELETE'])]
    #[SessionOnly]
    public function delete(Book $book): Response
    {
        $this->service->delete($book);

        return new Response(null, 204);
    }
}
