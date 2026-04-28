<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body: {"bookSlug": "<slug>", "days": <positive integer>}.
 */
final class BorrowBookRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Field "bookSlug" is required.')]
        #[Assert\Regex(
            pattern: '/^[a-z0-9\-]+$/',
            message: 'Field "bookSlug" must be a slug containing only lowercase letters, numbers, and dashes.',
        )]
        public readonly ?string $bookSlug = null,

        #[Assert\NotNull(message: 'Field "days" is required.')]
        #[Assert\Type(type: 'integer', message: 'Field "days" must be an integer.')]
        #[Assert\Positive(message: 'Field "days" must be a positive number.')]
        public readonly ?int $days = null,
    ) {}
}
