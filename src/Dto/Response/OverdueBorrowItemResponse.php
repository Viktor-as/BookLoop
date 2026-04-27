<?php

namespace App\Dto\Response;

final readonly class OverdueBorrowItemResponse
{
    public function __construct(
        public int $borrowId,
        public int $bookId,
        public string $slug,
        public string $title,
        public ?string $authors,
        public ?string $categories,
        public string $borrowedAt,
        public string $dueDate,
        public string $memberName,
    ) {}

    /**
     * @param array{
     *     borrowId: int,
     *     bookId: int,
     *     slug: string,
     *     title: string,
     *     authors: string|null,
     *     categories: string|null,
     *     borrowedAt: string,
     *     dueDate: string,
     *     memberName: string
     * } $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            borrowId: $row['borrowId'],
            bookId: $row['bookId'],
            slug: $row['slug'],
            title: $row['title'],
            authors: $row['authors'],
            categories: $row['categories'],
            borrowedAt: $row['borrowedAt'],
            dueDate: $row['dueDate'],
            memberName: $row['memberName'],
        );
    }
}
