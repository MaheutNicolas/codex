<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

/** The bulk import (atomic, all or nothing) and the export, which the import accepts again. */
final class ImportExportTest extends ApiTestCase
{
    private int $bookId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();
    }

    private function importUrl(): string
    {
        return "/api/books/{$this->bookId}/import";
    }

    public function testAnImportCreatesThenUpdates(): void
    {
        $created = $this->api('POST', $this->importUrl(), $this->story());
        self::assertSame(200, $created['status']);
        self::assertSame(['knowledge' => 3, 'events' => 5, 'participants' => 6], $created['data']['created']);
        self::assertSame(['knowledge' => 0, 'events' => 0, 'participants' => 0], $created['data']['updated']);

        // The same identifiers again: everything is updated, nothing is created twice.
        $document = $this->story();
        $document['knowledge'][1]['summary'] = 'A very good healer.';
        $document['participants'][0]['role'] = 'victim';
        $again = $this->api('POST', $this->importUrl(), $document);

        self::assertSame(['knowledge' => 0, 'events' => 0, 'participants' => 0], $again['data']['created']);
        self::assertSame(['knowledge' => 3, 'events' => 5, 'participants' => 6], $again['data']['updated']);
        self::assertSame('A very good healer.', $this->api('GET', "/api/books/{$this->bookId}/knowledge/mira")['data']['summary']);
        self::assertSame('victim', $this->api('GET', "/api/books/{$this->bookId}/event-participants/evt-1/aldric")['data']['role']);
        self::assertSame(3, $this->api('GET', "/api/books/{$this->bookId}/knowledge")['data']['total']);
    }

    public function testAnImportCanHaveOnlySomeSections(): void
    {
        $response = $this->api('POST', $this->importUrl(), ['knowledge' => $this->story()['knowledge']]);

        self::assertSame(200, $response['status']);
        self::assertSame(3, $response['data']['created']['knowledge']);
        self::assertSame(0, $response['data']['created']['events']);
    }

    public function testLinksCanPointToItemsAlreadyInTheBook(): void
    {
        $this->import($this->bookId, ['knowledge' => $this->story()['knowledge'], 'events' => $this->story()['events']]);

        $response = $this->api('POST', $this->importUrl(), ['participants' => [['eventId' => 'evt-2', 'knowledgeId' => 'aldric', 'role' => 'guest']]]);

        self::assertSame(200, $response['status']);
        self::assertSame(1, $response['data']['created']['participants']);
    }

    public function testAnInvalidItemWritesNothingAtAll(): void
    {
        $document = [
            'knowledge' => [
                ['id' => 'fine', 'type' => 'character', 'name' => 'Fine', 'summary' => 's'],
                ['id' => 'broken', 'type' => 'monster', 'name' => 'Broken', 'summary' => 's'],
            ],
            'events' => [['id' => 'evt-ok', 'title' => 'T', 'summary' => 's', 'worldOrder' => 1]],
        ];

        $response = $this->api('POST', $this->importUrl(), $document);

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertSame('knowledge[1]', $response['data']['error']['details']['path'], 'The answer says which item stopped the import.');
        self::assertArrayHasKey('type', $response['data']['error']['details']['fields']);
        $this->assertError(404, 'KNOWLEDGE_NOT_FOUND', $this->api('GET', "/api/books/{$this->bookId}/knowledge/fine"));
        $this->assertError(404, 'EVENT_NOT_FOUND', $this->api('GET', "/api/books/{$this->bookId}/events/evt-ok"));
    }

    public function testAnImportThatFailsLateAlsoUndoesWhatCameBefore(): void
    {
        $document = $this->story();
        $document['participants'][] = ['eventId' => 'evt-1', 'knowledgeId' => 'nobody-by-that-id'];

        $response = $this->api('POST', $this->importUrl(), $document);

        $this->assertError(409, 'REFERENCE_NOT_FOUND', $response);
        self::assertSame('participants[6]', $response['data']['error']['details']['path']);
        self::assertSame(0, $this->api('GET', "/api/books/{$this->bookId}/knowledge")['data']['total']);
        self::assertSame(0, $this->api('GET', "/api/books/{$this->bookId}/events")['data']['total']);
    }

    public function testAFailedUpdateLeavesTheExistingDataAsItWas(): void
    {
        $this->import($this->bookId, $this->story());

        $response = $this->api('POST', $this->importUrl(), ['knowledge' => [
            ['id' => 'mira', 'type' => 'character', 'name' => 'Mira, changed', 'summary' => 's'],
            ['id' => 'aldric', 'type' => 'monster', 'name' => 'Aldric', 'summary' => 's'],
        ]]);

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertSame('Mira', $this->api('GET', "/api/books/{$this->bookId}/knowledge/mira")['data']['name']);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedDocuments(): iterable
    {
        yield 'not JSON' => ['{not json'];
        yield 'a list instead of an object' => ['[1, 2]'];
        yield 'an unknown section' => ['{"foo": []}'];
        yield 'a section that is not a list' => ['{"knowledge": {"a": 1}}'];
        yield 'an item that is not an object' => ['{"knowledge": ["text"]}'];
        yield 'an item that is a list' => ['{"events": [[1, 2]]}'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedDocuments')]
    public function testTheServerOnlySaysInvalidJsonForAMalformedDocument(string $body): void
    {
        $this->assertError(400, 'INVALID_JSON', $this->send('POST', $this->importUrl(), $body));
    }

    public function testASectionCannotHoldTooManyItems(): void
    {
        $items = array_map(static fn (int $i) => ['id' => "k-$i", 'type' => 'character', 'name' => "K $i", 'summary' => 's'], range(1, 1001));

        $response = $this->api('POST', $this->importUrl(), ['knowledge' => $items]);

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertArrayHasKey('knowledge', $response['data']['error']['details']['fields']);
        self::assertSame(0, $this->api('GET', "/api/books/{$this->bookId}/knowledge")['data']['total']);
    }

    // ---- Export ------------------------------------------------------------------------------------

    public function testTheExportHoldsEverythingInTheShapeOfAnImportDocument(): void
    {
        $this->import($this->bookId, $this->story());

        $export = $this->api('GET', "/api/books/{$this->bookId}/export");

        self::assertSame(200, $export['status']);
        self::assertSame(['knowledge', 'events', 'participants'], array_keys($export['data']));
        self::assertCount(3, $export['data']['knowledge']);
        self::assertCount(5, $export['data']['events']);
        self::assertCount(6, $export['data']['participants']);

        $aldric = array_values(array_filter($export['data']['knowledge'], static fn (array $k) => 'aldric' === $k['id']))[0];
        self::assertSame('Aldric swore an oath to protect the citadel.', $aldric['description'], 'The long texts are exported.');
        self::assertSame(['the One-Eyed', 'Zoé'], $aldric['aliases']);

        self::assertSame(['evt-0', 'evt-1', 'evt-2', 'evt-3', 'evt-4'], array_column($export['data']['events'], 'id'), 'Events come in the order of the world.');
        $secret = $export['data']['events'][0];
        self::assertFalse($secret['revealed']);
        self::assertNull($secret['chapter']);
        self::assertIsInt($export['data']['events'][1]['worldOrder']);
        self::assertSame(['vow'], $export['data']['events'][1]['tags']);
    }

    public function testAnExportCanBeImportedAgainAsIs(): void
    {
        $this->import($this->bookId, $this->story());
        $export = $this->api('GET', "/api/books/{$this->bookId}/export")['data'];

        $again = $this->api('POST', $this->importUrl(), $export);

        self::assertSame(200, $again['status']);
        self::assertSame(['knowledge' => 3, 'events' => 5, 'participants' => 6], $again['data']['updated']);

        // ...and it can fill another, empty book with the same content.
        $other = $this->createBook('Copy');
        $copy = $this->api('POST', "/api/books/$other/import", $export);
        self::assertSame(['knowledge' => 3, 'events' => 5, 'participants' => 6], $copy['data']['created']);
        self::assertSame($export, $this->api('GET', "/api/books/$other/export")['data']);
    }

    public function testAnEmptyBookExportsEmptySections(): void
    {
        self::assertSame(
            ['knowledge' => [], 'events' => [], 'participants' => []],
            $this->api('GET', "/api/books/{$this->bookId}/export")['data'],
        );
    }
}
