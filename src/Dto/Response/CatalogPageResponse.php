<?php

namespace App\Dto\Response;

final readonly class CatalogPageResponse
{
    /**
     * @param list<CatalogItemResponse> $items
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
        public int $lastPage,
        public array $items,
    ) {}
}
