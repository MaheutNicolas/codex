<?php

namespace App\Api;

use App\Entity\ApiKey;
use App\Entity\Book;
use App\Entity\User;
use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Repository\BookRepository;
use App\Security\CurrentApiKey;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Gives any controller argument typed "Book" the book named by the {bookId} route parameter,
 * after checking that the current credentials may reach it. This is the single place where
 * access to a book is decided:
 * - a logged-in session reaches the books of its account;
 * - an API key reaches the book it was created for, and can only change it with the "write" scope.
 * A book the credentials cannot reach is reported as not found, so nobody can discover which books exist.
 */
#[AutoconfigureTag('controller.argument_value_resolver', ['priority' => 150])]
final class BookResolver implements ValueResolverInterface
{
    private const READ_METHODS = ['GET', 'HEAD'];

    public function __construct(
        private readonly BookRepository $books,
        private readonly Security $security,
        private readonly CurrentApiKey $currentKey,
    ) {
    }

    /** @return iterable<Book> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Book::class !== $argument->getType()) {
            return [];
        }

        $id = (string) $request->attributes->get('bookId');
        $book = ctype_digit($id) ? $this->books->find((int) $id) : null;
        $key = $this->currentKey->get();

        if (null === $book || !$this->canReach($book, $key)) {
            throw new ApiException(ErrorCode::BOOK_NOT_FOUND, ['id' => $id]);
        }

        if (null !== $key && !$key->canWrite() && !\in_array($request->getMethod(), self::READ_METHODS, true)) {
            throw new ApiException(ErrorCode::FORBIDDEN, [], 'This API key is read-only: it cannot create, modify or delete anything.');
        }

        return [$book];
    }

    private function canReach(Book $book, ?ApiKey $key): bool
    {
        if (null !== $key) {
            return $key->getBook()->getId() === $book->getId();
        }

        $user = $this->security->getUser();

        return $user instanceof User && $book->getUser()->getId() === $user->getId();
    }
}
