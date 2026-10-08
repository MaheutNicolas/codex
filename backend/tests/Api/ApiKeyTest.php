<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

/** The key of a book: one per book, read-only, tied to its book, and replaced (not duplicated) when regenerated. */
final class ApiKeyTest extends ApiTestCase
{
    public function testEachBookGetsOneReadOnlyKeyCreatedOnFirstUse(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook();

        $first = $this->api('GET', "/api/books/$bookId/api-key");
        self::assertSame(200, $first['status']);
        self::assertMatchesRegularExpression('/^cdx_[0-9a-f]{40}$/', $first['data']['token']);
        self::assertSame('read', $first['data']['scope']);
        self::assertNull($first['data']['lastUsedAt']);

        $second = $this->api('GET', "/api/books/$bookId/api-key");
        self::assertSame($first['data']['token'], $second['data']['token'], 'Asking again must show the same key, not create another one.');
    }

    public function testTheKeyReadsItsBookButCannotChangeIt(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook();
        $this->import($bookId, $this->story());
        $key = ['X-API-Key' => $this->keyOf($bookId)];
        $this->logout();

        $list = $this->api('GET', "/api/books/$bookId/knowledge", null, $key);
        self::assertSame(200, $list['status']);
        self::assertSame(3, $list['data']['total']);
        self::assertSame(200, $this->api('GET', "/api/books/$bookId/export", null, $key)['status']);
        self::assertSame(200, $this->api('GET', "/api/books/$bookId/search?q=oath", null, $key)['status']);

        $this->assertError(403, 'FORBIDDEN', $this->api('POST', "/api/books/$bookId/knowledge", ['id' => 'x', 'type' => 'character', 'name' => 'X', 'summary' => 's'], $key));
        $this->assertError(403, 'FORBIDDEN', $this->api('POST', "/api/books/$bookId/import", ['knowledge' => []], $key));
        $this->assertError(403, 'FORBIDDEN', $this->api('DELETE', "/api/books/$bookId/knowledge/aldric", null, $key));
    }

    public function testAKeyCannotManageBooksOrKeys(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook();
        $key = ['X-API-Key' => $this->keyOf($bookId)];
        $this->logout();

        $this->assertError(403, 'FORBIDDEN', $this->api('GET', "/api/books/$bookId/api-key", null, $key));
        $this->assertError(403, 'FORBIDDEN', $this->api('POST', "/api/books/$bookId/api-key/regenerate", null, $key));
        $this->assertError(403, 'FORBIDDEN', $this->api('POST', '/api/books', ['name' => 'Another'], $key));
        $this->assertError(403, 'FORBIDDEN', $this->api('DELETE', "/api/books/$bookId", null, $key));
    }

    public function testAKeyOpensOnlyItsOwnBook(): void
    {
        $this->loginAsNewUser();
        $first = $this->createBook('First');
        $second = $this->createBook('Second');
        $firstKey = ['X-API-Key' => $this->keyOf($first)];
        $this->logout();

        self::assertSame(200, $this->api('GET', "/api/books/$first/knowledge", null, $firstKey)['status']);
        $this->assertError(404, 'BOOK_NOT_FOUND', $this->api('GET', "/api/books/$second/knowledge", null, $firstKey));
    }

    public function testAnUnknownKeyIsRefused(): void
    {
        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', '/api/books/1/knowledge', null, ['X-API-Key' => 'cdx_'.str_repeat('0', 40)]));
        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', '/api/books/1/knowledge', null, ['X-API-Key' => 'not-even-a-key']));
    }

    public function testAKeyDoesNotFallBackOnTheSession(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook();

        // The browser is logged in, but this request carries a bad key: it must be refused, not served by the session.
        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', "/api/books/$bookId/knowledge", null, ['X-API-Key' => 'cdx_'.str_repeat('1', 40)]));
    }

    public function testRegeneratingTheKeyKillsTheOldOne(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook();
        $old = $this->keyOf($bookId);

        $regenerated = $this->api('POST', "/api/books/$bookId/api-key/regenerate");
        self::assertSame(200, $regenerated['status']);
        $new = $regenerated['data']['token'];
        self::assertNotSame($old, $new);
        self::assertSame($new, $this->keyOf($bookId), 'The book has one key, now with the new token.');
        $this->logout();

        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', "/api/books/$bookId/knowledge", null, ['X-API-Key' => $old]));
        self::assertSame(200, $this->api('GET', "/api/books/$bookId/knowledge", null, ['X-API-Key' => $new])['status']);
    }

    public function testDeletingTheBookDeletesItsKey(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook();
        $token = $this->keyOf($bookId);
        $this->api('DELETE', "/api/books/$bookId");
        $this->logout();

        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', "/api/books/$bookId/knowledge", null, ['X-API-Key' => $token]));
    }

    public function testUsingTheKeyRecordsWhenItWasLastUsed(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook();
        $token = $this->keyOf($bookId);
        $this->api('GET', "/api/books/$bookId/knowledge", null, ['X-API-Key' => $token]);

        self::assertNotNull($this->api('GET', "/api/books/$bookId/api-key")['data']['lastUsedAt']);
    }
}
