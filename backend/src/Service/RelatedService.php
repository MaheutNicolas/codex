<?php

namespace App\Service;

use App\Api\Viewpoint;
use App\Entity\Book;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\EventParticipantRepository;
use App\Repository\KnowledgeRepository;

/**
 * The entries linked to an entry: first those it has an explicit relation with (that holds from the point of view),
 * then those that share events with it, the most often first. An entry in both lists appears once, with both pieces
 * of information; "source" says where each result comes from: "relation", "events" or "both".
 */
final class RelatedService
{
    private const PARTNER_STATS_LIMIT = 200;

    public function __construct(
        private readonly EventParticipantRepository $participants,
        private readonly KnowledgeRepository $knowledge,
        private readonly RelationStateService $relations,
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

        // The entries with a relation, and what they also share in events.
        $states = $this->relations->current($book, $slug, $viewpoint);
        $partners = array_map(static fn (array $state) => $state['with']['id'], $states);
        $shared = [];
        if ([] !== $partners) {
            $stats = $this->participants->relatedTo($book, $slug, $viewpoint, self::PARTNER_STATS_LIMIT, only: $partners);
            $shared = array_column($stats['rows'], null, 'id');
        }

        $data = [];
        foreach ($states as $state) {
            $events = $shared[$state['with']['id']] ?? null;
            $data[] = [
                'id' => $state['with']['id'],
                'name' => $state['with']['name'],
                'type' => $state['with']['type'],
                'source' => null === $events ? 'relation' : 'both',
                'relation' => array_intersect_key($state, array_flip(['type', 'direction', 'statement', 'since', 'note', 'secret'])),
                'sharedEvents' => $events['sharedEvents'] ?? 0,
                'firstChapter' => $events['firstChapter'] ?? null,
                'lastChapter' => $events['lastChapter'] ?? null,
            ];
        }
        $data = \array_slice($data, 0, $limit);

        // Then the entries that only share events, the most linked first.
        $others = $this->participants->relatedTo($book, $slug, $viewpoint, max(0, $limit - \count($data)), except: $partners);
        foreach ($others['rows'] as $row) {
            $data[] = $row + ['source' => 'events'];
        }

        return ['data' => $data, 'total' => \count($states) + $others['total'], 'limit' => $limit];
    }
}
