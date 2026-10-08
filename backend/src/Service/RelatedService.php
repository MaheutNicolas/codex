<?php

namespace App\Service;

use App\Api\Viewpoint;
use App\Entity\Book;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\EventParticipantRepository;
use App\Repository\KnowledgeRepository;

/**
 * The entries linked to an entry. For now the links are deduced from the events they share; each result says so
 * in "source", so that explicit relations can be added next to them later.
 */
final class RelatedService
{
    public function __construct(
        private readonly EventParticipantRepository $participants,
        private readonly KnowledgeRepository $knowledge,
    ) {
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, limit: int} "total" counts every related entry, not only the returned ones
     */
    public function related(Book $book, string $slug, Viewpoint $viewpoint, int $limit): array
    {
        if (null === $this->knowledge->findBySlug($book, $slug)) {
            throw new ApiException(ErrorCode::KNOWLEDGE_NOT_FOUND, ['id' => $slug]);
        }

        $related = $this->participants->relatedTo($book, $slug, $viewpoint, $limit);

        return [
            'data' => array_map(static fn (array $row) => $row + ['source' => 'events'], $related['rows']),
            'total' => $related['total'],
            'limit' => $limit,
        ];
    }
}
