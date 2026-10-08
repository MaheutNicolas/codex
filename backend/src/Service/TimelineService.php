<?php

namespace App\Service;

use App\Api\Page;
use App\Api\Viewpoint;
use App\Entity\Book;
use App\Error\ApiException;
use App\Error\ErrorCode;
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
        private readonly EventService $eventService,
    ) {
    }

    /**
     * @param bool $withParticipants false for a lighter answer: the list of participants of every event is the
     *                               bulk of it, and an entry's own events already carry its role
     *
     * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function timeline(Book $book, Viewpoint $viewpoint, ?string $knowledgeSlug, Page $page, bool $withParticipants = true): array
    {
        $result = $this->paginator->paginate($this->events->timelineQuery($book, $viewpoint, $knowledgeSlug), 'e.id', $page);
        if (!$withParticipants) {
            return $result;
        }

        $byEvent = [];
        foreach ($this->participants->participantsOf($book, array_column($result['data'], 'id')) as $row) {
            $byEvent[$row['eventId']][] = ['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']];
        }
        foreach ($result['data'] as &$event) {
            $event['participants'] = $byEvent[$event['id']] ?? [];
        }

        return $result;
    }

    /** The highest chapter in which an event is told: how far the story has been written, or null. */
    public function lastChapter(Book $book): ?int
    {
        return $this->events->lastChapter($book);
    }

    /**
     * One event with its detail and participants, if it is visible from this point of view
     * (otherwise it is reported as not found, like an event that does not exist).
     *
     * @return array<string, mixed>
     */
    public function event(Book $book, string $slug, Viewpoint $viewpoint): array
    {
        $event = $this->eventService->get($book, $slug);
        if (!$viewpoint->allows($event['revealed'], $event['chapter'])) {
            throw new ApiException(ErrorCode::EVENT_NOT_FOUND, ['id' => $slug]);
        }

        $event['participants'] = array_map(
            static fn (array $row) => ['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']],
            $this->participants->participantsOf($book, [$slug]),
        );

        return $event;
    }
}
