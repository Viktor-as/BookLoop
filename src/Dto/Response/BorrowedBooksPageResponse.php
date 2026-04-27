<?php

namespace App\Dto\Response;

final readonly class BorrowedBooksPageResponse
{
    /**
     * @param list<BorrowingItemResponse> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}
