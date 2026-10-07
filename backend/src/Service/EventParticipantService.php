<?php

namespace App\Service;

use App\Api\Page;
use App\Entity\Book;
use App\Entity\EventParticipant;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\EventParticipantRepository;
use App\Repository\EventRepository;
use App\Repository\KnowledgeRepository;
use App\Validation\Validate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Business logic of the links between events and knowledge entries. A link is addressed by the slugs
 * of its event and knowledge entry, both looked up in the same book, so a link can never cross books.
 * Returns plain arrays, ready to be sent as JSON.
 */
final class EventParticipantService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventParticipantRepository $participants,
        private readonly EventRepository $events,
        private readonly KnowledgeRepository $knowledge,
        private readonly Validate $validate,
        private readonly Hydrator $hydrator,
        private readonly Paginator $paginator,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int} */
    public function list(Book $book, ?string $eventSlug, ?string $knowledgeSlug, ?string $role, Page $page): array
    {
        return $this->paginator->paginate(
            $this->participants->linksQuery($book, $eventSlug, $knowledgeSlug, $role),
            'p.event',
            $page,
        );
    }

    /** @return array<string, mixed> */
    public function get(Book $book, string $eventSlug, string $knowledgeSlug): array
    {
        return $this->serialize($this->find($book, $eventSlug, $knowledgeSlug));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(Book $book, array $data): array
    {
        $this->validate->participant($data, creating: true);

        $event = $this->events->findBySlug($book, $data['eventId'])
            ?? throw $this->missingReference('eventId', $data['eventId']);
        $knowledge = $this->knowledge->findBySlug($book, $data['knowledgeId'])
            ?? throw $this->missingReference('knowledgeId', $data['knowledgeId']);

        $participant = (new EventParticipant())
            ->setEvent($event)
            ->setKnowledge($knowledge)
            ->setRole($data['role'] ?? null);
        $this->validate->entity($participant);

        if (null !== $this->participants->findLink($book, $event->getSlug(), $knowledge->getSlug())) {
            throw $this->alreadyExists($event->getSlug(), $knowledge->getSlug());
        }

        try {
            $this->em->persist($participant);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw $this->alreadyExists($event->getSlug(), $knowledge->getSlug());
        }

        return $this->serialize($participant);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(Book $book, string $eventSlug, string $knowledgeSlug, array $data): array
    {
        $participant = $this->find($book, $eventSlug, $knowledgeSlug);

        $this->validate->participant($data, creating: false);
        $this->hydrator->apply($participant, $data);
        $this->validate->entity($participant);
        $this->em->flush();

        return $this->serialize($participant);
    }

    public function delete(Book $book, string $eventSlug, string $knowledgeSlug): void
    {
        $this->em->remove($this->find($book, $eventSlug, $knowledgeSlug));
        $this->em->flush();
    }

    private function find(Book $book, string $eventSlug, string $knowledgeSlug): EventParticipant
    {
        return $this->participants->findLink($book, $eventSlug, $knowledgeSlug)
            ?? throw new ApiException(ErrorCode::PARTICIPANT_NOT_FOUND, ['eventId' => $eventSlug, 'knowledgeId' => $knowledgeSlug]);
    }

    private function missingReference(string $field, string $slug): ApiException
    {
        return new ApiException(ErrorCode::REFERENCE_NOT_FOUND, ['field' => $field, 'id' => $slug]);
    }

    private function alreadyExists(string $eventSlug, string $knowledgeSlug): ApiException
    {
        return new ApiException(
            ErrorCode::ID_ALREADY_EXISTS,
            ['resource' => 'event-participant', 'eventId' => $eventSlug, 'knowledgeId' => $knowledgeSlug],
        );
    }

    /** @return array<string, mixed> */
    private function serialize(EventParticipant $participant): array
    {
        return [
            'eventId' => $participant->getEvent()->getSlug(),
            'knowledgeId' => $participant->getKnowledge()->getSlug(),
            'role' => $participant->getRole(),
        ];
    }
}
