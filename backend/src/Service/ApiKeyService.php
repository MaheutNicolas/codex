<?php

namespace App\Service;

use App\Entity\ApiKey;
use App\Entity\Book;
use App\Entity\User;
use App\Repository\ApiKeyRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/** Business logic of the API key of a book (one per book). Returns plain arrays, ready to be sent as JSON. */
final class ApiKeyService
{
    private const TOKEN_PREFIX = 'cdx_';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApiKeyRepository $repository,
    ) {
    }

    /**
     * The key of the book, created on first use. A new key can only read: writing goes through the app.
     *
     * @return array<string, mixed>
     */
    public function get(User $user, Book $book): array
    {
        return $this->serialize($this->findOrCreate($user, $book));
    }

    /**
     * Replaces the token: the old one stops working immediately.
     *
     * @return array<string, mixed>
     */
    public function regenerate(User $user, Book $book): array
    {
        $key = $this->findOrCreate($user, $book);
        $key->setToken(self::newToken())->setLastUsedAt(null);
        $this->em->flush();

        return $this->serialize($key);
    }

    private function findOrCreate(User $user, Book $book): ApiKey
    {
        $key = $this->repository->findOneBy(['book' => $book]);
        if (null !== $key) {
            return $key;
        }

        $key = (new ApiKey())
            ->setUser($user)
            ->setBook($book)
            ->setToken(self::newToken())
            ->setScope(ApiKey::SCOPE_READ);

        try {
            $this->em->persist($key);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Two requests created the key at the same time: the other one won, use its key.
            $this->em->clear();

            return $this->repository->findOneBy(['book' => $book]);
        }

        return $key;
    }

    private static function newToken(): string
    {
        return self::TOKEN_PREFIX.bin2hex(random_bytes(20));
    }

    /** @return array<string, mixed> */
    private function serialize(ApiKey $key): array
    {
        return [
            'bookId' => $key->getBook()->getId(),
            'token' => $key->getToken(),
            'scope' => $key->getScope(),
            'createdAt' => $key->getCreatedAt()->format(\DATE_ATOM),
            'lastUsedAt' => $key->getLastUsedAt()?->format(\DATE_ATOM),
        ];
    }
}
