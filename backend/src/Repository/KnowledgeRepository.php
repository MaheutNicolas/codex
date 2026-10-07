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
            ->select('k.slug AS id', 'k.name', 'k.type', 'k.summary')
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
}
