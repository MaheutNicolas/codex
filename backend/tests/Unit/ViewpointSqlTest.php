<?php

namespace App\Tests\Unit;

use App\Api\Viewpoint;
use App\Repository\ViewpointSql;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewpointSqlTest extends TestCase
{
    /** @return iterable<string, array{Viewpoint, string, string, array<string, int>}> */
    public static function cases(): iterable
    {
        yield 'a reader' => [new Viewpoint(), '', 'revealed = 1', []];
        yield 'a reader, with an alias' => [new Viewpoint(), 'e', 'e.revealed = 1', []];
        yield 'a reader at chapter 3' => [new Viewpoint(3), 'e', 'e.revealed = 1 AND e.chapter <= :maxChapter', ['maxChapter' => 3]];
        yield 'the author' => [new Viewpoint(null, true), 'e', '1 = 1', []];
        yield 'the author at chapter 3 keeps what is never told' => [new Viewpoint(3, true), '', '(chapter <= :maxChapter OR chapter IS NULL)', ['maxChapter' => 3]];
        yield 'before chapter 1' => [new Viewpoint(0), '', 'revealed = 1 AND chapter <= :maxChapter', ['maxChapter' => 0]];
    }

    /** @param array<string, int> $params */
    #[DataProvider('cases')]
    public function testEvents(Viewpoint $viewpoint, string $alias, string $sql, array $params): void
    {
        self::assertSame([$sql, $params], ViewpointSql::events($viewpoint, $alias));
    }
}
