<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Event> */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findBySlug(Book $book, string $slug): ?Event
    {
        return $this->findOneBy(['book' => $book, 'slug' => $slug]);
    }

    /** List query in chronological order: short columns only, no entity hydration (see ApiHelper::paginate). The slug is exposed as "id". */
    public function summariesQuery(Book $book, ?int $chapter, ?bool $revealed): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.slug AS id', 'e.title', 'e.summary', 'e.worldOrder', 'e.worldDate', 'e.chapter', 'e.revealed')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->orderBy('e.worldOrder')
            ->addOrderBy('e.slug');

        if (null !== $chapter) {
            $qb->andWhere('e.chapter = :chapter')->setParameter('chapter', $chapter);
        }
        if (null !== $revealed) {
            $qb->andWhere('e.revealed = :revealed')->setParameter('revealed', $revealed);
        }

        return $qb;
    }
}
