<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Entity\User;
use App\Security\SessionOnly;
use App\Service\ApiKeyService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Reading or regenerating the key of a book is reserved to a logged-in session: a key can never reveal or replace itself. */
#[SessionOnly]
#[Route('/api/books/{bookId}/api-key', requirements: ['bookId' => '\d+'])]
final class ApiKeyController
{
    public function __construct(private readonly ApiKeyService $service)
    {
    }

    /** The key of the book, with its token. It is created the first time it is asked for. */
    #[Route('', methods: ['GET'])]
    public function show(Book $book, #[CurrentUser] User $user): JsonResponse
    {
        return ApiHelper::json($this->service->get($user, $book));
    }

    /** Gives the key a new token; the previous one stops working immediately. */
    #[Route('/regenerate', methods: ['POST'])]
    public function regenerate(Book $book, #[CurrentUser] User $user): JsonResponse
    {
        return ApiHelper::json($this->service->regenerate($user, $book));
    }
}
