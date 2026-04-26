<?php

namespace App\Event\Borrowing;

use App\Entity\Borrows;
use Symfony\Contracts\EventDispatcher\Event;

final class BookBorrowed extends Event
{
    public function __construct(
        public readonly Borrows $borrow,
    ) {
    }
}
