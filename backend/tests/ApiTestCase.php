<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base of the API tests. They run against a real MySQL/MariaDB database (the full-text search and the
 * transactions of the import cannot be tested on anything else) and really commit, so each test creates its
 * own accounts and removes them at the end (the database deletes their books and content in cascade).
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'test-password-123';

    protected KernelBrowser $client;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        // Never run against a real database: the tests delete the accounts they create.
        $name = $this->connection()->getDatabase();
        self::assertStringEndsWith('_test', (string) $name, 'The tests must run on a database whose name ends with "_test".');
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        foreach ($this->createdUserIds as $id) {
            $connection->executeStatement('DELETE FROM user WHERE id = ?', [$id]);
        }
        $this->createdUserIds = [];

        parent::tearDown();
    }

    protected function connection(): Connection
    {
        return static::getContainer()->get('doctrine')->getConnection();
    }

    // ---- Accounts ----------------------------------------------------------------------------------

    /** Creates an account directly in the database. Returns its username. */
    protected function createUser(): string
    {
        $username = 't_'.bin2hex(random_bytes(6));

        $user = (new User())->setUsername($username);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();
        $this->createdUserIds[] = $user->getId();

        return $username;
    }

    /** Creates an account and logs in with it. Returns its username. */
    protected function loginAsNewUser(): string
    {
        $username = $this->createUser();
        $response = $this->api('POST', '/api/auth/login', ['username' => $username, 'password' => self::PASSWORD]);
        self::assertSame(200, $response['status'], 'The login of a new account must work.');

        return $username;
    }

    protected function logout(): void
    {
        $this->client->getCookieJar()->clear();
    }

    // ---- Requests ----------------------------------------------------------------------------------

    /**
     * Sends a JSON request. Returns the status, the decoded body (null when empty) and the response headers.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers HTTP headers, e.g. ['X-API-Key' => 'cdx_…']
     *
     * @return array{status: int, data: mixed, headers: array<string, list<string>>}
     */
    protected function api(string $method, string $uri, ?array $body = null, array $headers = []): array
    {
        return $this->send($method, $uri, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR), $headers);
    }

    /**
     * Sends a request with a raw body (to test invalid JSON).
     *
     * @param array<string, string> $headers
     *
     * @return array{status: int, data: mixed, headers: array<string, list<string>>}
     */
    protected function send(string $method, string $uri, ?string $content, array $headers = []): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request($method, $uri, [], [], $server, $content);
        $response = $this->client->getResponse();
        $text = (string) $response->getContent();

        return [
            'status' => $response->getStatusCode(),
            'data' => '' === $text ? null : json_decode($text, true),
            'headers' => $response->headers->all(),
        ];
    }

    /** The error code of an API error response, or null. */
    protected function errorCode(array $response): ?string
    {
        return $response['data']['error']['code'] ?? null;
    }

    protected function assertError(int $status, string $code, array $response): void
    {
        self::assertSame($status, $response['status'], 'Unexpected status: '.json_encode($response['data']));
        self::assertSame($code, $this->errorCode($response), 'Unexpected error: '.json_encode($response['data']));
    }

    // ---- Books and content -----------------------------------------------------------------------

    /** Creates a book for the logged-in account. Returns its id. */
    protected function createBook(string $name = 'Test Book'): int
    {
        $response = $this->api('POST', '/api/books', ['name' => $name]);
        self::assertSame(201, $response['status']);

        return $response['data']['id'];
    }

    /**
     * Imports a document into a book (the quickest way to set up data) and checks that it worked.
     *
     * @param array<string, list<array<string, mixed>>> $document
     */
    protected function import(int $bookId, array $document): void
    {
        $response = $this->api('POST', "/api/books/$bookId/import", $document);
        self::assertSame(200, $response['status'], 'The import must work: '.json_encode($response['data']));
    }

    /** The token of the key of a book. */
    protected function keyOf(int $bookId): string
    {
        $response = $this->api('GET', "/api/books/$bookId/api-key");
        self::assertSame(200, $response['status']);

        return $response['data']['token'];
    }

    /**
     * A small story used by several tests: chapters 1, 3 and 7 are told, one event is secret (told in chapter 2
     * but unknown to the reader) and one is never told (off-screen).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function story(): array
    {
        return [
            'knowledge' => [
                ['id' => 'aldric', 'type' => 'character', 'name' => 'Aldric', 'summary' => 'A knight of the north.', 'description' => 'Aldric swore an oath to protect the citadel.', 'aliases' => ['the One-Eyed', 'Zoé']],
                ['id' => 'mira', 'type' => 'character', 'name' => 'Mira', 'summary' => 'A healer.'],
                ['id' => 'citadel', 'type' => 'place', 'name' => 'North Citadel', 'summary' => 'A fortress.'],
            ],
            'events' => [
                ['id' => 'evt-1', 'title' => 'The oath', 'summary' => 'Aldric swears loyalty.', 'worldOrder' => 1, 'chapter' => 1, 'revealed' => true, 'tags' => ['vow']],
                ['id' => 'evt-2', 'title' => 'The feast', 'summary' => 'A dinner at the citadel.', 'worldOrder' => 2, 'chapter' => 3, 'revealed' => true],
                ['id' => 'evt-3', 'title' => 'The betrayal', 'summary' => 'The council betrays Aldric.', 'worldOrder' => 3, 'chapter' => 7, 'revealed' => true],
                ['id' => 'evt-4', 'title' => 'The hidden twist', 'summary' => 'A twist told in chapter 2 but unknown to the reader.', 'worldOrder' => 4, 'chapter' => 2, 'revealed' => false],
                ['id' => 'evt-0', 'title' => 'The poisoning', 'summary' => 'Mira secretly poisons the well.', 'worldOrder' => 0, 'chapter' => null, 'revealed' => false],
            ],
            'participants' => [
                ['eventId' => 'evt-1', 'knowledgeId' => 'aldric', 'role' => 'author'],
                ['eventId' => 'evt-1', 'knowledgeId' => 'citadel', 'role' => 'place'],
                ['eventId' => 'evt-2', 'knowledgeId' => 'mira'],
                ['eventId' => 'evt-3', 'knowledgeId' => 'aldric', 'role' => 'victim'],
                ['eventId' => 'evt-4', 'knowledgeId' => 'aldric'],
                ['eventId' => 'evt-0', 'knowledgeId' => 'mira', 'role' => 'author'],
            ],
        ];
    }

    /**
     * The story of Aldric, for the tests about relations. Allies with Mira from the start, enemies from chapter 6, no
     * longer linked from chapter 9; mentored by Corvin from chapter 2; friends with Zed from chapter 1 but secretly his
     * enemy from chapter 4; in the service of the citadel from the start. He shares an event with Mira (chapter 1),
     * with Loner (chapter 3) and with Corvin (chapter 5); Loner has no relation with him.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function aldricStory(): array
    {
        return [
            'knowledge' => [
                ['id' => 'aldric', 'type' => 'character', 'name' => 'Aldric', 'summary' => 's'],
                ['id' => 'mira', 'type' => 'character', 'name' => 'Mira', 'summary' => 's'],
                ['id' => 'corvin', 'type' => 'character', 'name' => 'Corvin', 'summary' => 's'],
                ['id' => 'zed', 'type' => 'character', 'name' => 'Zed', 'summary' => 's'],
                ['id' => 'loner', 'type' => 'character', 'name' => 'Loner', 'summary' => 's'],
                ['id' => 'citadel', 'type' => 'place', 'name' => 'North Citadel', 'summary' => 's'],
            ],
            'events' => [
                ['id' => 'e1', 'title' => 'One', 'summary' => 's', 'worldOrder' => 1, 'chapter' => 1],
                ['id' => 'e2', 'title' => 'Two', 'summary' => 's', 'worldOrder' => 2, 'chapter' => 3],
                ['id' => 'e3', 'title' => 'Three', 'summary' => 's', 'worldOrder' => 3, 'chapter' => 5],
            ],
            'participants' => [
                ['eventId' => 'e1', 'knowledgeId' => 'aldric'], ['eventId' => 'e1', 'knowledgeId' => 'mira'],
                ['eventId' => 'e2', 'knowledgeId' => 'aldric'], ['eventId' => 'e2', 'knowledgeId' => 'loner'],
                ['eventId' => 'e3', 'knowledgeId' => 'aldric'], ['eventId' => 'e3', 'knowledgeId' => 'corvin'],
            ],
            'relations' => [
                ['id' => 'rel-a', 'sourceId' => 'aldric', 'targetId' => 'mira', 'type' => 'ally', 'chapter' => null],
                ['id' => 'rel-b', 'sourceId' => 'aldric', 'targetId' => 'mira', 'type' => 'enemy', 'chapter' => 6, 'note' => 'The betrayal.'],
                ['id' => 'rel-c', 'sourceId' => 'mira', 'targetId' => 'aldric', 'type' => 'none', 'chapter' => 9],
                ['id' => 'rel-d', 'sourceId' => 'corvin', 'targetId' => 'aldric', 'type' => 'mentor', 'chapter' => 2],
                ['id' => 'rel-e', 'sourceId' => 'aldric', 'targetId' => 'citadel', 'type' => 'serves', 'chapter' => null],
                ['id' => 'rel-f', 'sourceId' => 'aldric', 'targetId' => 'zed', 'type' => 'friend', 'chapter' => 1],
                ['id' => 'rel-g', 'sourceId' => 'aldric', 'targetId' => 'zed', 'type' => 'enemy', 'chapter' => 4, 'revealed' => false],
            ],
        ];
    }

    /**
     * The ids of the items of a list response, in order.
     *
     * @return list<string>
     */
    protected function ids(array $response): array
    {
        return array_column($response['data']['data'], 'id');
    }
}
