<?php

namespace App\Dto\Response;

final readonly class CatalogItemResponse
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public ?string $authors,
        public ?string $categories,
        public bool $available,
        public bool $borrowedByMe,
    ) {}

    /**
     * @param array{
     *     id: int,
     *     slug: string,
     *     title: string,
     *     authors: string|null,
     *     categories: string|null,
     *     available: bool,
     *     borrowedByMe?: bool
     * } $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: $row['id'],
            slug: $row['slug'],
            title: $row['title'],
            authors: $row['authors'],
            categories: $row['categories'],
            available: $row['available'],
            borrowedByMe: $row['borrowedByMe'] ?? false,
        );
    }
}
