<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Knowledge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Knowledge> */
class KnowledgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Knowledge::class);
    }

    public function findBySlug(Book $book, string $slug): ?Knowledge
    {
        return $this->findOneBy(['book' => $book, 'slug' => $slug]);
    }

    /** List query: short columns only, no entity hydration (see ApiHelper::paginate). The slug is exposed as "id". */
    public function summariesQuery(Book $book, ?string $type): QueryBuilder
    {
        $qb = $this->createQueryBuilder('k')
            ->select('k.slug AS id', 'k.name', 'k.type', 'k.summary', 'k.aliases')
            ->andWhere('k.book = :book')->setParameter('book', $book)
            ->orderBy('k.name')
            ->addOrderBy('k.slug');

        if (null !== $type) {
            $qb->andWhere('k.type = :type')->setParameter('type', $type);
        }

        return $qb;
    }

    /**
     * Lightweight lexicon of every entry of a book, in plain SQL: no ORM overhead on the call the AI makes most.
     *
     * @return list<array{id: string, name: string, type: string, aliases: list<string>}>
     */
    public function lexicon(Book $book, ?string $type): array
    {
        $sql = 'SELECT slug, name, type, aliases FROM knowledge WHERE book_id = ?'.(null === $type ? '' : ' AND type = ?').' ORDER BY name, slug';
        $params = null === $type ? [$book->getId()] : [$book->getId(), $type];
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params);

        return array_map(static fn (array $row) => [
            'id' => $row['slug'],
            'name' => $row['name'],
            'type' => $row['type'],
            'aliases' => json_decode($row['aliases'], true, 512, JSON_THROW_ON_ERROR),
        ], $rows);
    }

    /**
     * Every entry of a book with all its fields, as plain arrays (no entity hydration), for the export.
     * The slug is exposed as "id".
     *
     * @return list<array<string, mixed>>
     */
    public function exportRows(Book $book): array
    {
        return $this->createQueryBuilder('k')
            ->select('k.slug AS id', 'k.type', 'k.name', 'k.summary', 'k.description', 'k.aliases')
            ->andWhere('k.book = :book')->setParameter('book', $book)
            ->orderBy('k.name')
            ->addOrderBy('k.slug')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Full-text search in the entries of a book (name, summary, description), plus a plain match on the
     * name, identifier and aliases so that short words and nicknames are found. Best matches first.
     *
     * @param string       $match the full-text query (boolean mode)
     * @param list<string> $likes LIKE patterns, one per word
     *
     * @return array{rows: list<array<string, mixed>>, total: int} the best $limit matches, and how many there are in all
     */
    public function search(Book $book, string $match, array $likes, int $limit): array
    {
        $any = SearchSql::likeAny(['name', 'slug', SearchSql::jsonText('aliases')], \count($likes));
        $named = SearchSql::likeCount(['name', 'slug', SearchSql::jsonText('aliases')], \count($likes));
        $where = 'book_id = :book AND (MATCH(name, summary, description) AGAINST (:match IN BOOLEAN MODE) OR '.$any.')';
        $params = ['book' => $book->getId(), 'match' => $match] + SearchSql::likeParams($likes);
        $connection = $this->getEntityManager()->getConnection();

        $rows = $connection->fetchAllAssociative(
            'SELECT slug AS id, name, type, summary, '
            .'MATCH(name, summary, description) AGAINST (:match IN BOOLEAN MODE) AS score, '
            .$named.' AS named '
            .'FROM knowledge WHERE '.$where.' ORDER BY named DESC, score DESC, name LIMIT '.$limit,
            $params,
        );

        return ['rows' => $rows, 'total' => (int) $connection->fetchOne('SELECT COUNT(*) FROM knowledge WHERE '.$where, $params)];
    }
}
