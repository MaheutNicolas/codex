<?php

namespace App\Tests\Unit;

use App\Api\Viewpoint;
use App\Entity\Relation;
use App\Service\RelationStates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Which state of a relation holds from a point of view, written from the side of an entry. */
final class RelationStatesTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function row(string $id, string $source, string $target, string $type, int $chapter, bool $revealed = true, ?string $note = null): array
    {
        return [
            'id' => $id,
            'sourceId' => $source, 'sourceName' => ucfirst($source), 'sourceType' => 'character',
            'targetId' => $target, 'targetName' => ucfirst($target), 'targetType' => 'character',
            'type' => $type, 'chapter' => $chapter, 'revealed' => $revealed, 'note' => $note,
        ];
    }

    /** @return list<array<string, mixed>> Aldric and Mira: allies, then enemies from chapter 6, then no longer linked from chapter 9. */
    private static function progression(): array
    {
        return [
            self::row('rel-a', 'aldric', 'mira', 'ally', 0),
            self::row('rel-b', 'aldric', 'mira', 'enemy', 6),
            self::row('rel-c', 'mira', 'aldric', 'none', 9),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<string> "mira:enemy:6"
     */
    private static function summary(array $items): array
    {
        return array_map(static fn (array $i) => $i['with']['id'].':'.$i['type'].':'.($i['since'] ?? 'start'), $items);
    }

    /** @return iterable<string, array{Viewpoint, list<string>}> */
    public static function progressionViewpoints(): iterable
    {
        yield 'with no limit, the pair is no longer linked' => [new Viewpoint(), []];
        yield 'at chapter 0 (nothing read yet): the allies of the start' => [new Viewpoint(0), ['mira:ally:start']];
        yield 'at chapter 5' => [new Viewpoint(5), ['mira:ally:start']];
        yield 'at chapter 6, the change is visible' => [new Viewpoint(6), ['mira:enemy:6']];
        yield 'at chapter 8' => [new Viewpoint(8), ['mira:enemy:6']];
        yield 'at chapter 9, the relation has ended' => [new Viewpoint(9), []];
    }

    /** @param list<string> $expected */
    #[DataProvider('progressionViewpoints')]
    public function testTheLatestVisibleStateHolds(Viewpoint $viewpoint, array $expected): void
    {
        self::assertSame($expected, self::summary(RelationStates::resolve(self::progression(), 'aldric', $viewpoint)));
    }

    public function testTheRelationIsTheSameFromEitherSide(): void
    {
        $fromAldric = RelationStates::resolve(self::progression(), 'aldric', new Viewpoint(7));
        $fromMira = RelationStates::resolve(self::progression(), 'mira', new Viewpoint(7));

        self::assertSame('mira', $fromAldric[0]['with']['id']);
        self::assertSame('aldric', $fromMira[0]['with']['id']);
        self::assertSame('Aldric and Mira are enemies.', $fromAldric[0]['statement']);
        self::assertSame($fromAldric[0]['statement'], $fromMira[0]['statement'], 'The sentence names both entries the same way from both sides.');
        self::assertSame('mutual', $fromAldric[0]['direction']);
    }

    public function testADirectedRelationReadsFromEachSide(): void
    {
        $rows = [self::row('rel-m', 'corvin', 'aldric', 'mentor', 2, true, 'Taught him the sword.')];

        $fromCorvin = RelationStates::resolve($rows, 'corvin', new Viewpoint())[0];
        $fromAldric = RelationStates::resolve($rows, 'aldric', new Viewpoint())[0];

        self::assertSame('outgoing', $fromCorvin['direction']);
        self::assertSame('incoming', $fromAldric['direction']);
        self::assertSame('Corvin is the mentor of Aldric.', $fromCorvin['statement']);
        self::assertSame('Corvin is the mentor of Aldric.', $fromAldric['statement']);
        self::assertSame(['id' => 'aldric', 'name' => 'Aldric', 'type' => 'character'], $fromCorvin['with']);
        self::assertSame('Taught him the sword.', $fromAldric['note']);
        self::assertSame(2, $fromAldric['since']);
    }

    public function testTheOrientationMayFlipBetweenStatesOfAPair(): void
    {
        $rows = [self::row('rel-1', 'corvin', 'aldric', 'mentor', 2), self::row('rel-2', 'aldric', 'corvin', 'serves', 7)];

        $state = RelationStates::resolve($rows, 'aldric', new Viewpoint())[0];

        self::assertSame('serves', $state['type']);
        self::assertSame('Aldric serves Corvin.', $state['statement']);
        self::assertSame('outgoing', $state['direction']);
    }

    public function testSecretsOnlyShowToTheAuthor(): void
    {
        $rows = [
            self::row('rel-1', 'aldric', 'zed', 'friend', 1),
            self::row('rel-2', 'aldric', 'zed', 'enemy', 4, false),
        ];

        $reader = RelationStates::resolve($rows, 'aldric', new Viewpoint(5))[0];
        $author = RelationStates::resolve($rows, 'aldric', new Viewpoint(5, true))[0];

        self::assertSame('friend', $reader['type'], 'The reader still believes they are friends.');
        self::assertArrayNotHasKey('secret', $reader);
        self::assertSame('enemy', $author['type']);
        self::assertTrue($author['secret']);
        self::assertSame(4, $author['since']);
    }

    public function testASecretRelationAloneIsInvisibleToTheReader(): void
    {
        $rows = [self::row('rel-1', 'aldric', 'zed', 'enemy', 0, false)];

        self::assertSame([], RelationStates::resolve($rows, 'aldric', new Viewpoint()));
        self::assertCount(1, RelationStates::resolve($rows, 'aldric', new Viewpoint(null, true)));
    }

    public function testEndedPairsAreListedOnRequestWithTheirHistory(): void
    {
        $items = RelationStates::resolve(self::progression(), 'aldric', new Viewpoint(), includeEnded: true, withHistory: true);

        self::assertCount(1, $items);
        self::assertTrue($items[0]['ended']);
        self::assertSame('none', $items[0]['type']);
        self::assertSame(9, $items[0]['since']);
        self::assertSame(
            [[null, 'ally'], [6, 'enemy'], [9, 'none']],
            array_map(static fn (array $s) => [$s['chapter'], $s['type']], $items[0]['history']),
        );
        self::assertSame('Aldric and Mira are allies.', $items[0]['history'][0]['statement']);
        self::assertSame('Mira and Aldric are no longer linked.', $items[0]['history'][2]['statement']);
    }

    public function testTheHistoryStopsAtTheChapterReached(): void
    {
        $item = RelationStates::resolve(self::progression(), 'aldric', new Viewpoint(7), withHistory: true)[0];

        self::assertSame([null, 6], array_column($item['history'], 'chapter'), 'The end at chapter 9 is not known yet.');
    }

    public function testWithoutHistoryThereIsNone(): void
    {
        self::assertArrayNotHasKey('history', RelationStates::resolve(self::progression(), 'aldric', new Viewpoint(7))[0]);
    }

    public function testARelationCanStartAgainAfterItEnded(): void
    {
        $rows = [...self::progression(), self::row('rel-d', 'aldric', 'mira', 'partner', 12)];

        self::assertSame(['mira:partner:12'], self::summary(RelationStates::resolve($rows, 'aldric', new Viewpoint())));
        self::assertSame([], self::summary(RelationStates::resolve($rows, 'aldric', new Viewpoint(10))));
    }

    public function testTheMostRecentlyChangedComeFirstThenByName(): void
    {
        $rows = [
            self::row('rel-1', 'aldric', 'zed', 'ally', 0),
            self::row('rel-2', 'aldric', 'mira', 'ally', 0),
            self::row('rel-3', 'aldric', 'corvin', 'ally', 5),
            self::row('rel-4', 'aldric', 'bran', 'ally', 3),
        ];

        self::assertSame(['corvin', 'bran', 'mira', 'zed'], array_column(array_column(RelationStates::resolve($rows, 'aldric', new Viewpoint()), 'with'), 'id'));
    }

    public function testRowsInAnyOrderGiveTheSameAnswer(): void
    {
        $rows = self::progression();

        self::assertSame(
            RelationStates::resolve($rows, 'aldric', new Viewpoint(7)),
            RelationStates::resolve(array_reverse($rows), 'aldric', new Viewpoint(7)),
        );
    }

    public function testEveryTypeHasASentence(): void
    {
        foreach (Relation::TYPES as $type) {
            $sentence = RelationStates::statement($type, 'Aldric', 'Mira');
            self::assertStringContainsString('Aldric', $sentence, $type);
            self::assertStringContainsString('Mira', $sentence, $type);
            self::assertStringEndsWith('.', $sentence, $type);
        }
    }
}
