<?php

namespace App\Service;

use App\Api\Page;
use App\Api\Viewpoint;
use App\Entity\Book;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\KnowledgeRepository;
use App\Repository\RelationRepository;

/**
 * The relations of an entry as they stand from a point of view: for each other entry, the state that holds at the
 * chapter the reader has reached (see RelationStates), and on request the whole history of the pair.
 */
final class RelationStateService
{
    public function __construct(
        private readonly RelationRepository $relations,
        private readonly KnowledgeRepository $knowledge,
    ) {
    }

    /**
     * Every relation that holds from this point of view, most recently changed first.
     *
     * @param bool $includeEnded keep the pairs that are no longer linked (marked "ended")
     * @param bool $withHistory  add the visible states of each pair, oldest first
     *
     * @return list<array<string, mixed>>
     */
    public function current(Book $book, string $slug, Viewpoint $viewpoint, bool $includeEnded = false, bool $withHistory = false): array
    {
        if (null === $this->knowledge->findBySlug($book, $slug)) {
            throw new ApiException(ErrorCode::KNOWLEDGE_NOT_FOUND, ['id' => $slug]);
        }

        return RelationStates::resolve($this->relations->involving($book, $slug), $slug, $viewpoint, $includeEnded, $withHistory);
    }

    /**
     * A page of the relations of an entry. With history, the pairs that are no longer linked are listed too.
     *
     * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function page(Book $book, string $slug, Viewpoint $viewpoint, bool $withHistory, Page $page): array
    {
        $all = $this->current($book, $slug, $viewpoint, $withHistory, $withHistory);

        return [
            'data' => \array_slice($all, $page->offset, $page->limit),
            'total' => \count($all),
            'limit' => $page->limit,
            'offset' => $page->offset,
        ];
    }
}
