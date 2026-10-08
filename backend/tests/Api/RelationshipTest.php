<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The relationship of two entries: how they stand with each other at the chapter reached, how it evolved, and
 * the events they share. See ApiTestCase::aldricStory() for the story.
 */
final class RelationshipTest extends ApiTestCase
{
    private int $bookId;
    private string $token;
    private int $rpcId = 0;
    private ?string $session = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();
        $this->import($this->bookId, $this->aldricStory());
        $this->token = $this->keyOf($this->bookId);
    }

    /** @return array<string, mixed> */
    private function between(string $first, string $second, string $query = ''): array
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/$first/relations/$second$query");
        self::assertSame(200, $response['status'], json_encode($response['data']));

        return $response['data'];
    }

    /** @return iterable<string, array{string, array{string, ?string, ?int}}> the query, then [status, type, since] */
    public static function miraAndAldric(): iterable
    {
        yield 'a reader who has read everything: they are no longer linked' => ['', ['ended', null, null]];
        yield 'at chapter 5: allies' => ['?atChapter=5', ['linked', 'ally', null]];
        yield 'at chapter 6: enemies' => ['?atChapter=6', ['linked', 'enemy', 6]];
        yield 'writing chapter 6: still allies' => ['?beforeChapter=6', ['linked', 'ally', null]];
        yield 'at chapter 9: the relation has ended' => ['?atChapter=9', ['ended', null, null]];
    }

    /** @param array{string, ?string, ?int} $expected */
    #[DataProvider('miraAndAldric')]
    public function testTheStatusOfAPairFollowsThePointOfView(string $query, array $expected): void
    {
        $pair = $this->between('aldric', 'mira', $query);

        self::assertSame($expected[0], $pair['status']);
        self::assertSame($expected[1], $pair['relation']['type'] ?? null);
        self::assertSame($expected[2], $pair['relation']['since'] ?? null);
    }

    public function testTheAnswerNamesBothEntriesAndTheHistory(): void
    {
        $pair = $this->between('aldric', 'mira', '?atChapter=7');

        self::assertSame(
            [['id' => 'aldric', 'name' => 'Aldric', 'type' => 'character'], ['id' => 'mira', 'name' => 'Mira', 'type' => 'character']],
            $pair['entries'],
        );
        self::assertSame('Aldric and Mira are enemies.', $pair['relation']['statement']);
        self::assertSame('The betrayal.', $pair['relation']['note']);
        self::assertSame([null, 6], array_column($pair['history'], 'chapter'), 'The end at chapter 9 is not known yet.');
        self::assertSame(['ally', 'enemy'], array_column($pair['history'], 'type'));
    }

    public function testAnEndedRelationKeepsItsWholeHistory(): void
    {
        $pair = $this->between('aldric', 'mira');

        self::assertSame('ended', $pair['status']);
        self::assertNull($pair['relation']);
        self::assertSame([null, 6, 9], array_column($pair['history'], 'chapter'));
        self::assertSame(['ally', 'enemy', 'none'], array_column($pair['history'], 'type'));
        self::assertSame('Mira and Aldric are no longer linked.', $pair['history'][2]['statement']);
    }

    public function testTheOrderOfTheTwoEntriesOnlyChangesTheDirection(): void
    {
        $aldricFirst = $this->between('aldric', 'corvin', '?atChapter=5');
        $corvinFirst = $this->between('corvin', 'aldric', '?atChapter=5');

        self::assertSame('Corvin is the mentor of Aldric.', $aldricFirst['relation']['statement']);
        self::assertSame($aldricFirst['relation']['statement'], $corvinFirst['relation']['statement']);
        self::assertSame('incoming', $aldricFirst['relation']['direction'], 'Seen from Aldric, the mentorship comes in.');
        self::assertSame('outgoing', $corvinFirst['relation']['direction']);
        self::assertSame(['corvin', 'aldric'], array_column($corvinFirst['entries'], 'id'), 'The entries come in the order they were asked.');
        self::assertSame($aldricFirst['status'], $corvinFirst['status']);
    }

    public function testASecretOnlyShowsToTheAuthor(): void
    {
        $reader = $this->between('aldric', 'zed', '?atChapter=5');
        self::assertSame('friend', $reader['relation']['type']);
        self::assertSame([1], array_column($reader['history'], 'chapter'));

        $author = $this->between('aldric', 'zed', '?atChapter=5&includeSecrets=true');
        self::assertSame('enemy', $author['relation']['type']);
        self::assertTrue($author['relation']['secret']);
        self::assertSame([1, 4], array_column($author['history'], 'chapter'));
    }

    public function testTwoEntriesWithNoRelationMayStillShareEvents(): void
    {
        $pair = $this->between('aldric', 'loner');

        self::assertSame('none', $pair['status']);
        self::assertNull($pair['relation']);
        self::assertSame([], $pair['history']);
        self::assertSame(1, $pair['sharedEvents']['total']);
        self::assertSame('e2', $pair['sharedEvents']['data'][0]['id']);
    }

    public function testTwoEntriesWithNothingInCommon(): void
    {
        $pair = $this->between('loner', 'zed');

        self::assertSame('none', $pair['status']);
        self::assertSame(0, $pair['sharedEvents']['total']);
        self::assertSame([], $pair['sharedEvents']['data']);
    }

    public function testTheSharedEventsFollowThePointOfViewAndCarryBothRoles(): void
    {
        $this->api('PATCH', "/api/books/{$this->bookId}/event-participants/e1/aldric", ['role' => 'hero']);
        $this->api('PATCH', "/api/books/{$this->bookId}/event-participants/e1/mira", ['role' => 'witness']);

        $pair = $this->between('aldric', 'mira');

        self::assertSame(1, $pair['sharedEvents']['total']);
        self::assertSame(
            ['id' => 'e1', 'title' => 'One', 'chapter' => 1, 'worldOrder' => 1, 'worldDate' => null, 'roles' => ['first' => 'hero', 'second' => 'witness']],
            $pair['sharedEvents']['data'][0],
        );
        self::assertSame(0, $this->between('aldric', 'corvin', '?atChapter=4')['sharedEvents']['total'], 'The event shared with Corvin is told in chapter 5.');
        self::assertSame(1, $this->between('aldric', 'corvin', '?atChapter=5')['sharedEvents']['total']);
    }

    public function testTheSharedEventsAreLimited(): void
    {
        $events = array_map(static fn (int $i) => ['id' => "x$i", 'title' => "X $i", 'summary' => 's', 'worldOrder' => 10 + $i, 'chapter' => 1], range(1, 5));
        $links = array_merge(...array_map(static fn (int $i) => [['eventId' => "x$i", 'knowledgeId' => 'aldric'], ['eventId' => "x$i", 'knowledgeId' => 'zed']], range(1, 5)));
        $this->import($this->bookId, ['events' => $events, 'participants' => $links]);

        $pair = $this->between('aldric', 'zed', '?eventsLimit=2');

        self::assertSame(5, $pair['sharedEvents']['total']);
        self::assertSame(['x1', 'x2'], array_column($pair['sharedEvents']['data'], 'id'), 'In the order of the world.');
    }

    public function testTheRequestIsChecked(): void
    {
        $base = "/api/books/{$this->bookId}/knowledge";

        $this->assertError(404, 'KNOWLEDGE_NOT_FOUND', $this->api('GET', "$base/aldric/relations/nobody"));
        $this->assertError(404, 'KNOWLEDGE_NOT_FOUND', $this->api('GET', "$base/nobody/relations/aldric"));
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('GET', "$base/aldric/relations/aldric"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base/aldric/relations/mira?eventsLimit=0"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base/aldric/relations/mira?eventsLimit=51"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base/aldric/relations/mira?atChapter=1&beforeChapter=2"));
    }

    public function testAnEntryOfAnotherBookIsNotFound(): void
    {
        $other = $this->createBook('Other');
        $this->import($other, ['knowledge' => [['id' => 'stranger', 'type' => 'character', 'name' => 'Stranger', 'summary' => 's']]]);

        $this->assertError(404, 'KNOWLEDGE_NOT_FOUND', $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations/stranger"));
    }

    public function testAReadOnlyKeyCanLookItUp(): void
    {
        $key = ['X-API-Key' => $this->token];
        $this->logout();

        $response = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric/relations/mira?atChapter=5", null, $key);

        self::assertSame(200, $response['status']);
        self::assertSame('ally', $response['data']['relation']['type']);
    }

    // ---- The tool of the AI --------------------------------------------------------------------

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{error: bool, result: mixed}
     */
    private function tool(string $name, array $arguments = []): array
    {
        $this->logout();
        if (null === $this->session) {
            $init = $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']]);
            $this->session = $init['session'];
            $this->rpc('notifications/initialized', [], $this->session);
        }

        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $this->session);
        $text = $response['body']['result']['content'][0]['text'] ?? '';

        return ['error' => (bool) ($response['body']['result']['isError'] ?? false), 'result' => json_validate($text) ? json_decode($text, true) : $text];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{body: mixed, session: ?string}
     */
    private function rpc(string $method, array $params, ?string $session = null): array
    {
        $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];
        if (!str_starts_with($method, 'notifications/')) {
            $message['id'] = ++$this->rpcId;
        }
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if (null !== $session) {
            $headers['HTTP_MCP_SESSION_ID'] = $session;
        }

        $this->client->request('POST', '/mcp/'.$this->token, [], [], $headers, json_encode($message, \JSON_THROW_ON_ERROR));
        $text = (string) $this->client->getResponse()->getContent();

        return ['body' => '' === $text ? null : json_decode($text, true), 'session' => $this->client->getResponse()->headers->get('Mcp-Session-Id')];
    }

    public function testTheToolAnswersLikeTheRoute(): void
    {
        $answer = $this->tool('get_relationship', ['id' => 'aldric', 'otherId' => 'mira', 'atChapter' => 7]);

        self::assertFalse($answer['error']);
        self::assertSame('linked', $answer['result']['status']);
        self::assertSame('Aldric and Mira are enemies.', $answer['result']['relation']['statement']);
        self::assertSame([null, 6], array_column($answer['result']['history'], 'chapter'));
        self::assertSame(1, $answer['result']['sharedEvents']['total']);
    }

    public function testTheToolIsAppliedThePointOfViewToo(): void
    {
        $reader = $this->tool('get_relationship', ['id' => 'aldric', 'otherId' => 'zed', 'atChapter' => 5])['result'];
        $author = $this->tool('get_relationship', ['id' => 'aldric', 'otherId' => 'zed', 'atChapter' => 5, 'includeSecrets' => true])['result'];

        self::assertSame('friend', $reader['relation']['type']);
        self::assertSame('enemy', $author['relation']['type']);
    }

    public function testTheToolReportsMistakesInReadableWords(): void
    {
        foreach ([
            ['id' => 'aldric', 'otherId' => 'nobody'],
            ['id' => 'aldric', 'otherId' => 'aldric'],
            ['id' => 'aldric', 'otherId' => 'mira', 'eventsLimit' => 0],
            ['id' => 'aldric', 'otherId' => 'mira', 'atChapter' => 1, 'beforeChapter' => 2],
        ] as $arguments) {
            $answer = $this->tool('get_relationship', $arguments);
            self::assertTrue($answer['error'], json_encode($arguments).' must fail as a tool error');
            self::assertIsString($answer['result']);
        }
    }

    public function testTheToolIsListedWithItsDescription(): void
    {
        $this->logout();
        $init = $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']]);
        $tools = array_column($this->rpc('tools/list', [], $init['session'])['body']['result']['tools'], null, 'name');

        self::assertArrayHasKey('get_relationship', $tools);
        self::assertStringContainsString('TWO entries', $tools['get_relationship']['description']);
        self::assertEqualsCanonicalizing(['id', 'otherId'], $tools['get_relationship']['inputSchema']['required']);
    }
}
