<?php

namespace App\Dto;

final readonly class BorrowBookResult
{
    public function __construct(
        public bool $ok,
        public string $message,
        public ?string $errorCode = null,
        public ?\DateTimeImmutable $dueDate = null,
    ) {}

    public static function notFound(): self
    {
        return new self(false, 'Book not found.', 'not_found');
    }
}
