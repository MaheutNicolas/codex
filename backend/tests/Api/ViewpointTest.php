<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

/**
 * The point of view of the reader on the timeline and on the sheet of an entry. The story (see ApiTestCase::story)
 * has events told in chapters 1, 3 and 7, one secret event told in chapter 2, and one never told.
 */
final class ViewpointTest extends ApiTestCase
{
    private int $bookId;

    private string $timeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();
        $this->import($this->bookId, $this->story());
        $this->timeline = "/api/books/{$this->bookId}/timeline";
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function viewpoints(): iterable
    {
        yield 'a reader sees the revealed events' => ['', ['evt-1', 'evt-2', 'evt-3']];
        yield 'who has read up to chapter 3' => ['?atChapter=3', ['evt-1', 'evt-2']];
        yield 'who has read up to chapter 7' => ['?atChapter=7', ['evt-1', 'evt-2', 'evt-3']];
        yield 'who is writing chapter 3' => ['?beforeChapter=3', ['evt-1']];
        yield 'who is writing chapter 1 sees nothing yet' => ['?beforeChapter=1', []];
        yield 'the author sees everything, in the order of the world' => ['?includeSecrets=true', ['evt-0', 'evt-1', 'evt-2', 'evt-3', 'evt-4']];
        yield 'the author up to chapter 3 keeps the secrets and what is never told' => ['?includeSecrets=true&atChapter=3', ['evt-0', 'evt-1', 'evt-2', 'evt-4']];
        yield 'the author before chapter 2 does not see a later secret' => ['?includeSecrets=true&beforeChapter=2', ['evt-0', 'evt-1']];
        yield 'an entry takes part in some events only' => ['?knowledgeId=aldric', ['evt-1', 'evt-3']];
        yield 'the author sees the secrets of an entry too' => ['?knowledgeId=aldric&includeSecrets=true', ['evt-1', 'evt-3', 'evt-4']];
        yield 'an entry seen from a chapter' => ['?knowledgeId=aldric&atChapter=5', ['evt-1']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('viewpoints')]
    public function testTheTimelineFollowsThePointOfView(string $query, array $expected): void
    {
        self::assertSame($expected, $this->ids($this->api('GET', $this->timeline.$query)));
    }

    public function testTheTotalFollowsThePointOfViewToo(): void
    {
        self::assertSame(2, $this->api('GET', $this->timeline.'?atChapter=3')['data']['total']);
        self::assertSame(5, $this->api('GET', $this->timeline.'?includeSecrets=true&limit=2')['data']['total']);
    }

    public function testTheTimelineIsPaginated(): void
    {
        $page = $this->api('GET', $this->timeline.'?includeSecrets=true&limit=2&offset=2');

        self::assertSame(['evt-2', 'evt-3'], $this->ids($page));
        self::assertSame(2, $page['data']['limit']);
        self::assertSame(2, $page['data']['offset']);
    }

    /** @return iterable<string, array{string}> */
    public static function badViewpoints(): iterable
    {
        yield 'both chapter parameters' => ['?atChapter=3&beforeChapter=2'];
        yield 'chapter zero' => ['?atChapter=0'];
        yield 'a chapter that is not a number' => ['?beforeChapter=abc'];
        yield 'a secrets flag that is not a boolean' => ['?includeSecrets=maybe'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badViewpoints')]
    public function testAnUnusableViewpointIsRefused(string $query): void
    {
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', $this->timeline.$query));
    }

    public function testEachEventComesWithItsParticipants(): void
    {
        $events = $this->api('GET', $this->timeline.'?atChapter=1')['data']['data'];

        self::assertSame('evt-1', $events[0]['id']);
        self::assertSame(
            [['id' => 'aldric', 'name' => 'Aldric', 'role' => 'author'], ['id' => 'citadel', 'name' => 'North Citadel', 'role' => 'place']],
            $events[0]['participants'],
        );

        $feast = $this->api('GET', $this->timeline.'?atChapter=3')['data']['data'][1];
        self::assertSame([['id' => 'mira', 'name' => 'Mira', 'role' => null]], $feast['participants'], 'A participant without a role has a null role.');
    }

    public function testTheTimelineDoesNotChangeWhatTheAppSees(): void
    {
        // The viewpoint filters only apply to the timeline, the sheet and the search: the app lists everything.
        self::assertSame(5, $this->api('GET', "/api/books/{$this->bookId}/events")['data']['total']);
    }

    // ---- The sheet of an entry ---------------------------------------------------------------------

    public function testTheSheetOfAnEntryComesWithItsVisibleEvents(): void
    {
        $sheet = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric?events=true&atChapter=5");

        self::assertSame(200, $sheet['status']);
        self::assertSame('Aldric swore an oath to protect the citadel.', $sheet['data']['description']);
        self::assertSame(['evt-1'], $this->ids(['data' => $sheet['data']['events']]));
        self::assertSame('author', $sheet['data']['events']['data'][0]['role'], 'The role of the entry is shown.');
        self::assertArrayNotHasKey('participants', $sheet['data']['events']['data'][0], 'The other participants are left out to keep the sheet short.');
    }

    public function testTheSheetCanBePaginated(): void
    {
        $sheet = $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric?events=true&includeSecrets=true&limit=1&offset=1");

        self::assertSame(3, $sheet['data']['events']['total']);
        self::assertSame(['evt-3'], $this->ids(['data' => $sheet['data']['events']]));
    }

    public function testWithoutAskingForEventsTheSheetIsJustTheEntry(): void
    {
        self::assertArrayNotHasKey('events', $this->api('GET', "/api/books/{$this->bookId}/knowledge/aldric")['data']);
    }
}
