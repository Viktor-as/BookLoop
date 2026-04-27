<?php

namespace App\Dto\Response;

final readonly class BorrowingItemResponse
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
        public ?string $returnedAt,
        public bool $isActive,
    ) {}

    /**
     * @param array{
     *     borrowId: int,
     *     bookId: int,
     *     slug: string,
     *     title: string,
     *     authors: string|null,
     *     categories: string|null,
     *     borrowedAt: \DateTimeImmutable,
     *     dueDate: \DateTimeImmutable,
     *     returnedAt: \DateTimeImmutable|null,
     *     isActive: bool
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
            borrowedAt: $row['borrowedAt']->format(\DateTimeInterface::ATOM),
            dueDate: $row['dueDate']->format(\DateTimeInterface::ATOM),
            returnedAt: $row['returnedAt']?->format(\DateTimeInterface::ATOM),
            isActive: $row['isActive'],
        );
    }
}
