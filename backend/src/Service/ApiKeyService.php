<?php

namespace App\Service;

use App\Entity\ApiKey;
use App\Entity\Book;
use App\Entity\User;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\ApiKeyRepository;
use App\Validation\Validate;
use Doctrine\ORM\EntityManagerInterface;

/** Business logic of the API keys of a book. Returns plain arrays, ready to be sent as JSON. */
final class ApiKeyService
{
    private const TOKEN_PREFIX = 'cdx_';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApiKeyRepository $repository,
        private readonly Validate $validate,
        private readonly Hydrator $hydrator,
    ) {
    }

    /** The tokens are random and long, so a plain SHA-256 is enough to store them. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return array{data: list<array<string, mixed>>, total: int} */
    public function list(Book $book): array
    {
        $keys = array_map($this->serialize(...), $this->repository->findByBook($book));

        return ['data' => $keys, 'total' => \count($keys)];
    }

    /**
     * Creates a key. The response carries the token, which is never shown again afterwards.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(User $user, Book $book, array $data): array
    {
        $this->validate->apiKey($data);

        $token = self::TOKEN_PREFIX.bin2hex(random_bytes(20));

        $key = (new ApiKey())
            ->setUser($user)
            ->setBook($book)
            ->setTokenHash(self::hashToken($token))
            ->setPrefix(substr($token, 0, 12));
        $this->hydrator->apply($key, $data);
        $this->validate->entity($key);

        $this->em->persist($key);
        $this->em->flush();

        return $this->serialize($key) + ['token' => $token];
    }

    public function delete(User $user, int $id): void
    {
        $key = $this->repository->findOwned($user, $id)
            ?? throw new ApiException(ErrorCode::API_KEY_NOT_FOUND, ['id' => $id]);

        $this->em->remove($key);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function serialize(ApiKey $key): array
    {
        return [
            'id' => $key->getId(),
            'bookId' => $key->getBook()->getId(),
            'name' => $key->getName(),
            'prefix' => $key->getPrefix(),
            'scope' => $key->getScope(),
            'createdAt' => $key->getCreatedAt()->format(\DATE_ATOM),
            'lastUsedAt' => $key->getLastUsedAt()?->format(\DATE_ATOM),
        ];
    }
}
