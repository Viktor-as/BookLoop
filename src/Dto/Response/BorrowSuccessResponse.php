<?php

namespace App\Dto\Response;

final readonly class BorrowSuccessResponse
{
    public function __construct(
        public string $message,
        public int $borrowId,
        public ?string $dueDate,
    ) {}
}
