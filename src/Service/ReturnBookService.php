<?php

namespace App\Service;

use App\Dto\ReturnBookResult;
use App\Entity\Users;
use App\Event\Borrowing\BookReturned;
use App\Repository\BorrowsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ReturnBookService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BorrowsRepository $borrowsRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function returnForMember(Users $user, int $borrowId): ReturnBookResult
    {
        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use ($user, $borrowId): ReturnBookResult {
            $borrow = $this->borrowsRepository->find($borrowId);
            if ($borrow === null) {
                return new ReturnBookResult(false, 'Borrow not found.', 'borrow_not_found');
            }

            if ((int) $borrow->getMember()?->getId() !== (int) $user->getId()) {
                return new ReturnBookResult(false, 'You cannot return this loan.', 'return_forbidden');
            }

            if (!$borrow->isActive()) {
                return new ReturnBookResult(
                    false,
                    'This book has already been returned.',
                    'already_returned',
                );
            }

            $borrow->setReturnedAt(new \DateTimeImmutable());
            $em->flush();

            $this->eventDispatcher->dispatch(new BookReturned($borrow));

            return new ReturnBookResult(
                true,
                'Book returned successfully.',
                null,
                $borrow,
            );
        });
    }
}
