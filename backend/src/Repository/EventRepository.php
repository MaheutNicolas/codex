<?php

namespace App\Repository;

use App\Api\Viewpoint;
use App\Entity\Book;
use App\Entity\Event;
use App\Entity\EventParticipant;
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

    /**
     * Every event of a book with all its fields, in chronological order, as plain arrays (no entity
     * hydration), for the export. The slug is exposed as "id".
     *
     * @return list<array<string, mixed>>
     */
    public function exportRows(Book $book): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.slug AS id', 'e.title', 'e.summary', 'e.detail', 'e.worldOrder', 'e.worldDate', 'e.chapter', 'e.revealed', 'e.tags')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->orderBy('e.worldOrder')
            ->addOrderBy('e.slug')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * The events a reader may see, in chronological order (short columns only, like summariesQuery).
     * With a knowledge slug, only the events that entry takes part in, with its role.
     */
    public function timelineQuery(Book $book, Viewpoint $viewpoint, ?string $knowledgeSlug): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.slug AS id', 'e.title', 'e.summary', 'e.worldOrder', 'e.worldDate', 'e.chapter', 'e.revealed')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->orderBy('e.worldOrder')
            ->addOrderBy('e.slug');

        // The secrets of the author are the events the reader does not know yet.
        if (!$viewpoint->includeSecrets) {
            $qb->andWhere('e.revealed = true');
        }
        // An event told in a later chapter is not known yet; one that is never told (no chapter) is only
        // part of the author's knowledge.
        if (null !== $viewpoint->maxChapter) {
            $qb->andWhere($viewpoint->includeSecrets ? 'e.chapter <= :maxChapter OR e.chapter IS NULL' : 'e.chapter <= :maxChapter')
                ->setParameter('maxChapter', $viewpoint->maxChapter);
        }
        if (null !== $knowledgeSlug) {
            $qb->addSelect('p.role AS role')
                ->join(EventParticipant::class, 'p', 'WITH', 'p.event = e')
                ->join('p.knowledge', 'k')
                ->andWhere('k.slug = :knowledgeSlug')->setParameter('knowledgeSlug', $knowledgeSlug);
        }

        return $qb;
    }

    /**
     * Full-text search in the events a reader may see. Returns plain rows, best matches first.
     *
     * @param string       $match the full-text query (boolean mode)
     * @param list<string> $likes LIKE patterns, one per word
     *
     * @return list<array<string, mixed>>
     */
    public function search(Book $book, Viewpoint $viewpoint, string $match, array $likes, int $limit): array
    {
        $any = SearchSql::likeAny(['title', 'slug', 'tags'], \count($likes));
        $named = SearchSql::likeCount(['title', 'slug', 'tags'], \count($likes));
        $where = 'book_id = :book AND (MATCH(title, summary, detail) AGAINST (:match IN BOOLEAN MODE) OR '.$any.')';
        $params = ['book' => $book->getId(), 'match' => $match] + SearchSql::likeParams($likes);
        if (!$viewpoint->includeSecrets) {
            $where .= ' AND revealed = 1';
        }
        if (null !== $viewpoint->maxChapter) {
            $where .= $viewpoint->includeSecrets ? ' AND (chapter <= :maxChapter OR chapter IS NULL)' : ' AND chapter <= :maxChapter';
            $params['maxChapter'] = $viewpoint->maxChapter;
        }

        $sql = 'SELECT slug AS id, title, summary, chapter, world_order AS worldOrder, '
            .'MATCH(title, summary, detail) AGAINST (:match IN BOOLEAN MODE) AS score, '
            .$named.' AS named '
            .'FROM event WHERE '.$where.' ORDER BY named DESC, score DESC, world_order LIMIT '.$limit;

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params);
    }
}
