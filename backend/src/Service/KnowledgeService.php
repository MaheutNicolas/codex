<?php

namespace App\Service;

use App\Api\Page;
use App\Api\Viewpoint;
use App\Entity\Book;
use App\Entity\Knowledge;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\KnowledgeRepository;
use App\Validation\Validate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/** Business logic of the knowledge entries of a book. Returns plain arrays, ready to be sent as JSON. */
final class KnowledgeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KnowledgeRepository $repository,
        private readonly Validate $validate,
        private readonly Hydrator $hydrator,
        private readonly Paginator $paginator,
        private readonly TimelineService $timeline,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int} */
    public function list(Book $book, ?string $type, Page $page): array
    {
        return $this->paginator->paginate($this->repository->summariesQuery($book, $type), 'k.id', $page);
    }

    /** @return list<array{id: string, name: string, type: string, aliases: list<string>}> */
    public function lexicon(Book $book, ?string $type): array
    {
        return $this->repository->lexicon($book, $type);
    }

    /** @return array<string, mixed> */
    public function get(Book $book, string $slug): array
    {
        return $this->serialize($this->find($book, $slug));
    }

    /**
     * An entry with the events it takes part in (those the reader may see), as an AI reads it in one call.
     * The events carry the role of the entry but not their other participants: read an event for those.
     *
     * @return array<string, mixed>
     */
    public function sheet(Book $book, string $slug, Viewpoint $viewpoint, Page $page): array
    {
        $entry = $this->get($book, $slug);

        return $entry + ['events' => $this->timeline->timeline($book, $viewpoint, $entry['id'], $page, withParticipants: false)];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(Book $book, array $data): array
    {
        $this->validate->knowledge($data, creating: true);

        $knowledge = (new Knowledge())->setBook($book);
        $this->hydrator->apply($knowledge, $data);
        $this->validate->entity($knowledge);

        if (null !== $this->repository->findBySlug($book, $knowledge->getSlug())) {
            throw $this->alreadyExists($knowledge->getSlug());
        }

        try {
            $this->em->persist($knowledge);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw $this->alreadyExists($knowledge->getSlug());
        }

        return $this->serialize($knowledge);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(Book $book, string $slug, array $data): array
    {
        $knowledge = $this->find($book, $slug);

        $this->validate->knowledge($data, creating: false);
        $this->hydrator->apply($knowledge, $data);
        $this->validate->entity($knowledge);
        $this->em->flush();

        return $this->serialize($knowledge);
    }

    public function delete(Book $book, string $slug): void
    {
        // The participant links are removed by the database (ON DELETE CASCADE).
        $this->em->remove($this->find($book, $slug));
        $this->em->flush();
    }

    private function find(Book $book, string $slug): Knowledge
    {
        return $this->repository->findBySlug($book, $slug)
            ?? throw new ApiException(ErrorCode::KNOWLEDGE_NOT_FOUND, ['id' => $slug]);
    }

    private function alreadyExists(string $slug): ApiException
    {
        return new ApiException(ErrorCode::ID_ALREADY_EXISTS, ['resource' => 'knowledge', 'id' => $slug]);
    }

    /** @return array<string, mixed> */
    private function serialize(Knowledge $knowledge): array
    {
        return [
            'id' => $knowledge->getSlug(),
            'type' => $knowledge->getType(),
            'name' => $knowledge->getName(),
            'summary' => $knowledge->getSummary(),
            'description' => $knowledge->getDescription(),
            'aliases' => $knowledge->getAliases(),
            'createdAt' => $knowledge->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $knowledge->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }
}
