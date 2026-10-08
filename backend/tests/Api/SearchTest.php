<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

/** The full-text search over entries and events, seen from a point of view. */
final class SearchTest extends ApiTestCase
{
    private int $bookId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();
        $this->import($this->bookId, $this->story());
    }

    /** @return list<array<string, mixed>> */
    private function search(string $query, string $extra = ''): array
    {
        $response = $this->api('GET', "/api/books/{$this->bookId}/search?q=".rawurlencode($query).$extra);
        self::assertSame(200, $response['status'], json_encode($response['data']));

        return $response['data']['data'];
    }

    /**
     * @param list<array<string, mixed>> $results
     *
     * @return list<string> "k:aldric" for an entry, "e:evt-1" for an event
     */
    private function keys(array $results): array
    {
        return array_map(static fn (array $r) => $r['kind'][0].':'.$r['id'], $results);
    }

    public function testAWordInTheTextsFindsEntriesAndEvents(): void
    {
        $found = $this->keys($this->search('oath'));

        self::assertContains('e:evt-1', $found, 'In the title of an event.');
        self::assertContains('k:aldric', $found, 'In the description of an entry.');
    }

    public function testTheBeginningOfAWordIsEnough(): void
    {
        self::assertContains('k:citadel', $this->keys($this->search('cita')));
    }

    public function testANameBeatsAWordInTheTexts(): void
    {
        $results = $this->search('citadel');

        self::assertSame('k:citadel', $this->keys($results)[0], 'The entry named "North Citadel" comes before the events that merely mention it.');
    }

    public function testAliasesAreFoundWhateverTheCaseAndTheAccents(): void
    {
        self::assertContains('k:aldric', $this->keys($this->search('the One-Eyed')));
        self::assertContains('k:aldric', $this->keys($this->search('one-eyed')));
        self::assertContains('k:aldric', $this->keys($this->search('ONE-EYED')));
        self::assertContains('k:aldric', $this->keys($this->search('zoe')), 'The alias "Zoé" is found without the accent.');
        self::assertContains('k:aldric', $this->keys($this->search('ZOÉ')));
    }

    public function testTagsAreFoundWhateverTheCase(): void
    {
        self::assertContains('e:evt-1', $this->keys($this->search('VOW')));
    }

    public function testShortWordsAreFoundInNames(): void
    {
        self::assertContains('k:mira', $this->keys($this->search('mi')));
    }

    public function testAccentsInANameAreIgnored(): void
    {
        $this->api('POST', "/api/books/{$this->bookId}/knowledge", ['id' => 'eloise', 'type' => 'character', 'name' => 'Éloïse', 'summary' => 's']);

        self::assertContains('k:eloise', $this->keys($this->search('eloise')));
        self::assertContains('k:eloise', $this->keys($this->search('ÉLOÏSE')));
    }

    public function testSecretsAreHiddenUnlessTheAuthorAsks(): void
    {
        self::assertSame([], $this->search('poisoning'));
        self::assertSame(['e:evt-0'], $this->keys($this->search('poisoning', '&includeSecrets=true')));
        self::assertSame([], $this->search('twist'), 'An event unknown to the reader stays hidden even when it is told in an early chapter.');
    }

    public function testEventsToldLaterAreHiddenFromAnEarlierReader(): void
    {
        self::assertContains('e:evt-3', $this->keys($this->search('betrayal')));
        self::assertNotContains('e:evt-3', $this->keys($this->search('betrayal', '&atChapter=3')));
        self::assertNotContains('e:evt-3', $this->keys($this->search('betrayal', '&beforeChapter=7')));
        self::assertContains('e:evt-3', $this->keys($this->search('betrayal', '&atChapter=7')));
    }

    public function testResultsDescribeEntriesAndEventsTheSameWayEverywhere(): void
    {
        $results = $this->search('aldric');
        $entry = array_values(array_filter($results, static fn (array $r) => 'knowledge' === $r['kind']))[0];
        $event = array_values(array_filter($results, static fn (array $r) => 'event' === $r['kind']))[0];

        self::assertSame('Aldric', $entry['name']);
        self::assertSame('character', $entry['type']);
        self::assertArrayNotHasKey('title', $entry);
        self::assertArrayHasKey('title', $event, 'An event has a "title", as everywhere else.');
        self::assertArrayNotHasKey('name', $event);
        self::assertArrayHasKey('chapter', $event);
        self::assertIsFloat($entry['score']);
    }

    public function testTheTotalCountsEveryMatchNotOnlyTheReturnedOnes(): void
    {
        $heroes = array_map(static fn (int $i) => ['id' => "hero-$i", 'type' => 'character', 'name' => "Hero $i", 'summary' => "The hero number $i."], range(1, 25));
        $this->import($this->bookId, ['knowledge' => $heroes]);

        $response = $this->api('GET', "/api/books/{$this->bookId}/search?q=hero&limit=5");

        self::assertCount(5, $response['data']['data']);
        self::assertSame(25, $response['data']['total']);
    }

    public function testSearchTermsCannotBeUsedAsOperatorsOrWildcards(): void
    {
        // Characters with a meaning for the full-text engine or for LIKE are just ignored or matched literally.
        self::assertSame([], $this->search('+-><()~*"@'.'zzzz'));
        self::assertSame([], $this->search('100%'), 'A percent sign is not a wildcard.');
        self::assertSame([], $this->search('%'.'%'.'abc'), 'Neither are two of them.');
        // Punctuation, "_" included, only separates words: "_ldric" is the word "ldric", found inside "Aldric".
        self::assertContains('k:aldric', $this->keys($this->search('_ldric')));
        self::assertSame([], $this->search('zz_zz'), 'An underscore is not a wildcard for a single character either.');
    }

    public function testASearchNeedsAtLeastOneWord(): void
    {
        $base = "/api/books/{$this->bookId}/search";

        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', $base));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?q="));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?q=%25%25%2B"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?q=oath&limit=0"));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', "$base?q=oath&limit=51"));
    }

    public function testASearchOnlySeesItsOwnBook(): void
    {
        $other = $this->createBook('Other');
        $this->import($other, ['knowledge' => [['id' => 'zorglub', 'type' => 'character', 'name' => 'Zorglub', 'summary' => 'Only in the other book.']]]);

        self::assertSame([], $this->search('zorglub'));
    }
}
