<?php

namespace App\Tests\Api;

use App\Entity\Relation;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The relations between entries: one state per pair and chapter, so the history of a pair is a list of states
 * ("allies from the start", "enemies from chapter 6", "no longer linked from chapter 9").
 */
final class RelationTest extends ApiTestCase
{
    private int $bookId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsNewUser();
        $this->bookId = $this->createBook();
        $this->import($this->bookId, ['knowledge' => [
            ['id' => 'aldric', 'type' => 'character', 'name' => 'Aldric', 'summary' => 's'],
            ['id' => 'mira', 'type' => 'character', 'name' => 'Mira', 'summary' => 's'],
            ['id' => 'corvin', 'type' => 'character', 'name' => 'Corvin', 'summary' => 's'],
            ['id' => 'citadel', 'type' => 'place', 'name' => 'North Citadel', 'summary' => 's'],
        ]]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/books/{$this->bookId}/relations$suffix";
    }

    /**
     * @param array<string, mixed> $more
     *
     * @return array<string, mixed>
     */
    private function state(string $id, string $source = 'aldric', string $target = 'mira', string $type = 'ally', ?int $chapter = null, array $more = []): array
    {
        return ['id' => $id, 'sourceId' => $source, 'targetId' => $target, 'type' => $type, 'chapter' => $chapter] + $more;
    }

    // ---- The relations ---------------------------------------------------------------------------

    public function testARelationIsCreatedReadUpdatedAndDeleted(): void
    {
        $created = $this->api('POST', $this->url(), $this->state('rel-0001', 'aldric', 'mira', 'ally', null, ['note' => 'Sworn allies.']));

        self::assertSame(201, $created['status']);
        self::assertSame($this->url('/rel-0001'), $created['headers']['location'][0]);
        self::assertSame(
            ['id' => 'rel-0001', 'sourceId' => 'aldric', 'targetId' => 'mira', 'type' => 'ally', 'chapter' => null, 'revealed' => true, 'note' => 'Sworn allies.'],
            array_diff_key($created['data'], ['createdAt' => 0, 'updatedAt' => 0]),
        );

        self::assertSame('ally', $this->api('GET', $this->url('/rel-0001'))['data']['type']);

        $updated = $this->api('PATCH', $this->url('/rel-0001'), ['type' => 'enemy', 'chapter' => 6, 'revealed' => false, 'note' => null]);
        self::assertSame(200, $updated['status']);
        self::assertSame('enemy', $updated['data']['type']);
        self::assertSame(6, $updated['data']['chapter']);
        self::assertFalse($updated['data']['revealed']);
        self::assertNull($updated['data']['note']);
        self::assertSame('aldric', $updated['data']['sourceId'], 'A partial update leaves the other fields alone.');

        self::assertSame(204, $this->api('DELETE', $this->url('/rel-0001'))['status']);
        $this->assertError(404, 'RELATION_NOT_FOUND', $this->api('GET', $this->url('/rel-0001')));
        $this->assertError(404, 'RELATION_NOT_FOUND', $this->api('DELETE', $this->url('/rel-0001')));
    }

    public function testARelationIsKnownToTheReaderUnlessSaidOtherwise(): void
    {
        self::assertTrue($this->api('POST', $this->url(), $this->state('rel-1'))['data']['revealed']);
        self::assertFalse($this->api('POST', $this->url(), $this->state('rel-2', 'aldric', 'corvin', 'enemy', null, ['revealed' => false]))['data']['revealed']);
    }

    public function testThePatchCanMoveARelationToAnotherPair(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1'));

        $moved = $this->api('PATCH', $this->url('/rel-1'), ['sourceId' => 'corvin', 'targetId' => 'citadel', 'type' => 'serves']);

        self::assertSame(200, $moved['status']);
        self::assertSame(['corvin', 'citadel', 'serves'], [$moved['data']['sourceId'], $moved['data']['targetId'], $moved['data']['type']]);
    }

