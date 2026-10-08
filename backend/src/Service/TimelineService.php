<?php

namespace App\Service;

use App\Api\Page;
use App\Api\Viewpoint;
use App\Entity\Book;
use App\Repository\EventParticipantRepository;
use App\Repository\EventRepository;

/**
 * The timeline as an AI reads it: the events a reader may see (see Viewpoint), in the order of the
 * world, each with its participants. Two queries for a page, whatever its size.
 */
final class TimelineService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventParticipantRepository $participants,
        private readonly Paginator $paginator,
    ) {
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function timeline(Book $book, Viewpoint $viewpoint, ?string $knowledgeSlug, Page $page): array
    {
        $result = $this->paginator->paginate($this->events->timelineQuery($book, $viewpoint, $knowledgeSlug), 'e.id', $page);

        $byEvent = [];
        foreach ($this->participants->participantsOf($book, array_column($result['data'], 'id')) as $row) {
            $byEvent[$row['eventId']][] = ['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']];
        }
        foreach ($result['data'] as &$event) {
            $event['participants'] = $byEvent[$event['id']] ?? [];
        }

        return $result;
    }
}
