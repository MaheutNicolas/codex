<?php

namespace App\Repository;

/** Small helpers shared by the full-text searches of the repositories. */
final class SearchSql
{
    /**
     * "(a LIKE :like0 OR b LIKE :like0) OR (a LIKE :like1 OR b LIKE :like1)": any column matching any pattern.
     *
     * @param list<string> $columns
     */
    public static function likeAny(array $columns, int $patterns): string
    {
        return '('.implode(' OR ', self::groups($columns, $patterns)).')';
    }

    /**
     * "(a LIKE :like0 OR b LIKE :like0) + (a LIKE :like1 OR b LIKE :like1)": how many patterns match.
     *
     * @param list<string> $columns
     */
    public static function likeCount(array $columns, int $patterns): string
    {
        return '('.implode(' + ', self::groups($columns, $patterns)).')';
    }

    /**
     * The named parameters of the patterns used by likeAny() and likeCount().
     *
     * @param list<string> $patterns
     *
     * @return array<string, string>
     */
    public static function likeParams(array $patterns): array
    {
        $params = [];
        foreach ($patterns as $i => $pattern) {
            $params["like$i"] = $pattern;
        }

        return $params;
    }

    /**
     * A JSON column (aliases, tags) as text that is compared without regard to case and accents, like the
     * other columns. A JSON column otherwise compares as binary text on both MySQL and MariaDB.
     */
    public static function jsonText(string $column): string
    {
        return "CONVERT($column USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    /**
     * @param list<string> $columns
     *
     * @return list<string>
     */
    private static function groups(array $columns, int $patterns): array
    {
        $groups = [];
        for ($i = 0; $i < $patterns; ++$i) {
            $groups[] = '('.implode(' OR ', array_map(static fn (string $column) => "$column LIKE :like$i", $columns)).')';
        }

        return $groups;
    }
}
