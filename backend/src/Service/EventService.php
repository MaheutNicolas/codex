<?php

namespace App\Service;

use App\Api\Page;
use App\Entity\Book;
use App\Entity\Event;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\EventRepository;
use App\Validation\Validate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/** Business logic of the events of a book. Returns plain arrays, ready to be sent as JSON. */
final class EventService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventRepository $repository,
        private readonly Validate $validate,
        private readonly Hydrator $hydrator,
        private readonly Paginator $paginator,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int} */
    public function list(Book $book, ?int $chapter, ?bool $revealed, Page $page): array
    {
        return $this->paginator->paginate($this->repository->summariesQuery($book, $chapter, $revealed), 'e.id', $page);
    }

    /** @return array<string, mixed> */
    public function get(Book $book, string $slug): array
    {
        return $this->serialize($this->find($book, $slug));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(Book $book, array $data): array
    {
        $this->validate->event($data, creating: true);

        $event = (new Event())->setBook($book);
        $this->hydrator->apply($event, $data);
        $this->validate->entity($event);

        if (null !== $this->repository->findBySlug($book, $event->getSlug())) {
            throw $this->alreadyExists($event->getSlug());
        }

        try {
            $this->em->persist($event);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw $this->alreadyExists($event->getSlug());
        }

        return $this->serialize($event);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(Book $book, string $slug, array $data): array
    {
        $event = $this->find($book, $slug);

        $this->validate->event($data, creating: false);
        $this->hydrator->apply($event, $data);
        $this->validate->entity($event);
        $this->em->flush();

        return $this->serialize($event);
    }

    public function delete(Book $book, string $slug): void
    {
        // The participant links are removed by the database (ON DELETE CASCADE).
        $this->em->remove($this->find($book, $slug));
        $this->em->flush();
    }

    private function find(Book $book, string $slug): Event
    {
        return $this->repository->findBySlug($book, $slug)
            ?? throw new ApiException(ErrorCode::EVENT_NOT_FOUND, ['id' => $slug]);
    }

    private function alreadyExists(string $slug): ApiException
    {
        return new ApiException(ErrorCode::ID_ALREADY_EXISTS, ['resource' => 'event', 'id' => $slug]);
    }

    /** @return array<string, mixed> */
    private function serialize(Event $event): array
    {
        return [
            'id' => $event->getSlug(),
            'title' => $event->getTitle(),
            'summary' => $event->getSummary(),
            'detail' => $event->getDetail(),
            'worldOrder' => $event->getWorldOrder(),
            'worldDate' => $event->getWorldDate(),
            'chapter' => $event->getChapter(),
            'revealed' => $event->isRevealed(),
            'tags' => $event->getTags(),
            'createdAt' => $event->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $event->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }
}
