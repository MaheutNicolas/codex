<?php

namespace App\Service;

use App\Entity\Book;
use App\Repository\EventParticipantRepository;
use App\Repository\EventRepository;
use App\Repository\KnowledgeRepository;

/**
 * Everything a book holds, in the shape of an import document (so an export can be imported again).
 * Three queries, whatever the size of the book. The formats (JSON file, Markdown...) are built by the browser.
 */
final class ExportService
{
    public function __construct(
        private readonly KnowledgeRepository $knowledge,
        private readonly EventRepository $events,
        private readonly EventParticipantRepository $participants,
    ) {
    }

    /** @return array{knowledge: list<array<string, mixed>>, events: list<array<string, mixed>>, participants: list<array<string, mixed>>} */
    public function export(Book $book): array
    {
        return [
            'knowledge' => $this->knowledge->exportRows($book),
            'events' => $this->events->exportRows($book),
            'participants' => $this->participants->linksQuery($book, null, null, null)->getQuery()->getArrayResult(),
        ];
    }
}