    /** @return iterable<string, array{string}> */
    public static function types(): iterable
    {
        foreach (Relation::TYPES as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('types')]
    public function testEveryTypeOfRelationIsAccepted(string $type): void
    {
        $response = $this->api('POST', $this->url(), $this->state('rel-'.str_replace('_', '-', $type), 'aldric', 'mira', $type));

        self::assertSame(201, $response['status']);
        self::assertSame($type, $response['data']['type']);
    }

    public function testTheTypesAreEitherSymmetricOrDirectedNeverBoth(): void
    {
        self::assertSame([], array_intersect(Relation::SYMMETRIC_TYPES, Relation::DIRECTED_TYPES));
        self::assertEqualsCanonicalizing(Relation::TYPES, [...Relation::SYMMETRIC_TYPES, ...Relation::DIRECTED_TYPES]);
        self::assertTrue(Relation::isSymmetric('ally'));
        self::assertFalse(Relation::isSymmetric('mentor'));
        self::assertContains('none', Relation::TYPES, 'A relation can be ended with the type "none".');
    }

    // ---- Validation -------------------------------------------------------------------------------

    public function testAnUnknownTypeIsRefusedWithTheListOfTypes(): void
    {
        $response = $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'mira', 'cousin'));

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertStringContainsString('"member_of"', $response['data']['error']['details']['fields']['type'][0]);
    }

    public function testTheRequiredFieldsAreReportedTogether(): void
    {
        $response = $this->api('POST', $this->url(), ['id' => 'rel-1']);

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertEqualsCanonicalizing(['sourceId', 'targetId', 'type'], array_keys($response['data']['error']['details']['fields']));
    }

    public function testTheIdentifierIsRequiredAndMustBeASlug(): void
    {
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', $this->url(), ['sourceId' => 'aldric', 'targetId' => 'mira', 'type' => 'ally']));
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', $this->url(), $this->state('Not A Slug')));
    }

    public function testUnknownFieldsAndWrongTypesAreRefused(): void
    {
        $response = $this->api('POST', $this->url(), $this->state('rel-1') + ['colour' => 'red', 'revealed' => 'yes']);

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertArrayHasKey('colour', $response['data']['error']['details']['fields']);
        self::assertArrayHasKey('revealed', $response['data']['error']['details']['fields']);

        $this->api('POST', $this->url(), $this->state('rel-1'));
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('PATCH', $this->url('/rel-1'), ['id' => 'other']));
    }

    /** @return iterable<string, array{mixed}> */
    public static function badChapters(): iterable
    {
        yield 'zero (the start of the book is null)' => [0];
        yield 'negative' => [-3];
        yield 'not a number' => ['six'];
        yield 'a decimal' => [1.5];
    }

    #[DataProvider('badChapters')]
    public function testTheChapterIsAWholeNumberFromOneOrNull(mixed $chapter): void
    {
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', $this->url(), array_replace($this->state('rel-1'), ['chapter' => $chapter])));
    }

    public function testBothEntriesMustExistInTheSameBook(): void
    {
        $this->assertError(409, 'REFERENCE_NOT_FOUND', $this->api('POST', $this->url(), $this->state('rel-1', 'ghost', 'mira')));
        $this->assertError(409, 'REFERENCE_NOT_FOUND', $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'ghost')));

        $other = $this->createBook('Other');
        $this->import($other, ['knowledge' => [['id' => 'stranger', 'type' => 'character', 'name' => 'Stranger', 'summary' => 's']]]);
        $response = $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'stranger'));
        $this->assertError(409, 'REFERENCE_NOT_FOUND', $response);
        self::assertSame('targetId', $response['data']['error']['details']['field']);
    }

    public function testAnEntryHasNoRelationWithItself(): void
    {
        $response = $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'aldric'));

        $this->assertError(400, 'VALIDATION_FAILED', $response);
        self::assertArrayHasKey('targetId', $response['data']['error']['details']['fields']);
    }

    public function testTheNoteIsShort(): void
    {
        $this->assertError(400, 'VALIDATION_FAILED', $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'mira', 'ally', null, ['note' => str_repeat('x', 501)])));
        self::assertSame(201, $this->api('POST', $this->url(), $this->state('rel-2', 'aldric', 'mira', 'ally', null, ['note' => str_repeat('x', 500)]))['status']);
    }

    // ---- One state per pair and chapter -----------------------------------------------------------

    public function testAnIdentifierCanBeUsedOnlyOnce(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1'));

        $this->assertError(409, 'ID_ALREADY_EXISTS', $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'corvin')));
    }

    public function testAPairHasOneStatePerChapter(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'mira', 'ally', 3));

        $same = $this->api('POST', $this->url(), $this->state('rel-2', 'aldric', 'mira', 'enemy', 3));
        $this->assertError(409, 'RELATION_ALREADY_EXISTS', $same);
        self::assertSame('rel-1', $same['data']['error']['details']['existing'], 'The answer names the state in the way.');

        $reversed = $this->api('POST', $this->url(), $this->state('rel-3', 'mira', 'aldric', 'enemy', 3));
        $this->assertError(409, 'RELATION_ALREADY_EXISTS', $reversed);
    }

    public function testTheStartOfTheBookIsAChapterLikeAnother(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'mira', 'ally', null));

        $this->assertError(409, 'RELATION_ALREADY_EXISTS', $this->api('POST', $this->url(), $this->state('rel-2', 'mira', 'aldric', 'friend', null)));
    }

    public function testAnotherChapterOrAnotherPairIsFine(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'mira', 'ally', 3));

        self::assertSame(201, $this->api('POST', $this->url(), $this->state('rel-2', 'aldric', 'mira', 'enemy', 4))['status']);
        self::assertSame(201, $this->api('POST', $this->url(), $this->state('rel-3', 'aldric', 'mira', 'enemy', null))['status']);
        self::assertSame(201, $this->api('POST', $this->url(), $this->state('rel-4', 'aldric', 'corvin', 'enemy', 3))['status']);
    }

    public function testChangingARelationCannotLandOnAnOccupiedChapter(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1', 'aldric', 'mira', 'ally', 3));
        $this->api('POST', $this->url(), $this->state('rel-2', 'aldric', 'mira', 'enemy', 6));

        $this->assertError(409, 'RELATION_ALREADY_EXISTS', $this->api('PATCH', $this->url('/rel-2'), ['chapter' => 3]));
        self::assertSame(200, $this->api('PATCH', $this->url('/rel-2'), ['chapter' => 6, 'note' => 'Same chapter, only the note changes.'])['status'], 'A state is not in conflict with itself.');
        self::assertSame(200, $this->api('PATCH', $this->url('/rel-2'), ['chapter' => 7])['status']);
    }

    // ---- Reading ------------------------------------------------------------------------------------

    private function progression(): void
    {
        // Aldric and Mira: allies from the start, enemies from chapter 6, no longer linked from chapter 9.
        $this->api('POST', $this->url(), $this->state('rel-b', 'aldric', 'mira', 'enemy', 6));
        $this->api('POST', $this->url(), $this->state('rel-a', 'aldric', 'mira', 'ally', null));
        $this->api('POST', $this->url(), $this->state('rel-c', 'mira', 'aldric', 'none', 9));
        $this->api('POST', $this->url(), $this->state('rel-d', 'corvin', 'aldric', 'mentor', 2));
        $this->api('POST', $this->url(), $this->state('rel-e', 'corvin', 'citadel', 'serves', null));
    }

    public function testTheListIsTheHistoryByChapterWithTheStartFirst(): void
    {
        $this->progression();

        $all = $this->api('GET', $this->url());

        self::assertSame(['rel-a', 'rel-e', 'rel-d', 'rel-b', 'rel-c'], $this->ids($all));
        self::assertSame([null, null, 2, 6, 9], array_column($all['data']['data'], 'chapter'));
        self::assertSame(5, $all['data']['total']);
    }

    public function testAnEntrysRelationsAreFoundWhicheverSideItIsOn(): void
    {
        $this->progression();

        self::assertSame(['rel-a', 'rel-d', 'rel-b', 'rel-c'], $this->ids($this->api('GET', $this->url('?knowledgeId=aldric'))), 'Aldric is a source, a target, and both.');
        self::assertSame(['rel-a', 'rel-b', 'rel-c'], $this->ids($this->api('GET', $this->url('?knowledgeId=mira'))));
        self::assertSame(['rel-e'], $this->ids($this->api('GET', $this->url('?knowledgeId=citadel'))));
        self::assertSame([], $this->ids($this->api('GET', $this->url('?knowledgeId=nobody'))));
    }

    public function testRelationsCanBeFilteredByType(): void
    {
        $this->progression();

        self::assertSame(['rel-b'], $this->ids($this->api('GET', $this->url('?type=enemy'))));
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', $this->url('?type=cousin')));
    }

    public function testTheListIsPaginated(): void
    {
        $this->progression();

        $page = $this->api('GET', $this->url('?limit=2&offset=1'));

        self::assertSame(['rel-e', 'rel-d'], $this->ids($page));
        self::assertSame(5, $page['data']['total']);
        $this->assertError(400, 'INVALID_QUERY_PARAMETER', $this->api('GET', $this->url('?limit=500')));
    }

    // ---- Life of the data ----------------------------------------------------------------------------

    public function testDeletingAnEntryRemovesItsRelations(): void
    {
        $this->progression();

        $this->api('DELETE', "/api/books/{$this->bookId}/knowledge/aldric");

        self::assertSame(['rel-e'], $this->ids($this->api('GET', $this->url())), 'Every state in which Aldric took part is gone.');
    }

    public function testRelationsStayInTheirBook(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1'));
        $other = $this->createBook('Other');

        self::assertSame(0, $this->api('GET', "/api/books/$other/relations")['data']['total']);
        $this->assertError(404, 'RELATION_NOT_FOUND', $this->api('GET', "/api/books/$other/relations/rel-1"));
    }

    public function testAReadOnlyKeyReadsButDoesNotWrite(): void
    {
        $this->api('POST', $this->url(), $this->state('rel-1'));
        $key = ['X-API-Key' => $this->keyOf($this->bookId)];
        $this->logout();

        self::assertSame(1, $this->api('GET', $this->url(), null, $key)['data']['total']);
        $this->assertError(403, 'FORBIDDEN', $this->api('POST', $this->url(), $this->state('rel-2', 'aldric', 'corvin'), $key));
        $this->assertError(403, 'FORBIDDEN', $this->api('PATCH', $this->url('/rel-1'), ['type' => 'enemy'], $key));
        $this->assertError(403, 'FORBIDDEN', $this->api('DELETE', $this->url('/rel-1'), null, $key));
    }

    // ---- Import and export --------------------------------------------------------------------------

    /** @return array{relations: list<array<string, mixed>>} */
    private function relationsDocument(): array
    {
        return ['relations' => [
            $this->state('rel-a', 'aldric', 'mira', 'ally', null),
            $this->state('rel-b', 'aldric', 'mira', 'enemy', 6, ['note' => 'The betrayal.', 'revealed' => false]),
            $this->state('rel-c', 'corvin', 'aldric', 'mentor', 2),
        ]];
    }

    public function testRelationsCanBeImportedAndThenUpdated(): void
    {
        $created = $this->api('POST', "/api/books/{$this->bookId}/import", $this->relationsDocument());
        self::assertSame(200, $created['status']);
        self::assertSame(3, $created['data']['created']['relations']);

        $document = $this->relationsDocument();
        $document['relations'][1]['note'] = 'Changed.';
        $again = $this->api('POST', "/api/books/{$this->bookId}/import", $document);

        self::assertSame(0, $again['data']['created']['relations']);
        self::assertSame(3, $again['data']['updated']['relations']);
        self::assertSame('Changed.', $this->api('GET', $this->url('/rel-b'))['data']['note']);
        self::assertSame(3, $this->api('GET', $this->url())['data']['total']);
    }

    public function testADocumentCanDefineTheEntriesAndTheirRelationsTogether(): void
    {
        $other = $this->createBook('Fresh');
        $document = [
            'knowledge' => [
                ['id' => 'x', 'type' => 'character', 'name' => 'X', 'summary' => 's'],
                ['id' => 'y', 'type' => 'group', 'name' => 'Y', 'summary' => 's'],
            ],
            'relations' => [['id' => 'rel-1', 'sourceId' => 'x', 'targetId' => 'y', 'type' => 'member_of', 'chapter' => null]],
        ];

        $response = $this->api('POST', "/api/books/$other/import", $document);

        self::assertSame(200, $response['status']);
        self::assertSame(1, $response['data']['created']['relations']);
    }

    public function testAnInvalidRelationStopsTheWholeImport(): void
    {
        $document = $this->relationsDocument();
        $document['relations'][2]['targetId'] = 'ghost';

        $response = $this->api('POST', "/api/books/{$this->bookId}/import", $document);

        $this->assertError(409, 'REFERENCE_NOT_FOUND', $response);
        self::assertSame('relations[2]', $response['data']['error']['details']['path']);
        self::assertSame(0, $this->api('GET', $this->url())['data']['total'], 'The two good relations were not kept.');
    }

    public function testTwoStatesOfAPairAtTheSameChapterStopTheImport(): void
    {
        $document = ['relations' => [$this->state('rel-a', 'aldric', 'mira', 'ally', 4), $this->state('rel-b', 'mira', 'aldric', 'enemy', 4)]];

        $response = $this->api('POST', "/api/books/{$this->bookId}/import", $document);

        $this->assertError(409, 'RELATION_ALREADY_EXISTS', $response);
        self::assertSame('relations[1]', $response['data']['error']['details']['path']);
    }

    public function testTheExportHoldsTheRelationsAndTheyComeBack(): void
    {
        $this->import($this->bookId, $this->relationsDocument());

        $export = $this->api('GET', "/api/books/{$this->bookId}/export")['data'];

        self::assertSame(['rel-a', 'rel-c', 'rel-b'], array_column($export['relations'], 'id'), 'By chapter, the start of the book first.');
        self::assertNull($export['relations'][0]['chapter']);
        self::assertSame(
            ['id' => 'rel-b', 'sourceId' => 'aldric', 'targetId' => 'mira', 'type' => 'enemy', 'chapter' => 6, 'revealed' => false, 'note' => 'The betrayal.'],
            $export['relations'][2],
        );

        $copy = $this->createBook('Copy');
        $this->api('POST', "/api/books/$copy/import", $export);
        self::assertSame($export, $this->api('GET', "/api/books/$copy/export")['data']);
    }

    public function testAnOldExportWithoutRelationsStillImports(): void
    {
        $response = $this->api('POST', "/api/books/{$this->bookId}/import", ['knowledge' => [['id' => 'new', 'type' => 'item', 'name' => 'New', 'summary' => 's']]]);

        self::assertSame(200, $response['status']);
        self::assertSame(0, $response['data']['created']['relations']);
    }
}
