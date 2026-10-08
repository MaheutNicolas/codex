<?php

namespace App\Repository;

use App\Api\Viewpoint;

/**
 * The visibility rule of Viewpoint::allows() for hand-written SQL (search, related entries...). The
 * timeline query builds the same rule with the query builder: keep the three in step (the tests do).
 */
final class ViewpointSql
{
    /**
     * A condition on the events a reader may see, and its parameters.
     *
     * @param string $alias the alias of the event table, or '' when the query has no alias
     *
     * @return array{0: string, 1: array<string, int>} the SQL (always usable after AND) and its named parameters
     */
    public static function events(Viewpoint $viewpoint, string $alias = ''): array
    {
        $column = static fn (string $name) => '' === $alias ? $name : "$alias.$name";

        $conditions = [];
        $params = [];
        if (!$viewpoint->includeSecrets) {
            $conditions[] = $column('revealed').' = 1';
        }
        if (null !== $viewpoint->maxChapter) {
            $conditions[] = $viewpoint->includeSecrets
                ? '('.$column('chapter').' <= :maxChapter OR '.$column('chapter').' IS NULL)'
                : $column('chapter').' <= :maxChapter';
            $params['maxChapter'] = $viewpoint->maxChapter;
        }

        return [[] === $conditions ? '1 = 1' : implode(' AND ', $conditions), $params];
    }
}
