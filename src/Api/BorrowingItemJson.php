<?php

namespace App\Api;

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
 *
 * @return array<string, mixed>
 */
final class BorrowingItemJson
{
    public static function encodeItem(array $row): array
    {
        return [
            'borrowId'   => $row['borrowId'],
            'bookId'     => $row['bookId'],
            'slug'       => $row['slug'],
            'title'      => $row['title'],
            'authors'    => $row['authors'],
            'categories' => $row['categories'],
            'borrowedAt' => $row['borrowedAt']->format(\DateTimeInterface::ATOM),
            'dueDate'    => $row['dueDate']->format(\DateTimeInterface::ATOM),
            'returnedAt' => $row['returnedAt']?->format(\DateTimeInterface::ATOM),
            'isActive'   => $row['isActive'],
        ];
    }
}
