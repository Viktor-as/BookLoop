<?php

namespace App\Dto;

use App\Entity\Borrows;

final readonly class ReturnBookResult
{
    public function __construct(
        public bool $ok,
        public string $message,
        public ?string $errorCode = null,
        public ?Borrows $borrow = null,
    ) {}
}
