<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\EventParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EventParticipant> */
class EventParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventParticipant::class);
    }

    /** Looks a link up by the slugs of its event and knowledge entry, within a book. */
    public function findLink(Book $book, string $eventSlug, string $knowledgeSlug): ?EventParticipant
    {
        return $this->createQueryBuilder('p')
            ->join('p.event', 'e')
            ->join('p.knowledge', 'k')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->andWhere('e.slug = :eventSlug')->setParameter('eventSlug', $eventSlug)
            ->andWhere('k.slug = :knowledgeSlug')->setParameter('knowledgeSlug', $knowledgeSlug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The participants of several events of a book (identifier, name, role), in one query.
     *
     * @param list<string> $eventSlugs
     *
     * @return list<array{eventId: string, id: string, name: string, role: string|null}>
     */
    public function participantsOf(Book $book, array $eventSlugs): array
    {
        if ([] === $eventSlugs) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->select('e.slug AS eventId', 'k.slug AS id', 'k.name', 'p.role')
            ->join('p.event', 'e')
            ->join('p.knowledge', 'k')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->andWhere('e.slug IN (:slugs)')->setParameter('slugs', $eventSlugs)
            ->orderBy('k.name')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * List query: reads the slugs through two joins, no entity hydration (see ApiHelper::paginate).
     * An event and its participants always belong to the same book, so filtering on the event is enough.
     */
    public function linksQuery(Book $book, ?string $eventSlug, ?string $knowledgeSlug, ?string $role): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->select('e.slug AS eventId', 'k.slug AS knowledgeId', 'p.role')
            ->join('p.event', 'e')
            ->join('p.knowledge', 'k')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->orderBy('eventId')
            ->addOrderBy('knowledgeId');

        if (null !== $eventSlug) {
            $qb->andWhere('e.slug = :eventSlug')->setParameter('eventSlug', $eventSlug);
        }
        if (null !== $knowledgeSlug) {
            $qb->andWhere('k.slug = :knowledgeSlug')->setParameter('knowledgeSlug', $knowledgeSlug);
        }
        if (null !== $role) {
            $qb->andWhere('p.role = :role')->setParameter('role', $role);
        }

        return $qb;
    }
}
