<?php

namespace App\Repository;

use App\Entity\ApiKey;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ApiKey> */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function findByTokenHash(string $tokenHash): ?ApiKey
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /** @return list<ApiKey> */
    public function findByBook(Book $book): array
    {
        return $this->findBy(['book' => $book], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** A key is only visible to the account that owns it. */
    public function findOwned(User $user, int $id): ?ApiKey
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }
}
