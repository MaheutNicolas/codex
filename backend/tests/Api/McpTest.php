<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

/** The MCP server an AI connects to: protocol handshake, the read-only tools, and the access rules. */
final class McpTest extends ApiTestCase
{
    private int $bookId;
    private string $token;
    private int $rpcId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook('The Book');
        $this->import($this->bookId, $this->story());
        $this->token = $this->keyOf($this->bookId);
        $this->logout();
    }

    /**
     * One JSON-RPC message to the server, as an MCP client sends it.
     *
     * @param array<string, mixed>  $params
     * @param array<string, string> $server extra server parameters (HTTP_HOST, HTTP_ORIGIN...)
     *
     * @return array{status: int, body: mixed, session: ?string}
     */
    private function rpc(string $method, array $params = [], ?string $session = null, ?string $token = null, array $server = []): array
    {
        $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];
        if (!str_starts_with($method, 'notifications/')) {
            $message['id'] = ++$this->rpcId;
        }

        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'] + $server;
        if (null !== $session) {
            $headers['HTTP_MCP_SESSION_ID'] = $session;
        }

        $this->client->request('POST', '/mcp/'.($token ?? $this->token), [], [], $headers, json_encode($message, \JSON_THROW_ON_ERROR));
        $response = $this->client->getResponse();
        $text = (string) $response->getContent();

        return [
            'status' => $response->getStatusCode(),
            'body' => '' === $text ? null : json_decode($text, true),
            'session' => $response->headers->get('Mcp-Session-Id'),
        ];
    }

    private function openSession(): string
    {
        $init = $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']]);
        self::assertSame(200, $init['status']);
        self::assertNotEmpty($init['session']);
        $this->rpc('notifications/initialized', [], $init['session']);

        return $init['session'];
    }

    /**
     * Calls a tool. Returns whether it failed and what it answered (decoded when it is JSON).
     *
     * @param array<string, mixed> $arguments
     *
     * @return array{error: bool, result: mixed}
     */
    private function tool(string $session, string $name, array $arguments = []): array
    {
        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $session);
        self::assertSame(200, $response['status']);
        $result = $response['body']['result'];
        $text = $result['content'][0]['text'] ?? '';

        return ['error' => (bool) ($result['isError'] ?? false), 'result' => json_validate($text) ? json_decode($text, true) : $text];
    }

    // ---- Protocol ----------------------------------------------------------------------------------

    public function testTheHandshakeAnnouncesTheServerAndOpensASession(): void
    {
        $init = $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']]);

        self::assertSame(200, $init['status']);
        self::assertSame('Codex', $init['body']['result']['serverInfo']['name']);
        self::assertArrayHasKey('tools', $init['body']['result']['capabilities']);
        self::assertStringContainsString('lastChapter', $init['body']['result']['instructions']);
        self::assertNotEmpty($init['session']);
    }

    public function testOlderProtocolVersionsAreAccepted(): void
    {
        $init = $this->rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']]);

        self::assertSame(200, $init['status']);
        self::assertSame('2025-03-26', $init['body']['result']['protocolVersion']);
    }

    public function testTheServerOffersFiveReadOnlyTools(): void
    {
        $session = $this->openSession();

        $tools = $this->rpc('tools/list', [], $session)['body']['result']['tools'];
        $names = array_column($tools, 'name');
        sort($names);

        self::assertSame(['get_event', 'get_knowledge', 'index', 'search', 'timeline'], $names, 'Reading only: no tool writes anything.');
        foreach ($tools as $tool) {
            self::assertNotEmpty($tool['description'], $tool['name'].' needs a description: it is what the AI reads to choose a tool.');
            foreach ($tool['inputSchema']['properties'] ?? [] as $property => $schema) {
                self::assertNotEmpty($schema['description'] ?? null, "{$tool['name']}.$property needs a description.");
            }
        }
    }

    public function testGetKnowledgeCanBePaged(): void
    {
        $session = $this->openSession();
        $tools = $this->rpc('tools/list', [], $session)['body']['result']['tools'];
        $properties = array_column($tools, null, 'name')['get_knowledge']['inputSchema']['properties'];

        self::assertArrayHasKey('limit', $properties);
        self::assertArrayHasKey('offset', $properties);
        self::assertSame(30, $properties['limit']['default']);
    }

    // ---- Tools -------------------------------------------------------------------------------------

    public function testIndexListsTheEntriesAndTellsHowFarTheStoryIsWritten(): void
    {
        $session = $this->openSession();

        $index = $this->tool($session, 'index');

        self::assertFalse($index['error']);
        self::assertSame(['title' => 'The Book', 'lastChapter' => 7], $index['result']['book']);
        self::assertSame(3, $index['result']['total']);
        self::assertSame(['id', 'name', 'type', 'aliases'], array_keys($index['result']['data'][0]));
    }

    public function testIndexCanBeFilteredByTypeAndRefusesAnUnknownOne(): void
    {
        $session = $this->openSession();

        self::assertSame(1, $this->tool($session, 'index', ['type' => 'place'])['result']['total']);

        $bad = $this->tool($session, 'index', ['type' => 'monster']);
        self::assertTrue($bad['error']);
        self::assertStringContainsString('theme', $bad['result'], 'The error lists the possible types.');
    }

    public function testGetKnowledgeAnswersShortly(): void
    {
        $session = $this->openSession();

        $sheet = $this->tool($session, 'get_knowledge', ['id' => 'aldric', 'includeSecrets' => true])['result'];

        self::assertSame('Aldric swore an oath to protect the citadel.', $sheet['description']);
        self::assertSame(['evt-1', 'evt-3', 'evt-4'], array_column($sheet['events']['data'], 'id'));
        self::assertSame(30, $sheet['events']['limit']);
        self::assertSame(3, $sheet['events']['total']);
        self::assertArrayNotHasKey('participants', $sheet['events']['data'][0], 'The other participants are not repeated for every event.');
        self::assertSame('author', $sheet['events']['data'][0]['role']);
        self::assertArrayNotHasKey('createdAt', $sheet);
        self::assertArrayNotHasKey('updatedAt', $sheet);
    }

    public function testGetKnowledgeReadsTheNextEventsWithAnOffset(): void
    {
        $session = $this->openSession();

        $page = $this->tool($session, 'get_knowledge', ['id' => 'aldric', 'includeSecrets' => true, 'limit' => 1, 'offset' => 1])['result'];

        self::assertSame(['evt-3'], array_column($page['events']['data'], 'id'));
        self::assertSame(3, $page['events']['total']);
    }

    public function testToolsFollowThePointOfViewOfTheReader(): void
    {
        $session = $this->openSession();

        $before = $this->tool($session, 'timeline', ['beforeChapter' => 3])['result'];
        self::assertSame(['evt-1'], array_column($before['data'], 'id'));

        $author = $this->tool($session, 'timeline', ['includeSecrets' => true])['result'];
        self::assertSame(5, $author['total']);

        $sheet = $this->tool($session, 'get_knowledge', ['id' => 'aldric', 'atChapter' => 1])['result'];
        self::assertSame(['evt-1'], array_column($sheet['events']['data'], 'id'));
    }

    public function testGetEventHidesWhatTheReaderMustNotSee(): void
    {
        $session = $this->openSession();

        $secret = $this->tool($session, 'get_event', ['id' => 'evt-4']);
        self::assertTrue($secret['error']);
        self::assertStringContainsString('not found', $secret['result']);

        $later = $this->tool($session, 'get_event', ['id' => 'evt-3', 'atChapter' => 3]);
        self::assertTrue($later['error']);

        $visible = $this->tool($session, 'get_event', ['id' => 'evt-4', 'includeSecrets' => true]);
        self::assertFalse($visible['error']);
        self::assertSame('The hidden twist', $visible['result']['title']);
        self::assertCount(1, $visible['result']['participants']);
        self::assertArrayNotHasKey('createdAt', $visible['result']);
    }

    public function testSearchTellsHowManyMatchesThereAre(): void
    {
        $session = $this->openSession();
        $heroes = array_map(static fn (int $i) => ['id' => "hero-$i", 'type' => 'character', 'name' => "Hero $i", 'summary' => "The hero number $i."], range(1, 12));
        $this->loginAsOwner();
        $this->import($this->bookId, ['knowledge' => $heroes]);
        $this->logout();

        $search = $this->tool($session, 'search', ['query' => 'hero', 'limit' => 5])['result'];

        self::assertCount(5, $search['data']);
        self::assertSame(12, $search['total']);
    }

    public function testToolErrorsAreReadableAndNotProtocolErrors(): void
    {
        $session = $this->openSession();

        foreach ([
            ['get_knowledge', ['id' => 'nobody']],
            ['get_event', ['id' => 'evt-999']],
            ['timeline', ['atChapter' => 1, 'beforeChapter' => 2]],
            ['timeline', ['atChapter' => 0]],
            ['timeline', ['limit' => 0]],
            ['get_knowledge', ['id' => 'aldric', 'limit' => 201]],
            ['search', ['query' => '%%']],
            ['search', ['query' => 'oath', 'limit' => 51]],
        ] as [$name, $arguments]) {
            $result = $this->tool($session, $name, $arguments);
            self::assertTrue($result['error'], "$name ".json_encode($arguments).' must fail as a tool error');
            self::assertIsString($result['result']);
            self::assertNotSame('', $result['result']);
        }
    }

    // ---- Access ------------------------------------------------------------------------------------

    public function testUnknownKeyIsRefused(): void
    {
        $unknown = $this->rpc('initialize', [], null, 'cdx_'.str_repeat('0', 40));

        self::assertSame(401, $unknown['status']);
        self::assertSame('UNAUTHORIZED', $unknown['body']['error']['code']);
    }

    public function testAMalformedKeyDoesNotEvenMatchARoute(): void
    {
        self::assertSame(404, $this->rpc('initialize', [], null, 'abc')['status']);
        self::assertSame(404, $this->rpc('initialize', [], null, 'cdx_ZZZ')['status']);
    }

    public function testRegeneratingTheKeyChangesTheAddress(): void
    {
        $old = $this->token;
        $this->loginAsOwner();
        $this->api('POST', "/api/books/{$this->bookId}/api-key/regenerate");
        $this->logout();

        self::assertSame(401, $this->rpc('initialize', [], null, $old)['status']);
    }

    public function testOnlyTheHostsOfTheServerAreServed(): void
    {
        $init = ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']];

        self::assertSame(200, $this->rpc('initialize', $init, null, null, ['HTTP_HOST' => 'localhost'])['status']);
        self::assertSame(403, $this->rpc('initialize', $init, null, null, ['HTTP_HOST' => 'evil.example'])['status']);
    }

    public function testAnAiClientThatSendsAnOriginIsNotRefused(): void
    {
        $init = ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']];

        $response = $this->rpc('initialize', $init, null, null, ['HTTP_ORIGIN' => 'https://chatgpt.com']);

        self::assertSame(200, $response['status'], 'Each request is authenticated by the key, so an Origin header is no reason to refuse it.');
    }

    public function testAPlainGetIsNotAllowedWithoutASession(): void
    {
        $this->client->request('GET', '/mcp/'.$this->token);

        self::assertSame(405, $this->client->getResponse()->getStatusCode());
    }

    public function testTheKeyOfAnotherBookGivesAnotherBook(): void
    {
        $this->loginAsNewUser();
        $other = $this->createBook('Empty book');
        $otherToken = $this->keyOf($other);
        $this->logout();

        $session = $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'phpunit', 'version' => '1']], null, $otherToken)['session'];
        $index = $this->tool2($session, $otherToken, 'index');

        self::assertSame('Empty book', $index['book']['title']);
        self::assertSame(0, $index['total']);
    }

    private function tool2(string $session, string $token, string $name): array
    {
        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => new \stdClass()], $session, $token);

        return json_decode($response['body']['result']['content'][0]['text'], true);
    }

    /** Logs in as the account that owns the book of these tests. */
    private function loginAsOwner(): void
    {
        $owner = $this->connection()->fetchOne('SELECT u.username FROM user u JOIN book b ON b.user_id = u.id WHERE b.id = ?', [$this->bookId]);
        $this->api('POST', '/api/auth/login', ['username' => $owner, 'password' => self::PASSWORD]);
    }
}
