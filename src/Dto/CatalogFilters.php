<?php

namespace App\Dto;

/**
 * Read-only filter set for the public book catalog API.
 */
final readonly class CatalogFilters
{
    public function __construct(
        public ?string $title,
        public ?string $author,
        public ?int $categoryId,
        public bool $onlyAvailable,
    ) {}

    public static function likeEscape(string $fragment): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $fragment);
    }
}
