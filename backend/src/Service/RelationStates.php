<?php

namespace App\Service;

use App\Api\Viewpoint;
use App\Entity\Relation;

/**
 * Turns the stored states of the relations of one entry into what an AI reads: for each other entry, the state
 * that holds from the reader's point of view (the latest visible one), written from the side of the entry. Pure
 * logic, no database: RelationStateService feeds it.
 *
 * A state is visible when it is known to the reader (or the author asks for secrets) and has started by the last
 * chapter read; a state that holds "from the start of the book" (chapter 0) is always visible. A pair whose latest
 * visible state is "none" is no longer linked.
 */
final class RelationStates
{
    /**
     * @param list<array<string, mixed>> $rows the relations of the entry: id, sourceId, sourceName, sourceType,
     *                                         targetId, targetName, targetType, type, chapter (0: from the start),
     *                                         revealed, note
     * @param bool                       $includeEnded keep the pairs that are no longer linked (marked "ended")
     * @param bool                       $withHistory  add every visible state of each pair, oldest first
     *
     * @return list<array<string, mixed>> most recently changed first, then by name
     */
    public static function resolve(array $rows, string $entryId, Viewpoint $viewpoint, bool $includeEnded = false, bool $withHistory = false): array
    {
        $byPartner = [];
        foreach ($rows as $row) {
            if (!$viewpoint->includeSecrets && !$row['revealed']) {
                continue;
            }
            if (null !== $viewpoint->maxChapter && $row['chapter'] > $viewpoint->maxChapter) {
                continue;
            }
            $partner = $row['sourceId'] === $entryId ? $row['targetId'] : $row['sourceId'];
            $byPartner[$partner][] = $row;
        }

        $items = [];
        foreach ($byPartner as $states) {
            usort($states, static fn (array $a, array $b) => [$a['chapter'], $a['id']] <=> [$b['chapter'], $b['id']]);
            $latest = $states[array_key_last($states)];
            $ended = 'none' === $latest['type'];
            if ($ended && !$includeEnded) {
                continue;
            }

            $items[] = self::item($latest, $entryId, $ended, $withHistory ? $states : null);
        }

        // The most recent change first (the start of the book counts as chapter 0), then alphabetically.
        usort($items, static fn (array $a, array $b) => [$b['since'] ?? 0, $a['with']['name'], $a['with']['id']] <=> [$a['since'] ?? 0, $b['with']['name'], $b['with']['id']]);

        return $items;
    }

    /** A sentence that names both entries, so that the direction of a relation cannot be misread. */
    public static function statement(string $type, string $sourceName, string $targetName): string
    {
        return match ($type) {
            'ally' => "$sourceName and $targetName are allies.",
            'enemy' => "$sourceName and $targetName are enemies.",
            'rival' => "$sourceName and $targetName are rivals.",
            'friend' => "$sourceName and $targetName are friends.",
            'family' => "$sourceName and $targetName are family.",
            'partner' => "$sourceName and $targetName are partners.",
            'other' => "$sourceName and $targetName are linked.",
            'none' => "$sourceName and $targetName are no longer linked.",
            'mentor' => "$sourceName is the mentor of $targetName.",
            'parent' => "$sourceName is a parent of $targetName.",
            'member_of' => "$sourceName is a member of $targetName.",
            'leader_of' => "$sourceName leads $targetName.",
            'serves' => "$sourceName serves $targetName.",
            default => "$sourceName and $targetName are linked.",
        };
    }

    /**
     * @param array<string, mixed>            $latest
     * @param list<array<string, mixed>>|null $history
     *
     * @return array<string, mixed>
     */
    private static function item(array $latest, string $entryId, bool $ended, ?array $history): array
    {
        $isSource = $latest['sourceId'] === $entryId;
        $item = [
            'with' => $isSource
                ? ['id' => $latest['targetId'], 'name' => $latest['targetName'], 'type' => $latest['targetType']]
                : ['id' => $latest['sourceId'], 'name' => $latest['sourceName'], 'type' => $latest['sourceType']],
            'type' => $latest['type'],
            'direction' => Relation::isSymmetric($latest['type']) ? 'mutual' : ($isSource ? 'outgoing' : 'incoming'),
            'statement' => self::statement($latest['type'], $latest['sourceName'], $latest['targetName']),
            'since' => self::chapter($latest['chapter']),
            'note' => $latest['note'],
        ];
        if (!$latest['revealed']) {
            $item['secret'] = true;
        }
        if ($ended) {
            $item['ended'] = true;
        }
        if (null !== $history) {
            $item['history'] = array_map(static function (array $state) {
                $entry = [
                    'chapter' => self::chapter($state['chapter']),
                    'type' => $state['type'],
                    'statement' => self::statement($state['type'], $state['sourceName'], $state['targetName']),
                    'note' => $state['note'],
                ];
                if (!$state['revealed']) {
                    $entry['secret'] = true;
                }

                return $entry;
            }, $history);
        }

        return $item;
    }

    private static function chapter(int $chapter): ?int
    {
        return Relation::FROM_THE_START === $chapter ? null : $chapter;
    }
}
