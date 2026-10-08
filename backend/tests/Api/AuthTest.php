<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

/** Who can reach what: public routes, login, and the isolation between accounts. */
final class AuthTest extends ApiTestCase
{
    public function testPublicRoutesNeedNoLogin(): void
    {
        self::assertSame(200, $this->api('GET', '/health')['status']);

        $errors = $this->api('GET', '/api/errors');
        self::assertSame(200, $errors['status']);
        self::assertNotEmpty($errors['data']['data'] ?? $errors['data']);
    }

    public function testEverythingElseNeedsALogin(): void
    {
        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', '/api/books'));
        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', '/api/books/1/knowledge'));
        $this->assertError(401, 'UNAUTHORIZED', $this->api('POST', '/api/books/1/import', []));
    }

    public function testLoginWithTheRightPasswordOpensASession(): void
    {
        $username = $this->createUser();

        $login = $this->api('POST', '/api/auth/login', ['username' => $username, 'password' => self::PASSWORD]);
        self::assertSame(200, $login['status']);

        $me = $this->api('GET', '/api/auth/me');
        self::assertSame(200, $me['status']);
        self::assertSame($username, $me['data']['username']);
    }

    public function testLoginFailsTheSameWayForAWrongPasswordAndAnUnknownAccount(): void
    {
        $username = $this->createUser();

        $wrongPassword = $this->api('POST', '/api/auth/login', ['username' => $username, 'password' => 'not-the-password']);
        $unknown = $this->api('POST', '/api/auth/login', ['username' => 'nobody_'.bin2hex(random_bytes(4)), 'password' => 'whatever-123']);

        $this->assertError(401, 'LOGIN_FAILED', $wrongPassword);
        $this->assertError(401, 'LOGIN_FAILED', $unknown);
        self::assertSame($wrongPassword['data'], $unknown['data'], 'The two answers must be identical so that nobody can guess which accounts exist.');
    }

    public function testLoginWithoutCredentialsIsInvalidJson(): void
    {
        $this->assertError(400, 'INVALID_JSON', $this->api('POST', '/api/auth/login', ['username' => 'someone']));
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->loginAsNewUser();
        self::assertSame(200, $this->api('GET', '/api/books')['status']);

        $this->api('POST', '/api/auth/logout');

        $this->assertError(401, 'UNAUTHORIZED', $this->api('GET', '/api/books'));
    }

    public function testAnAccountNeverSeesTheBooksOfAnother(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook('Private book');
        $this->api('POST', "/api/books/$bookId/knowledge", ['id' => 'secret', 'type' => 'character', 'name' => 'Secret', 'summary' => 's']);
        $this->logout();

        $this->loginAsNewUser();

        // The book is reported as not found, whatever the route, so that nobody can tell it exists.
        $this->assertError(404, 'BOOK_NOT_FOUND', $this->api('GET', "/api/books/$bookId"));
        $this->assertError(404, 'BOOK_NOT_FOUND', $this->api('GET', "/api/books/$bookId/knowledge"));
        $this->assertError(404, 'BOOK_NOT_FOUND', $this->api('GET', "/api/books/$bookId/export"));
        $this->assertError(404, 'BOOK_NOT_FOUND', $this->api('POST', "/api/books/$bookId/import", ['knowledge' => []]));
        $this->assertError(404, 'BOOK_NOT_FOUND', $this->api('DELETE', "/api/books/$bookId"));

        $list = $this->api('GET', '/api/books');
        self::assertSame(0, $list['data']['total']);
    }

    public function testBooksCanBeRenamedAndDeletedByTheirOwner(): void
    {
        $this->loginAsNewUser();
        $bookId = $this->createBook('Old name');

        $renamed = $this->api('PATCH', "/api/books/$bookId", ['name' => 'New name']);
        self::assertSame(200, $renamed['status']);
        self::assertSame('New name', $renamed['data']['name']);

        self::assertSame(204, $this->api('DELETE', "/api/books/$bookId")['status']);
        $this->assertError(404, 'BOOK_NOT_FOUND', $this->api('GET', "/api/books/$bookId"));
    }
}
