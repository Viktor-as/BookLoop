<?php

namespace App\Dto\Response;

final readonly class BorrowReturnSuccessResponse
{
    public function __construct(
        public string $message,
        public ?BorrowingItemResponse $item,
    ) {}
}
