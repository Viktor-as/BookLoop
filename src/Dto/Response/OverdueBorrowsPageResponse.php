<?php

namespace App\Dto\Response;

final readonly class OverdueBorrowsPageResponse
{
    /**
     * @param list<OverdueBorrowItemResponse> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {}
}
