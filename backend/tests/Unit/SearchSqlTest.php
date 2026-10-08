<?php

namespace App\Tests\Unit;

use App\Repository\SearchSql;
use PHPUnit\Framework\TestCase;

final class SearchSqlTest extends TestCase
{
    public function testLikeAnyMatchesAnyColumnAgainstAnyPattern(): void
    {
        self::assertSame(
            '((name LIKE :like0 OR slug LIKE :like0) OR (name LIKE :like1 OR slug LIKE :like1))',
            SearchSql::likeAny(['name', 'slug'], 2),
        );
    }

    public function testLikeCountAddsUpTheMatchingPatterns(): void
    {
        self::assertSame(
            '((name LIKE :like0) + (name LIKE :like1) + (name LIKE :like2))',
            SearchSql::likeCount(['name'], 3),
        );
    }

    public function testParametersAreNumberedLikeThePlaceholders(): void
    {
        self::assertSame(['like0' => '%a%', 'like1' => '%b%'], SearchSql::likeParams(['%a%', '%b%']));
    }

    public function testJsonColumnsAreComparedWithoutRegardToCaseAndAccents(): void
    {
        self::assertSame('CONVERT(aliases USING utf8mb4) COLLATE utf8mb4_unicode_ci', SearchSql::jsonText('aliases'));
    }
}
