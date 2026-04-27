<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body: {"days": <positive integer>}.
 */
final class BorrowBookRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Field "days" is required.')]
        #[Assert\Type(type: 'integer', message: 'Field "days" must be an integer.')]
        #[Assert\Positive(message: 'Field "days" must be a positive number.')]
        public readonly ?int $days = null,
    ) {}
}
