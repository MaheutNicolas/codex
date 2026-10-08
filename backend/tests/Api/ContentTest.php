<?php

namespace App\Tests\Api;

use App\Entity\Knowledge;
use App\Tests\ApiTestCase;

/** The CRUD of entries, events and participant links, with their validation. */
final class ContentTest extends ApiTestCase
{
    private int $bookId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();
    }

    private function entry(string $id = 'aldric', string $type = 'character'): array
    {
        return ['id' => $id, 'type' => $type, 'name' => ucfirst($id), 'summary' => 'A summary.', 'description' => 'A long description.', 'aliases' => ['a1']];
    }

    // ---- Entries -----------------------------------------------------------------------------------

    public function testAnEntryIsCreatedReadUpdatedAndDeleted(): void
    {
        $b = $this->bookId;

        $created = $this->api('POST', "/api/books/$b/knowledge", $this->entry());
        self::assertSame(201, $created['status']);
        self::assertSame("/api/books/$b/knowledge/aldric", $created['headers']['location'][0]);
        self::assertSame('aldric', $created['data']['id']);

        $read = $this->api('GET', "/api/books/$b/knowledge/aldric");
        self::assertSame('A long description.', $read['data']['description']);
        self::assertSame(['a1'], $read['data']['aliases']);

        $updated = $this->api('PATCH', "/api/books/$b/knowledge/aldric", ['summary' => 'New summary.', 'aliases' => []]);
        self::assertSame(200, $updated['status']);
        self::assertSame('New summary.', $updated['data']['summary']);
        self::assertSame('Aldric', $updated['data']['name'], 'A partial update leaves the other fields alone.');
        self::assertSame([], $updated['data']['aliases']);

        self::assertSame(204, $this->api('DELETE', "/api/books/$b/knowledge/aldric")['status']);
        $this->assertError(404, 'KNOWLEDGE_NOT_FOUND', $this->api('GET', "/api/books/$b/knowledge/aldric"));
    }

    /** @return iterable<string, array{string}> */
    public static function knowledgeTypes(): iterable
    {
        foreach (Knowledge::TYPES as $type) {
            yield $type => [$type];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('knowledgeTypes')]
    public function testEveryTypeOfEntryIsAccepted(string $type): void
    {
        $response = $this->api('POST', "/api/books/{$this->bookId}/knowledge", $this->entry("a-$type", $type));

        self::assertSame(201, $response['status']);
        self::assertSame($type, $response['data']['type']);
    }

    public function testTheTypesAreTheOnesTheAppKnows(): void
    {
        self::assertSame(
            ['character', 'group', 'species', 'place', 'item', 'system', 'ability', 'concept', 'rank', 'theme'],
            Knowledge::TYPES,
            'The front end (src/constants.js) must list the same types in the same order.',
        );
    }

    public function testAnUnknownTypeIsRefusedWithTheListOfTypes(): void
    {
        $response = $this->api('POST', "/api/books/{$this->bookId}/knowledge", $this->entry('x', 'monster'));

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertStringContainsString('"theme"', $response['data']['error']['details']['fields']['type'][0]);
    }

    public function testAllFaultyFieldsAreReportedAtOnce(): void
    {
        $response = $this->api('POST', "/api/books/{$this->bookId}/knowledge", ['id' => 'Bad Id!', 'type' => 'character', 'unknown' => 1, 'aliases' => 'not-a-list']);

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        $fields = $response['data']['error']['details']['fields'];
        self::assertArrayHasKey('unknown', $fields);
        self::assertArrayHasKey('aliases', $fields);
    }

    public function testRequiredFieldsAndTheFormatOfTheIdentifierAreChecked(): void
    {
        $missing = $this->api('POST', "/api/books/{$this->bookId}/knowledge", ['id' => 'x', 'type' => 'character']);
        $this->assertError(400, 'VALIDATION_FAILED', $missing);
        self::assertArrayHasKey('name', $missing['data']['error']['details']['fields']);
        self::assertArrayHasKey('summary', $missing['data']['error']['details']['fields']);

        $badId = $this->api('POST', "/api/books/{$this->bookId}/knowledge", $this->entry('Not A Slug'));
        $this->assertError(400, 'VALIDATION_FAILED', $badId);
        self::assertArrayHasKey('id', $badId['data']['error']['details']['fields']);
    }

    public function testTheIdentifierCannotChangeAfterCreation(): void
    {
        $this->api('POST', "/api/books/{$this->bookId}/knowledge", $this->entry());

        $this->assertError(400, 'VALIDATION_FAILED', $this->api('PATCH', "/api/books/{$this->bookId}/knowledge/aldric", ['id' => 'other']));
    }

    public function testAnIdentifierIsUniquePerBookNotAcrossBooks(): void
    {
        $b = $this->bookId;
        $this->api('POST', "/api/books/$b/knowledge", $this->entry());

        $this->assertError(409, 'ID_ALREADY_EXISTS', $this->api('POST', "/api/books/$b/knowledge", $this->entry()));

        $other = $this->createBook('Other');
        self::assertSame(201, $this->api('POST', "/api/books/$other/knowledge", $this->entry())['status'], 'Two books can each have an "aldric".');
    }

    public function testListsArePaginatedAndShort(): void
    {
        $b = $this->bookId;
        foreach (['a', 'b', 'c'] as $id) {
            $this->api('POST', "/api/books/$b/knowledge", $this->entry($id));
        }

        $page = $this->api('GET', "/api/books/$b/knowledge?limit=2&offset=1");
        self::assertSame(3, $page['data']['total']);
        self::assertSame(2, $page['data']['limit']);
        self::assertSame(1, $page['data']['offset']);
        self::assertSame(['b', 'c'], $this->ids($page));
        self::assertArrayNotHasKey('description', $page['data']['data'][0], 'The long text only comes with the single entry.');

        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "/api/books/$b/knowledge?limit=500"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "/api/books/$b/knowledge?offset=-1"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "/api/books/$b/knowledge?type=monster"));
    }

    public function testEntriesCanBeFilteredByType(): void
    {
        $b = $this->bookId;
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('a', 'character'));
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('b', 'place'));
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('c', 'place'));

        self::assertSame(['b', 'c'], $this->ids($this->api('GET', "/api/books/$b/knowledge?type=place")));
    }

    public function testTheIndexListsEveryEntryLightly(): void
    {
        $b = $this->bookId;
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('a'));

        $index = $this->api('GET', "/api/books/$b/index");
        self::assertSame(200, $index['status']);
        self::assertSame(['id', 'name', 'type', 'aliases'], array_keys($index['data']['data'][0]));
        self::assertNotEmpty($index['headers']['etag'] ?? null, 'The index can be cached with an ETag.');
    }

    // ---- Events ------------------------------------------------------------------------------------

    private function event(string $id, int $order, ?int $chapter = null, bool $revealed = true): array
    {
        return ['id' => $id, 'title' => "Event $id", 'summary' => 'Something happens.', 'worldOrder' => $order, 'chapter' => $chapter, 'revealed' => $revealed];
    }

    public function testEventsAreListedInTheOrderOfTheWorldAndCanBeFiltered(): void
    {
        $b = $this->bookId;
        $this->api('POST', "/api/books/$b/events", $this->event('late', 30, 2));
        $this->api('POST', "/api/books/$b/events", $this->event('early', 10, 1));
        $this->api('POST', "/api/books/$b/events", $this->event('middle', 20, 1, false));

        self::assertSame(['early', 'middle', 'late'], $this->ids($this->api('GET', "/api/books/$b/events")));
        self::assertSame(['early', 'middle'], $this->ids($this->api('GET', "/api/books/$b/events?chapter=1")));
        self::assertSame(['middle'], $this->ids($this->api('GET', "/api/books/$b/events?revealed=false")));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "/api/books/$b/events?revealed=maybe"));
    }

    public function testAnEventHasSensibleDefaultsAndStrictFields(): void
    {
        $b = $this->bookId;

        $created = $this->api('POST', "/api/books/$b/events", ['id' => 'evt-1', 'title' => 'T', 'summary' => 'S', 'worldOrder' => 5]);
        self::assertSame(201, $created['status']);
        self::assertTrue($created['data']['revealed'], 'An event is known to the reader unless said otherwise.');
        self::assertNull($created['data']['chapter']);
        self::assertSame([], $created['data']['tags']);

        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', "/api/books/$b/events", ['id' => 'evt-2', 'title' => 'T', 'summary' => 'S']));
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', "/api/books/$b/events", ['id' => 'evt-3', 'title' => 'T', 'summary' => 'S', 'worldOrder' => '5']));
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', "/api/books/$b/events", ['id' => 'evt-4', 'title' => 'T', 'summary' => 'S', 'worldOrder' => 5, 'chapter' => 0]));
    }

    // ---- Participants ----------------------------------------------------------------------------

    public function testParticipantLinksAreCreatedChangedAndRemoved(): void
    {
        $b = $this->bookId;
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('aldric'));
        $this->api('POST', "/api/books/$b/events", $this->event('evt-1', 1));

        $link = ['eventId' => 'evt-1', 'knowledgeId' => 'aldric', 'role' => 'author'];
        self::assertSame(201, $this->api('POST', "/api/books/$b/event-participants", $link)['status']);
        $this->assertError(409, 'ID_ALREADY_EXISTS', $this->api('POST', "/api/books/$b/event-participants", $link));

        $changed = $this->api('PATCH', "/api/books/$b/event-participants/evt-1/aldric", ['role' => 'victim']);
        self::assertSame('victim', $changed['data']['role']);

        $list = $this->api('GET', "/api/books/$b/event-participants?eventId=evt-1");
        self::assertSame(1, $list['data']['total']);

        self::assertSame(204, $this->api('DELETE', "/api/books/$b/event-participants/evt-1/aldric")['status']);
        $this->assertError(404, 'PARTICIPANT_NOT_FOUND', $this->api('GET', "/api/books/$b/event-participants/evt-1/aldric"));
    }

    public function testALinkNeedsExistingEventAndEntry(): void
    {
        $b = $this->bookId;
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('aldric'));

        $this->assertError(409, 'REFERENCE_NOT_FOUND', $this->api('POST', "/api/books/$b/event-participants", ['eventId' => 'ghost', 'knowledgeId' => 'aldric']));
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', "/api/books/$b/event-participants", ['eventId' => 'ghost']));
    }

    public function testALinkCannotCrossBooks(): void
    {
        $b = $this->bookId;
        $other = $this->createBook('Other');
        $this->api('POST', "/api/books/$b/events", $this->event('evt-1', 1));
        $this->api('POST', "/api/books/$other/knowledge", $this->entry('foreigner'));

        $this->assertError(409, 'REFERENCE_NOT_FOUND', $this->api('POST', "/api/books/$b/event-participants", ['eventId' => 'evt-1', 'knowledgeId' => 'foreigner']));
    }

    public function testDeletingAnEventOrAnEntryRemovesItsLinks(): void
    {
        $b = $this->bookId;
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('aldric'));
        $this->api('POST', "/api/books/$b/knowledge", $this->entry('mira'));
        $this->api('POST', "/api/books/$b/events", $this->event('evt-1', 1));
        $this->api('POST', "/api/books/$b/event-participants", ['eventId' => 'evt-1', 'knowledgeId' => 'aldric']);
        $this->api('POST', "/api/books/$b/event-participants", ['eventId' => 'evt-1', 'knowledgeId' => 'mira']);

        $this->api('DELETE', "/api/books/$b/knowledge/aldric");
        self::assertSame(1, $this->api('GET', "/api/books/$b/event-participants")['data']['total']);

        $this->api('DELETE', "/api/books/$b/events/evt-1");
        self::assertSame(0, $this->api('GET', "/api/books/$b/event-participants")['data']['total']);
    }

    public function testMalformedJsonIsReportedAsSuch(): void
    {
        $this->assertError(400, 'INVALID_JSON', $this->send('POST', "/api/books/{$this->bookId}/knowledge", '{not json'));
        $this->assertError(400, 'INVALID_JSON', $this->send('POST', "/api/books/{$this->bookId}/knowledge", '[1, 2]'));
    }

    public function testUnknownRoutesAndMethodsGiveJsonErrors(): void
    {
        $this->assertError(404, 'ROUTE_NOT_FOUND', $this->api('GET', '/api/nothing-here'));
        $this->assertError(405, 'METHOD_NOT_ALLOWED', $this->api('PUT', "/api/books/{$this->bookId}/knowledge"));
    }
}
