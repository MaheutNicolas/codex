<?php

namespace App\Service;

use App\Api\Page;
use Doctrine\ORM\QueryBuilder;

final class Paginator
{
    /**
     * Runs a paginated list query. The query must select scalar columns only (no entities):
     * rows are returned as arrays, which skips hydration and keeps long text columns out.
     *
     * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function paginate(QueryBuilder $qb, string $countExpression, Page $page): array
    {
        $total = (int) (clone $qb)
            ->select('COUNT('.$countExpression.')')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $rows = $qb->setFirstResult($page->offset)->setMaxResults($page->limit)->getQuery()->getArrayResult();

        return ['data' => $rows, 'total' => $total, 'limit' => $page->limit, 'offset' => $page->offset];
    }
}
