<?php

namespace App\Service;

use App\Entity\Book;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\EventParticipantRepository;
use App\Repository\EventRepository;
use App\Repository\KnowledgeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes a whole document (knowledge entries, events, participant links) in one transaction: either
 * everything is saved or nothing is. An item that already exists (same identifier) is updated, any other
 * is created. The browser has already checked and corrected the document, so the server only reports
 * the first problem it meets, with the path of the faulty item (for example "knowledge[2]").
 */
final class ImportService
{
    public const MAX_ITEMS = 1000;

    private const SECTIONS = ['knowledge', 'events', 'participants'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KnowledgeService $knowledge,
        private readonly EventService $events,
        private readonly EventParticipantService $participants,
        private readonly KnowledgeRepository $knowledgeRepository,
        private readonly EventRepository $eventRepository,
        private readonly EventParticipantRepository $participantRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array{created: array<string, int>, updated: array<string, int>}
     */
    public function import(Book $book, array $document): array
    {
        $this->checkShape($document);

        return $this->em->wrapInTransaction(function () use ($book, $document): array {
            $result = ['created' => array_fill_keys(self::SECTIONS, 0), 'updated' => array_fill_keys(self::SECTIONS, 0)];

            // Knowledge and events first, so that the links of the document can point to them.
            foreach ($document['knowledge'] ?? [] as $index => $item) {
                $slug = $item['id'] ?? null;
                $exists = \is_string($slug) && null !== $this->knowledgeRepository->findBySlug($book, $slug);
                $this->run('knowledge', $index, $exists, $result, fn () => $exists
                    ? $this->knowledge->update($book, $slug, array_diff_key($item, ['id' => 0]))
                    : $this->knowledge->create($book, $item));
            }

            foreach ($document['events'] ?? [] as $index => $item) {
                $slug = $item['id'] ?? null;
                $exists = \is_string($slug) && null !== $this->eventRepository->findBySlug($book, $slug);
                $this->run('events', $index, $exists, $result, fn () => $exists
                    ? $this->events->update($book, $slug, array_diff_key($item, ['id' => 0]))
                    : $this->events->create($book, $item));
            }

            foreach ($document['participants'] ?? [] as $index => $item) {
                $eventId = $item['eventId'] ?? null;
                $knowledgeId = $item['knowledgeId'] ?? null;
                $exists = \is_string($eventId) && \is_string($knowledgeId)
                    && null !== $this->participantRepository->findLink($book, $eventId, $knowledgeId);
                $this->run('participants', $index, $exists, $result, fn () => $exists
                    ? $this->participants->update($book, $eventId, $knowledgeId, array_diff_key($item, ['eventId' => 0, 'knowledgeId' => 0]))
                    : $this->participants->create($book, $item));
            }

            return $result;
        });
    }

    /** Anything that is not the expected document is plain "invalid JSON": the browser explains the details. */
    private function checkShape(array $document): void
    {
        foreach ($document as $section => $items) {
            if (!\in_array($section, self::SECTIONS, true)) {
                throw new ApiException(ErrorCode::INVALID_JSON, [], \sprintf('Unknown section "%s".', $section));
            }
            if (!\is_array($items) || !array_is_list($items)) {
                throw new ApiException(ErrorCode::INVALID_JSON, [], \sprintf('The section "%s" must be a list.', $section));
            }
            foreach ($items as $index => $item) {
                if (!\is_array($item) || ([] !== $item && array_is_list($item))) {
                    throw new ApiException(ErrorCode::INVALID_JSON, [], \sprintf('"%s[%d]" must be an object.', $section, $index));
                }
            }
            if (\count($items) > self::MAX_ITEMS) {
                throw new ApiException(
                    ErrorCode::VALIDATION_FAILED,
                    ['fields' => [$section => [\sprintf('At most %d items are accepted per section.', self::MAX_ITEMS)]]],
                );
            }
        }
    }

    /**
     * Runs one write and counts it. A failure is re-thrown with the path of the item, so the caller
     * knows which one stopped the import.
     *
     * @param array{created: array<string, int>, updated: array<string, int>} $result
     */
    private function run(string $section, int $index, bool $exists, array &$result, callable $write): void
    {
        try {
            $write();
        } catch (ApiException $e) {
            throw new ApiException($e->errorCode, ['path' => \sprintf('%s[%d]', $section, $index)] + $e->details, $e->getMessage());
        }

        ++$result[$exists ? 'updated' : 'created'][$section];
    }
}
