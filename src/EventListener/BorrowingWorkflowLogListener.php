<?php

namespace App\EventListener;

use App\Event\Borrowing\BookBorrowed;
use App\Event\Borrowing\BookReturned;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Hook for borrow/return workflow; extend for email, audit, etc.
 */
final class BorrowingWorkflowLogListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[AsEventListener(event: BookBorrowed::class)]
    public function onBookBorrowed(BookBorrowed $event): void
    {
        $id = $event->borrow->getId();
        $this->logger->info('Book borrowed', [
            'borrowId' => $id,
        ]);
    }

    #[AsEventListener(event: BookReturned::class)]
    public function onBookReturned(BookReturned $event): void
    {
        $id = $event->borrow->getId();
        $this->logger->info('Book returned', [
            'borrowId' => $id,
        ]);
    }
}
