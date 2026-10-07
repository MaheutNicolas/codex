<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\Book;
use App\Entity\Knowledge;
use App\Service\KnowledgeService;
use App\Validation\Validate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Lightweight lexicon of every knowledge entry of a book (id, name, type, aliases), meant to be loaded once by the AI. */
final class IndexController
{
    public function __construct(
        private readonly KnowledgeService $service,
        private readonly Validate $validate,
    ) {
    }

    #[Route('/api/books/{bookId}/index', requirements: ['bookId' => '\d+'], methods: ['GET'])]
    public function __invoke(Book $book, Request $request): JsonResponse
    {
        $type = $this->validate->choiceQuery($request, 'type', Knowledge::TYPES);
        $data = $this->service->lexicon($book, $type);

        // HTTP caching: the client revalidates with If-None-Match and gets a 304 when nothing changed.
        $response = ApiHelper::json(['data' => $data, 'total' => \count($data)]);
        $response->setEtag(md5((string) $response->getContent()));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified($request);

        return $response;
    }
}
