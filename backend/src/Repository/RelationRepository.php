<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Knowledge;
use App\Entity\Relation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Relation> */
class RelationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Relation::class);
    }

    public function findBySlug(Book $book, string $slug): ?Relation
    {
        return $this->findOneBy(['book' => $book, 'slug' => $slug]);
    }

    /**
     * The relation already stating something about these two entries (in either direction) at this chapter, if any.
     * A pair has one state per chapter, so this is what stops a second one.
     */
    public function findAtChapter(Book $book, Knowledge $one, Knowledge $other, int $chapter, ?int $exceptId): ?Relation
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.book = :book')->setParameter('book', $book)
            ->andWhere('r.chapter = :chapter')->setParameter('chapter', $chapter)
            ->andWhere('(r.source = :one AND r.target = :other) OR (r.source = :other AND r.target = :one)')
            ->setParameter('one', $one)
            ->setParameter('other', $other)
            ->setMaxResults(1);
        if (null !== $exceptId) {
            $qb->andWhere('r.id <> :except')->setParameter('except', $exceptId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * List query: short columns only, no entity hydration (see Paginator), by chapter. The slugs are exposed as
     * identifiers. "chapter" is 0 for a state that holds from the start of the book (the service says null).
     * With a knowledge slug, only the relations that entry is in, whichever side it is on.
     */
    public function summariesQuery(Book $book, ?string $knowledgeSlug, ?string $type): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.slug AS id', 's.slug AS sourceId', 't.slug AS targetId', 'r.type', 'r.chapter', 'r.revealed', 'r.note')
            ->join('r.source', 's')
            ->join('r.target', 't')
            ->andWhere('r.book = :book')->setParameter('book', $book)
            ->orderBy('r.chapter')
            ->addOrderBy('r.slug');

        if (null !== $knowledgeSlug) {
            $qb->andWhere('s.slug = :knowledge OR t.slug = :knowledge')->setParameter('knowledge', $knowledgeSlug);
        }
        if (null !== $type) {
            $qb->andWhere('r.type = :type')->setParameter('type', $type);
        }

        return $qb;
    }

    /**
     * Every state of the relations an entry is in, whichever side it is on, with the names and types of the two
     * entries, by chapter. A few rows per other entry: read them all, then pick the right state in PHP.
     *
     * @return list<array<string, mixed>>
     */
    public function involving(Book $book, string $knowledgeSlug): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.slug AS id', 's.slug AS sourceId', 's.name AS sourceName', 's.type AS sourceType', 't.slug AS targetId', 't.name AS targetName', 't.type AS targetType', 'r.type', 'r.chapter', 'r.revealed', 'r.note')
            ->join('r.source', 's')
            ->join('r.target', 't')
            ->andWhere('r.book = :book')->setParameter('book', $book)
            ->andWhere('s.slug = :knowledge OR t.slug = :knowledge')->setParameter('knowledge', $knowledgeSlug)
            ->orderBy('r.chapter')
            ->addOrderBy('r.slug')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row) {
            $row['chapter'] = (int) $row['chapter'];

            return $row;
        }, $rows);
    }

    /**
     * Every relation of a book as plain arrays, for the export.
     *
     * @return list<array<string, mixed>>
     */
    public function exportRows(Book $book): array
    {
        return $this->summariesQuery($book, null, null)->getQuery()->getArrayResult();
    }
}
