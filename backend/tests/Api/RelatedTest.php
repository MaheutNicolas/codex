<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** The entries linked to an entry through the events they share. */
final class RelatedTest extends ApiTestCase
{
    private int $bookId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();

        // "alpha" shares: e1 with beta and gamma, e2 and e3 with beta, e4 with delta, e5 with gamma and delta.
        // e3 is a secret of chapter 5, e5 is never told (and unknown to the reader).
        $this->import($this->bookId, [
            'knowledge' => [
                ['id' => 'alpha', 'type' => 'character', 'name' => 'Alpha', 'summary' => 's'],
                ['id' => 'beta', 'type' => 'character', 'name' => 'Beta', 'summary' => 's'],
                ['id' => 'gamma', 'type' => 'place', 'name' => 'Gamma', 'summary' => 's'],
                ['id' => 'delta', 'type' => 'group', 'name' => 'Delta', 'summary' => 's'],
                ['id' => 'loner', 'type' => 'character', 'name' => 'Loner', 'summary' => 's'],
            ],
            'events' => [
                ['id' => 'e1', 'title' => 'One', 'summary' => 's', 'worldOrder' => 1, 'chapter' => 1],
                ['id' => 'e2', 'title' => 'Two', 'summary' => 's', 'worldOrder' => 2, 'chapter' => 3],
                ['id' => 'e3', 'title' => 'Three', 'summary' => 's', 'worldOrder' => 3, 'chapter' => 5, 'revealed' => false],
                ['id' => 'e4', 'title' => 'Four', 'summary' => 's', 'worldOrder' => 4, 'chapter' => 6],
                ['id' => 'e5', 'title' => 'Five', 'summary' => 's', 'worldOrder' => 5, 'chapter' => null, 'revealed' => false],
            ],
            'participants' => [
                ['eventId' => 'e1', 'knowledgeId' => 'alpha'], ['eventId' => 'e1', 'knowledgeId' => 'beta'], ['eventId' => 'e1', 'knowledgeId' => 'gamma'],
                ['eventId' => 'e2', 'knowledgeId' => 'alpha'], ['eventId' => 'e2', 'knowledgeId' => 'beta'],
                ['eventId' => 'e3', 'knowledgeId' => 'alpha'], ['eventId' => 'e3', 'knowledgeId' => 'beta'],
                ['eventId' => 'e4', 'knowledgeId' => 'alpha'], ['eventId' => 'e4', 'knowledgeId' => 'delta'],
                ['eventId' => 'e5', 'knowledgeId' => 'alpha'], ['eventId' => 'e5', 'knowledgeId' => 'gamma'], ['eventId' => 'e5', 'knowledgeId' => 'delta'],
            ],
        ]);
    }

    /**
     * @return list<array{id: string, shared: int, first: int|null, last: int|null}>
     */
    private function related(string $id, string $query = ''): array
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/$id/related$query");
        self::assertSame(200, $response['status'], json_encode($response['data']));

        return array_map(
            static fn (array $r) => ['id' => $r['id'], 'shared' => $r['sharedEvents'], 'first' => $r['firstChapter'], 'last' => $r['lastChapter']],
            $response['data']['data'],
        );
    }

    /** @return iterable<string, array{string, list<array{id: string, shared: int, first: int|null, last: int|null}>}> */
    public static function viewpoints(): iterable
    {
        yield 'a reader counts the revealed events' => ['', [
            ['id' => 'beta', 'shared' => 2, 'first' => 1, 'last' => 3],
            ['id' => 'delta', 'shared' => 1, 'first' => 6, 'last' => 6],
            ['id' => 'gamma', 'shared' => 1, 'first' => 1, 'last' => 1],
        ]];
        yield 'who has read up to chapter 3' => ['?atChapter=3', [
            ['id' => 'beta', 'shared' => 2, 'first' => 1, 'last' => 3],
            ['id' => 'gamma', 'shared' => 1, 'first' => 1, 'last' => 1],
        ]];
        yield 'who is writing chapter 2' => ['?beforeChapter=2', [
            ['id' => 'beta', 'shared' => 1, 'first' => 1, 'last' => 1],
            ['id' => 'gamma', 'shared' => 1, 'first' => 1, 'last' => 1],
        ]];
        yield 'the author also counts the secrets' => ['?includeSecrets=true', [
            ['id' => 'beta', 'shared' => 3, 'first' => 1, 'last' => 5],
            ['id' => 'delta', 'shared' => 2, 'first' => 6, 'last' => 6],
            ['id' => 'gamma', 'shared' => 2, 'first' => 1, 'last' => 1],
        ]];
        yield 'the author up to chapter 3 keeps what is never told' => ['?includeSecrets=true&atChapter=3', [
            ['id' => 'beta', 'shared' => 2, 'first' => 1, 'last' => 3],
            ['id' => 'gamma', 'shared' => 2, 'first' => 1, 'last' => 1],
            ['id' => 'delta', 'shared' => 1, 'first' => null, 'last' => null],
        ]];
    }

    /** @param list<array{id: string, shared: int, first: int|null, last: int|null}> $expected */
    #[DataProvider('viewpoints')]
    public function testRelatedEntriesFollowThePointOfView(string $query, array $expected): void
    {
        self::assertSame($expected, $this->related('alpha', $query));
    }

    public function testTheMostLinkedComeFirstThenByName(): void
    {
        $ids = array_column($this->related('alpha', '?includeSecrets=true'), 'id');

        self::assertSame(['beta', 'delta', 'gamma'], $ids, 'Beta has 3 shared events; Delta and Gamma have 2 each, in alphabetical order.');
    }

    public function testTheLinkGoesBothWaysAndNeverIncludesTheEntryItself(): void
    {
        $fromBeta = array_column($this->related('beta'), 'id');

        self::assertSame(['alpha', 'gamma'], $fromBeta);
        self::assertNotContains('beta', $fromBeta);
    }

    public function testAnEntryWithoutSharedEventsHasNoRelatedEntries(): void
    {
        self::assertSame([], $this->related('loner'));
    }

    public function testEachResultSaysWhereItComesFrom(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/alpha/related?limit=1");

        self::assertSame(
            ['id' => 'beta', 'name' => 'Beta', 'type' => 'character', 'sharedEvents' => 2, 'firstChapter' => 1, 'lastChapter' => 3, 'source' => 'events'],
            $response['data']['data'][0],
        );
    }

    public function testTheTotalCountsEveryLinkedEntryNotOnlyTheReturnedOnes(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/alpha/related?limit=1");

        self::assertCount(1, $response['data']['data']);
        self::assertSame(3, $response['data']['total']);
        self::assertSame(1, $response['data']['limit']);
    }

    public function testAnUnknownEntryIsNotFound(): void
    {
        $this->assertError(404, 'KNOWLEDGE_NOT_FOUND', $this->api('GET', "/api/books/{$this->bookId}/knowledge/nobody/related"));
    }

    public function testTheLimitAndTheViewpointAreChecked(): void
    {
        $base = "/api/books/{$this->bookId}/knowledge/alpha/related";

        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?limit=0"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?limit=51"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?atChapter=1&beforeChapter=2"));
    }

    public function testOnlyTheEntriesOfTheSameBookAreRelated(): void
    {
        $other = $this->createBook('Other');
        $this->import($other, [
            'knowledge' => [['id' => 'alpha', 'type' => 'character', 'name' => 'Other Alpha', 'summary' => 's'], ['id' => 'omega', 'type' => 'character', 'name' => 'Omega', 'summary' => 's']],
            'events' => [['id' => 'e1', 'title' => 'Elsewhere', 'summary' => 's', 'worldOrder' => 1, 'chapter' => 1]],
            'participants' => [['eventId' => 'e1', 'knowledgeId' => 'alpha'], ['eventId' => 'e1', 'knowledgeId' => 'omega']],
        ]);

        $response = $this->api('GET', "/api/books/$other/knowledge/alpha/related");

        self::assertSame(['omega'], array_column($response['data']['data'], 'id'), 'The "alpha" of this book is not the "alpha" of the other one.');
    }

    public function testAReadOnlyKeyCanAskForRelatedEntries(): void
    {
        $key = ['X-API-Key' => $this->keyOf($this->bookId)];
        $this->logout();

        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/alpha/related", null, $key);

        self::assertSame(200, $response['status']);
        self::assertSame(3, $response['data']['total']);
    }
}
