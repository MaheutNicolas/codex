<?php

namespace App\Repository;

use App\Api\Viewpoint;
use App\Entity\Book;
use App\Entity\EventParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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
     * The entries that share events with the given one, the most linked first. Only the events the reader may see
     * count, so a secret never reveals a link. Two queries: the best $limit entries, and how many there are.
     *
     * @param list<string>|null $only   keep only these entries
     * @param list<string>|null $except leave these entries out
     *
     * @return array{rows: list<array{id: string, name: string, type: string, sharedEvents: int, firstChapter: int|null, lastChapter: int|null}>, total: int}
     */
    public function relatedTo(Book $book, string $knowledgeSlug, Viewpoint $viewpoint, int $limit, ?array $only = null, ?array $except = null): array
    {
        [$visible, $visibleParams] = ViewpointSql::events($viewpoint, 'e');
        $from = 'FROM event_participant p1 '
            .'JOIN knowledge k1 ON k1.id = p1.knowledge_id AND k1.book_id = :book AND k1.slug = :slug '
            .'JOIN event e ON e.id = p1.event_id AND '.$visible.' '
            .'JOIN event_participant p2 ON p2.event_id = e.id AND p2.knowledge_id <> p1.knowledge_id '
            .'JOIN knowledge k2 ON k2.id = p2.knowledge_id WHERE 1 = 1';
        $params = ['book' => $book->getId(), 'slug' => $knowledgeSlug] + $visibleParams;
        $types = [];
        if (!empty($only)) {
            $from .= ' AND k2.slug IN (:only)';
            $params['only'] = array_values($only);
            $types['only'] = ArrayParameterType::STRING;
        }
        if (!empty($except)) {
            $from .= ' AND k2.slug NOT IN (:except)';
            $params['except'] = array_values($except);
            $types['except'] = ArrayParameterType::STRING;
        }
        $connection = $this->getEntityManager()->getConnection();

        $rows = $connection->fetchAllAssociative(
            'SELECT k2.slug AS id, k2.name, k2.type, COUNT(DISTINCT e.id) AS sharedEvents, '
            .'MIN(e.chapter) AS firstChapter, MAX(e.chapter) AS lastChapter '
            .$from.' GROUP BY k2.id, k2.slug, k2.name, k2.type ORDER BY sharedEvents DESC, k2.name, k2.slug LIMIT '.$limit,
            $params,
            $types,
        );

        return [
            'rows' => array_map(static fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'sharedEvents' => (int) $row['sharedEvents'],
                'firstChapter' => null === $row['firstChapter'] ? null : (int) $row['firstChapter'],
                'lastChapter' => null === $row['lastChapter'] ? null : (int) $row['lastChapter'],
            ], $rows),
            'total' => (int) $connection->fetchOne('SELECT COUNT(DISTINCT k2.id) '.$from, $params, $types),
        ];
    }

    /**
     * The events two entries both take part in, in the order of the world, with the role each one has. Only the
     * events the reader may see. Two queries: the first $limit events, and how many there are.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function sharedEvents(Book $book, string $firstSlug, string $secondSlug, Viewpoint $viewpoint, int $limit): array
    {
        [$visible, $visibleParams] = ViewpointSql::events($viewpoint, 'e');
        $from = 'FROM event_participant p1 '
            .'JOIN knowledge k1 ON k1.id = p1.knowledge_id AND k1.book_id = :book AND k1.slug = :first '
            .'JOIN event_participant p2 ON p2.event_id = p1.event_id '
            .'JOIN knowledge k2 ON k2.id = p2.knowledge_id AND k2.book_id = :book AND k2.slug = :second '
            .'JOIN event e ON e.id = p1.event_id AND '.$visible;
        $params = ['book' => $book->getId(), 'first' => $firstSlug, 'second' => $secondSlug] + $visibleParams;
        $connection = $this->getEntityManager()->getConnection();

        $rows = $connection->fetchAllAssociative(
            'SELECT e.slug AS id, e.title, e.chapter, e.world_order AS worldOrder, e.world_date AS worldDate, '
            .'p1.role AS firstRole, p2.role AS secondRole '
            .$from.' ORDER BY e.world_order, e.slug LIMIT '.$limit,
            $params,
        );

        return [
            'rows' => array_map(static fn (array $row) => [
                'id' => $row['id'],
                'title' => $row['title'],
                'chapter' => null === $row['chapter'] ? null : (int) $row['chapter'],
                'worldOrder' => (int) $row['worldOrder'],
                'worldDate' => $row['worldDate'],
                'firstRole' => $row['firstRole'],
                'secondRole' => $row['secondRole'],
            ], $rows),
            'total' => (int) $connection->fetchOne('SELECT COUNT(*) '.$from, $params),
        ];
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
