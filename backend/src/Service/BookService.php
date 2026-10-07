<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Validation\Validate;
use Doctrine\ORM\EntityManagerInterface;

/** Business logic of the books of an account. Returns plain arrays, ready to be sent as JSON. */
final class BookService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookRepository $repository,
        private readonly Validate $validate,
        private readonly Hydrator $hydrator,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int} */
    public function list(User $user): array
    {
        $books = array_map($this->serialize(...), $this->repository->findByUser($user));

        return ['data' => $books, 'total' => \count($books)];
    }

    /** @return array<string, mixed> */
    public function get(Book $book): array
    {
        return $this->serialize($book);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(User $user, array $data): array
    {
        $this->validate->book($data);

        $book = (new Book())->setUser($user);
        $this->hydrator->apply($book, $data);
        $this->validate->entity($book);

        $this->em->persist($book);
        $this->em->flush();

        return $this->serialize($book);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(Book $book, array $data): array
    {
        $this->validate->book($data);
        $this->hydrator->apply($book, $data);
        $this->validate->entity($book);
        $this->em->flush();

        return $this->serialize($book);
    }

    public function delete(Book $book): void
    {
        // Its knowledge, events, participants and API keys are removed by the database (ON DELETE CASCADE).
        $this->em->remove($book);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function serialize(Book $book): array
    {
        return [
            'id' => $book->getId(),
            'name' => $book->getName(),
            'createdAt' => $book->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $book->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }
}
