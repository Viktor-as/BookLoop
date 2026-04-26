<?php

namespace App\Service;

use App\Dto\BorrowBookResult;
use App\Entity\Borrows;
use App\Entity\Users;
use App\Event\Borrowing\BookBorrowed;
use App\Repository\BooksRepository;
use App\Repository\BorrowsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class BorrowBookService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BooksRepository $booksRepository,
        private readonly BorrowsRepository $borrowsRepository,
        private readonly BorrowQuotaPresenter $borrowQuotaPresenter,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function borrow(Users $user, string $slug, int $days): BorrowBookResult
    {
        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use ($user, $slug, $days): BorrowBookResult {
            $book = $this->booksRepository->findOneBySlugForUpdate($slug);
            if ($book === null) {
                return BorrowBookResult::notFound();
            }

            $memberId = (int) $user->getId();
            $bookId    = (int) $book->getId();

            $loansForBook = $this->borrowsRepository->countActiveLoansForBook($bookId);
            if ($loansForBook > 0) {
                if ($this->borrowsRepository->hasActiveBorrowForMemberAndBook($memberId, $bookId)) {
                    return new BorrowBookResult(
                        false,
                        'You already have an active loan for this book.',
                        'already_borrowed',
                    );
                }

                return new BorrowBookResult(
                    false,
                    'This book is currently on loan to another member.',
                    'on_loan',
                );
            }

            $quota = $this->borrowQuotaPresenter->forMemberAndBook($user, $book->getBorrowDaysLimit());
            if ($quota['remainingBorrowSlots'] <= 0) {
                return new BorrowBookResult(
                    false,
                    'You have reached your borrow limit.',
                    'borrow_limit',
                );
            }

            $maxDays = $quota['effectiveMaxBorrowDays'];
            if ($days < 1 || $days > $maxDays) {
                return new BorrowBookResult(
                    false,
                    sprintf('Number of days must be between 1 and %d.', $maxDays),
                    'invalid_days',
                );
            }

            $now = new \DateTimeImmutable();
            $due = $now->modify(sprintf('+%d days', $days));

            $borrow = (new Borrows())
                ->setBook($book)
                ->setMember($user)
                ->setBorrowedAt($now)
                ->setDueDate($due)
                ->setReturnedAt(null);

            $em->persist($borrow);
            $em->flush();

            $this->eventDispatcher->dispatch(new BookBorrowed($borrow));

            return new BorrowBookResult(
                true,
                'Book borrowed successfully.',
                null,
                $due,
                (int) $borrow->getId(),
            );
        });
    }
}
