<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The relations of an entry as the AI reads them, from the REST route and from the MCP tools: the state that
 * holds at the chapter reached, the history on request, and the merge with the entries that share events.
 *
 * The story of Aldric: allies with Mira from the start, enemies from chapter 6, no longer linked from chapter 9;
 * mentored by Corvin from chapter 2; friends with Zed from chapter 1 but secretly his enemy from chapter 4; in
 * the service of the citadel from the start. He shares an event with Mira (chapter 1), with Corvin (chapter 5)
 * and with Loner (chapter 3), who has no relation with him.
 */
final class RelationStateTest extends ApiTestCase
{
    private int $bookId;
    private string $token;
    private int $rpcId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();
        $this->import($this->bookId, $this->aldricStory());
        $this->token = $this->keyOf($this->bookId);
    }

    /**
     * @return list<string> "mira:enemy:6" (the other entry, the type, since which chapter)
     */
    private function relations(string $query = ''): array
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations$query");
        self::assertSame(200, $response['status'], json_encode($response['data']));

        return array_map(static fn (array $r) => $r['with']['id'].':'.$r['type'].':'.($r['since'] ?? 'start'), $response['data']['data']);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function viewpoints(): iterable
    {
        yield 'a reader who has read everything: Mira is no longer linked' => ['', ['corvin:mentor:2', 'zed:friend:1', 'citadel:serves:start']];
        yield 'at chapter 5: still allies' => ['?atChapter=5', ['corvin:mentor:2', 'zed:friend:1', 'mira:ally:start', 'citadel:serves:start']];
        yield 'at chapter 6: they became enemies' => ['?atChapter=6', ['mira:enemy:6', 'corvin:mentor:2', 'zed:friend:1', 'citadel:serves:start']];
        yield 'writing chapter 6: the change has not happened yet' => ['?beforeChapter=6', ['corvin:mentor:2', 'zed:friend:1', 'mira:ally:start', 'citadel:serves:start']];
        yield 'at chapter 9: Mira is gone again' => ['?atChapter=9', ['corvin:mentor:2', 'zed:friend:1', 'citadel:serves:start']];
        yield 'before chapter 1: only what holds from the start' => ['?beforeChapter=1', ['mira:ally:start', 'citadel:serves:start']];
        yield 'the author at chapter 5 sees the secret' => ['?includeSecrets=true&atChapter=5', ['zed:enemy:4', 'corvin:mentor:2', 'mira:ally:start', 'citadel:serves:start']];
        yield 'the author, whole story' => ['?includeSecrets=true', ['zed:enemy:4', 'corvin:mentor:2', 'citadel:serves:start']];
    }

    /** @param list<string> $expected */
    #[DataProvider('viewpoints')]
    public function testTheRelationsFollowThePointOfView(string $query, array $expected): void
    {
        self::assertSame($expected, $this->relations($query));
    }

    public function testEachRelationIsWrittenFromTheSideOfTheEntry(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations?atChapter=7");
        $byPartner = array_column($response['data']['data'], null, 'with.id');
        $items = [];
        foreach ($response['data']['data'] as $item) {
            $items[$item['with']['id']] = $item;
        }

        self::assertSame('Aldric and Mira are enemies.', $items['mira']['statement']);
        self::assertSame('mutual', $items['mira']['direction']);
        self::assertSame('The betrayal.', $items['mira']['note']);
        self::assertSame(['id' => 'mira', 'name' => 'Mira', 'type' => 'character'], $items['mira']['with']);

        self::assertSame('Corvin is the mentor of Aldric.', $items['corvin']['statement']);
        self::assertSame('incoming', $items['corvin']['direction'], 'Corvin is the mentor: the relation comes in to Aldric.');

        self::assertSame('Aldric serves North Citadel.', $items['citadel']['statement']);
        self::assertSame('outgoing', $items['citadel']['direction']);
        self::assertSame('place', $items['citadel']['with']['type']);
        unset($byPartner);
    }

    public function testFromTheOtherSideTheStateIsTheSame(): void
    {
        $fromMira = $this->api('GET', "/api/books/{$this->bookId}/knowledge/mira/relations?atChapter=7")['data']['data'];

        self::assertCount(1, $fromMira);
        self::assertSame('aldric', $fromMira[0]['with']['id']);
        self::assertSame('Aldric and Mira are enemies.', $fromMira[0]['statement']);
    }

    public function testTheHistoryListsEveryVisibleStateAndKeepsTheEndedPairs(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations?withHistory=true");

        self::assertSame(4, $response['data']['total'], 'Mira is listed too, marked as ended.');
        $mira = array_values(array_filter($response['data']['data'], static fn (array $r) => 'mira' === $r['with']['id']))[0];
        self::assertTrue($mira['ended']);
        self::assertSame(
            [[null, 'ally'], [6, 'enemy'], [9, 'none']],
            array_map(static fn (array $s) => [$s['chapter'], $s['type']], $mira['history']),
        );
        self::assertSame('The betrayal.', $mira['history'][1]['note']);
    }

    public function testTheHistoryDoesNotRevealWhatTheReaderHasNotReached(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations?withHistory=true&atChapter=7");
        $mira = array_values(array_filter($response['data']['data'], static fn (array $r) => 'mira' === $r['with']['id']))[0];

        self::assertSame([null, 6], array_column($mira['history'], 'chapter'));
        self::assertArrayNotHasKey('ended', $mira);

        $zed = array_values(array_filter($response['data']['data'], static fn (array $r) => 'zed' === $r['with']['id']))[0];
        self::assertSame([1], array_column($zed['history'], 'chapter'), 'The secret enmity is not in the reader\'s history.');
    }

    public function testASecretStateIsMarkedForTheAuthor(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations?includeSecrets=true&withHistory=true&atChapter=5");
        $zed = array_values(array_filter($response['data']['data'], static fn (array $r) => 'zed' === $r['with']['id']))[0];

        self::assertTrue($zed['secret']);
        self::assertSame([1, 4], array_column($zed['history'], 'chapter'));
        self::assertTrue($zed['history'][1]['secret']);
        self::assertArrayNotHasKey('secret', $zed['history'][0]);
    }

    public function testTheListIsPaginated(): void
    {
        $page = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations?limit=1&offset=1");

        self::assertCount(1, $page['data']['data']);
        self::assertSame('zed', $page['data']['data'][0]['with']['id']);
        self::assertSame(3, $page['data']['total']);
        self::assertSame(1, $page['data']['offset']);
    }

    public function testTheParametersAreChecked(): void
    {
        $base = "/api/books/{$this->bookId}/knowledge/aldric/relations";

        $this->assertError(404, 'KNOWLEDGE_NOT_FOUND', $this->api('GET', "/api/books/{$this->bookId}/knowledge/nobody/relations"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?limit=0"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?offset=-1"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?withHistory=maybe"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?atChapter=1&beforeChapter=2"));
    }

    public function testAnEntryWithoutRelationsHasAnEmptyList(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/loner/relations");

        self::assertSame([], $response['data']['data']);
        self::assertSame(0, $response['data']['total']);
    }

    // ---- Related entries: relations and shared events together ---------------------------------

    /** @return list<string> "corvin:both" (the entry, where it comes from) */
    private function related(string $query = ''): array
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/related$query");
        self::assertSame(200, $response['status'], json_encode($response['data']));

        return array_map(static fn (array $r) => $r['id'].':'.$r['source'], $response['data']['data']);
    }

    public function testRelatedEntriesListTheRelationsFirstThenTheSharedEvents(): void
    {
        self::assertSame(
            ['corvin:both', 'zed:relation', 'citadel:relation', 'loner:events', 'mira:events'],
            $this->related(),
            'Mira is no longer linked by a relation (it ended) but still shares an event; Loner only shares an event.',
        );
    }

    public function testAnEntryInBothListsAppearsOnceWithBothPiecesOfInformation(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/related?atChapter=7");
        $mira = array_values(array_filter($response['data']['data'], static fn (array $r) => 'mira' === $r['id']))[0];

        self::assertSame('both', $mira['source']);
        self::assertSame('enemy', $mira['relation']['type']);
        self::assertSame('Aldric and Mira are enemies.', $mira['relation']['statement']);
        self::assertSame(6, $mira['relation']['since']);
        self::assertSame(1, $mira['sharedEvents']);
        self::assertSame([1, 1], [$mira['firstChapter'], $mira['lastChapter']]);

        $ids = array_column($response['data']['data'], 'id');
        self::assertSame($ids, array_values(array_unique($ids)), 'No entry appears twice.');
    }

    public function testAnEntryWithOnlyARelationHasNoSharedEvents(): void
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/related");
        $citadel = array_values(array_filter($response['data']['data'], static fn (array $r) => 'citadel' === $r['id']))[0];

        self::assertSame('relation', $citadel['source']);
        self::assertSame(0, $citadel['sharedEvents']);
        self::assertNull($citadel['firstChapter']);
        self::assertSame('place', $citadel['type']);
    }

    public function testTheRelatedTotalCountsEachEntryOnce(): void
    {
        $all = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/related");
        $short = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/related?limit=2");

        self::assertSame(5, $all['data']['total']);
        self::assertSame(5, $short['data']['total'], 'The total does not depend on the limit.');
        self::assertSame(['corvin', 'zed'], array_column($short['data']['data'], 'id'));
    }

    public function testTheRelatedEntriesFollowThePointOfViewToo(): void
    {
        self::assertSame(['mira:both', 'corvin:both', 'zed:relation', 'citadel:relation', 'loner:events'], $this->related('?atChapter=6'));
        self::assertSame(['zed:relation', 'mira:both', 'citadel:relation'], $this->related('?beforeChapter=2'), 'The friendship with Zed (chapter 1) is the latest change; the event shared with Loner is in chapter 3.');
    }

    // ---- The tools of the AI -------------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     *
     * @return array{status: int, body: mixed, session: ?string}
     */
    private function rpc(string $method, array $params = [], ?string $session = null): array
    {
        $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];
        if (!str_starts_with($method, 'notifications/')) {
            $message['id'] = ++$this->rpcId;
        }
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if (null !== $session) {
            $headers['HTTP_MCP_SESSION_ID'] = $session;
        }

        $this->logout();
        $this->client->request('POST', '/mcp/'.$this->token, [], [], $headers, json_encode($message, \JSON_THROW_ON_ERROR));
        $response = $this->client->getResponse();
        $text = (string) $response->getContent();

        return ['status' => $response->getStatusCode(), 'body' => '' === $text ? null : json_decode($text, true), 'session' => $response->headers->get('Mcp-Session-Id')];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{error: bool, result: mixed}
     */
    private function tool(string $name, array $arguments = []): array
    {
        static $sessions = [];
        $key = spl_object_id($this);
        if (!isset($sessions[$key])) {
            $init = $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']]);
            $this->rpc('notifications/initialized', [], $init['session']);
            $sessions[$key] = $init['session'];
        }

        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $sessions[$key]);
        $text = $response['body']['result']['content'][0]['text'] ?? '';

        return ['error' => (bool) ($response['body']['result']['isError'] ?? false), 'result' => json_validate($text) ? json_decode($text, true) : $text];
    }

    public function testGetKnowledgeComesWithItsRelationsAtTheChapterReached(): void
    {
        $sheet = $this->tool('get_knowledge', ['id' => 'aldric', 'atChapter' => 7])['result'];

        self::assertSame(4, $sheet['relations']['total']);
        self::assertSame(
            ['mira:enemy', 'corvin:mentor', 'zed:friend', 'citadel:serves'],
            array_map(static fn (array $r) => $r['with']['id'].':'.$r['type'], $sheet['relations']['data']),
        );
        self::assertArrayNotHasKey('history', $sheet['relations']['data'][0], 'The history is a separate request: the sheet stays short.');
        self::assertArrayHasKey('events', $sheet, 'The events are still there.');
    }

    public function testGetKnowledgeHidesWhatTheReaderMustNotKnow(): void
    {
        $reader = $this->tool('get_knowledge', ['id' => 'aldric', 'atChapter' => 5])['result'];
        self::assertContains('friend', array_column($reader['relations']['data'], 'type'));
        self::assertNotContains('enemy', array_column($reader['relations']['data'], 'type'));

        $author = $this->tool('get_knowledge', ['id' => 'aldric', 'atChapter' => 5, 'includeSecrets' => true])['result'];
        self::assertContains('enemy', array_column($author['relations']['data'], 'type'), 'The author sees that Zed is secretly an enemy.');
    }

    public function testGetRelationsGivesTheHistoryOnRequest(): void
    {
        $plain = $this->tool('get_relations', ['id' => 'aldric']);
        self::assertFalse($plain['error']);
        self::assertSame(3, $plain['result']['total']);

        $history = $this->tool('get_relations', ['id' => 'aldric', 'withHistory' => true]);
        self::assertSame(4, $history['result']['total']);
        $mira = array_values(array_filter($history['result']['data'], static fn (array $r) => 'mira' === $r['with']['id']))[0];
        self::assertSame([null, 6, 9], array_column($mira['history'], 'chapter'));
    }

    public function testGetRelationsIsPagedAndChecked(): void
    {
        $page = $this->tool('get_relations', ['id' => 'aldric', 'limit' => 1, 'offset' => 2])['result'];
        self::assertSame(['citadel'], array_column(array_column($page['data'], 'with'), 'id'));
        self::assertSame(3, $page['total']);

        foreach ([['id' => 'nobody'], ['id' => 'aldric', 'limit' => 0], ['id' => 'aldric', 'limit' => 201], ['id' => 'aldric', 'atChapter' => 1, 'beforeChapter' => 2], ['id' => 'aldric', 'offset' => -1]] as $arguments) {
            $result = $this->tool('get_relations', $arguments);
            self::assertTrue($result['error'], json_encode($arguments).' must fail as a tool error');
            self::assertIsString($result['result']);
        }
    }

    public function testGetRelatedMergesRelationsAndSharedEventsForTheAi(): void
    {
        $related = $this->tool('get_related', ['id' => 'aldric', 'limit' => 20])['result'];

        self::assertSame(['corvin', 'zed', 'citadel', 'loner', 'mira'], array_column($related['data'], 'id'));
        self::assertSame(5, $related['total']);
        self::assertSame('both', $related['data'][0]['source']);
        self::assertSame('Corvin is the mentor of Aldric.', $related['data'][0]['relation']['statement']);
    }
}
