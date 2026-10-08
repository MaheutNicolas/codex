<?php

namespace App\Service;

use App\Api\Page;
use App\Entity\Book;
use App\Entity\Knowledge;
use App\Entity\Relation;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\KnowledgeRepository;
use App\Repository\RelationRepository;
use App\Validation\Validate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Business logic of the relations between entries. The relation of two entries is a list of states, one per
 * chapter (see Relation); the API says null for a state that holds "from the start of the book" (stored as 0).
 * Only the types of entries listed in Relation::ENTRY_TYPES can be in a relation. Returns plain arrays, ready to be
 * sent as JSON.
 */
final class RelationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RelationRepository $repository,
        private readonly KnowledgeRepository $knowledge,
        private readonly Validate $validate,
        private readonly Paginator $paginator,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int} */
    public function list(Book $book, ?string $knowledgeSlug, ?string $type, Page $page): array
    {
        $result = $this->paginator->paginate($this->repository->summariesQuery($book, $knowledgeSlug, $type), 'r.id', $page);
        $result['data'] = array_map($this->fromRow(...), $result['data']);

        return $result;
    }

    /**
     * Every relation of a book, by chapter (for the export).
     *
     * @return list<array<string, mixed>>
     */
    public function all(Book $book): array
    {
        return array_map($this->fromRow(...), $this->repository->exportRows($book));
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
        $this->validate->relation($data, creating: true);

        $relation = (new Relation())->setBook($book)->setSlug($data['id'] ?? '');
        $this->apply($relation, $book, $data);
        $this->validate->entity($relation);
        $this->checkPair($relation, $book);

        if (null !== $this->repository->findBySlug($book, $relation->getSlug())) {
            throw $this->alreadyExists($relation->getSlug());
        }

        try {
            $this->em->persist($relation);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Two requests at once: say which of the two rules was broken.
            $this->checkPair($relation, $book);
            throw $this->alreadyExists($relation->getSlug());
        }

        return $this->serialize($relation);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(Book $book, string $slug, array $data): array
    {
        $relation = $this->find($book, $slug);

        $this->validate->relation($data, creating: false);
        $this->apply($relation, $book, $data);
        $this->validate->entity($relation);
        $this->checkPair($relation, $book);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->checkPair($relation, $book);
            throw $this->alreadyExists($slug);
        }

        return $this->serialize($relation);
    }

    public function delete(Book $book, string $slug): void
    {
        $this->em->remove($this->find($book, $slug));
        $this->em->flush();
    }

    private function find(Book $book, string $slug): Relation
    {
        return $this->repository->findBySlug($book, $slug)
            ?? throw new ApiException(ErrorCode::RELATION_NOT_FOUND, ['id' => $slug]);
    }

    /**
     * Copies the fields of the request onto the relation. The two entries are named by their identifiers and must
     * belong to the same book; a null chapter means "from the start of the book".
     *
     * @param array<string, mixed> $data
     */
    private function apply(Relation $relation, Book $book, array $data): void
    {
        // The type of an entry is only checked when the request names it: a relation that was made before this rule
        // (with a place, say) can still be edited, moved to other entries, or deleted.
        $wrongType = [];
        foreach (['sourceId' => 'setSource', 'targetId' => 'setTarget'] as $field => $setter) {
            if (\array_key_exists($field, $data)) {
                $entry = $this->knowledge->findBySlug($book, $data[$field])
                    ?? throw new ApiException(ErrorCode::REFERENCE_NOT_FOUND, ['field' => $field, 'id' => $data[$field]]);
                if (!\in_array($entry->getType(), Relation::ENTRY_TYPES, true)) {
                    $wrongType[$field] = [\sprintf('Only these types of entries can have relations: %s.', implode(', ', Relation::ENTRY_TYPES))];
                }
                $relation->$setter($entry);
            }
        }
        if ([] !== $wrongType) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, ['fields' => $wrongType]);
        }

        if (\array_key_exists('chapter', $data)) {
            if (\is_int($data['chapter']) && $data['chapter'] < 1) {
                throw new ApiException(ErrorCode::VALIDATION_FAILED, ['fields' => ['chapter' => ['The chapter must be 1 or more, or null for "from the start of the book".']]]);
            }
            $relation->setChapter($data['chapter'] ?? Relation::FROM_THE_START);
        }
        foreach (['type' => 'setType', 'revealed' => 'setRevealed', 'note' => 'setNote'] as $field => $setter) {
            if (\array_key_exists($field, $data)) {
                $relation->$setter($data[$field]);
            }
        }
    }

    /** An entry has no relation with itself, and a pair has one state per chapter (in either direction). */
    private function checkPair(Relation $relation, Book $book): void
    {
        $source = $relation->getSource();
        $target = $relation->getTarget();

        if ($source === $target) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, ['fields' => ['targetId' => ['An entry cannot have a relation with itself.']]]);
        }

        $existing = $this->repository->findAtChapter($book, $source, $target, $relation->getChapter(), $relation->getId());
        if (null !== $existing) {
            throw new ApiException(ErrorCode::RELATION_ALREADY_EXISTS, [
                'existing' => $existing->getSlug(),
                'sourceId' => $source->getSlug(),
                'targetId' => $target->getSlug(),
                'chapter' => Relation::FROM_THE_START === $existing->getChapter() ? null : $existing->getChapter(),
            ]);
        }
    }

    private function alreadyExists(string $slug): ApiException
    {
        return new ApiException(ErrorCode::ID_ALREADY_EXISTS, ['resource' => 'relation', 'id' => $slug]);
    }

    /**
     * @param array<string, mixed> $row a row of RelationRepository::summariesQuery()
     *
     * @return array<string, mixed>
     */
    private function fromRow(array $row): array
    {
        $row['chapter'] = Relation::FROM_THE_START === (int) $row['chapter'] ? null : (int) $row['chapter'];

        return $row;
    }

    /** @return array<string, mixed> */
    private function serialize(Relation $relation): array
    {
        return [
            'id' => $relation->getSlug(),
            'sourceId' => $relation->getSource()->getSlug(),
            'targetId' => $relation->getTarget()->getSlug(),
            'type' => $relation->getType(),
            'chapter' => Relation::FROM_THE_START === $relation->getChapter() ? null : $relation->getChapter(),
            'revealed' => $relation->isRevealed(),
            'note' => $relation->getNote(),
            'createdAt' => $relation->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $relation->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }
}
