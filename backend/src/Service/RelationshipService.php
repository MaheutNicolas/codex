<?php

namespace App\Service;

use App\Api\Viewpoint;
use App\Entity\Book;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\EventParticipantRepository;
use App\Repository\KnowledgeRepository;
use App\Repository\RelationRepository;

/**
 * Everything the book says about two entries together: how they stand with each other at the chapter reached, how
 * that evolved, and the events they both take part in. This is what an AI asks for before writing a scene with two
 * characters.
 */
final class RelationshipService
{
    public function __construct(
        private readonly KnowledgeRepository $knowledge,
        private readonly RelationRepository $relations,
        private readonly EventParticipantRepository $participants,
    ) {
    }

    /**
     * "status" says in one word where the pair stands: "linked" (the "relation" holds), "ended" (they were linked,
     * no longer), or "none" (nothing links them, at least as far as the reader knows). The "history" lists the visible
     * states oldest first and the sentences name both entries, so the direction of a relation cannot be misread.
     *
     * @return array<string, mixed>
     */
    public function between(Book $book, string $firstSlug, string $secondSlug, Viewpoint $viewpoint, int $eventsLimit): array
    {
        if ($firstSlug === $secondSlug) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, ['fields' => ['otherId' => ['Give two different entries.']]]);
        }
        $first = $this->knowledge->findBySlug($book, $firstSlug)
            ?? throw new ApiException(ErrorCode::KNOWLEDGE_NOT_FOUND, ['id' => $firstSlug]);
        $second = $this->knowledge->findBySlug($book, $secondSlug)
            ?? throw new ApiException(ErrorCode::KNOWLEDGE_NOT_FOUND, ['id' => $secondSlug]);

        // The relations of the first entry, seen from its side, then the one with the second.
        $states = RelationStates::resolve($this->relations->involving($book, $firstSlug), $firstSlug, $viewpoint, includeEnded: true, withHistory: true);
        $pair = null;
        foreach ($states as $state) {
            if ($state['with']['id'] === $secondSlug) {
                $pair = $state;
                break;
            }
        }

        $events = $this->participants->sharedEvents($book, $firstSlug, $secondSlug, $viewpoint, $eventsLimit);

        return [
            'entries' => [
                ['id' => $first->getSlug(), 'name' => $first->getName(), 'type' => $first->getType()],
                ['id' => $second->getSlug(), 'name' => $second->getName(), 'type' => $second->getType()],
            ],
            'status' => null === $pair ? 'none' : (isset($pair['ended']) ? 'ended' : 'linked'),
            'relation' => null === $pair || isset($pair['ended'])
                ? null
                : array_intersect_key($pair, array_flip(['type', 'direction', 'statement', 'since', 'note', 'secret'])),
            'history' => $pair['history'] ?? [],
            'sharedEvents' => [
                'total' => $events['total'],
                'data' => array_map(static function (array $event) {
                    return [
                        'id' => $event['id'],
                        'title' => $event['title'],
                        'chapter' => $event['chapter'],
                        'worldOrder' => $event['worldOrder'],
                        'worldDate' => $event['worldDate'],
                        'roles' => ['first' => $event['firstRole'], 'second' => $event['secondRole']],
                    ];
                }, $events['rows']),
            ],
        ];
    }
}
