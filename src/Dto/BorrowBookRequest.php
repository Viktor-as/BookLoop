<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body: {"days": <positive integer>}.
 */
class BorrowBookRequest
{
    #[Assert\NotNull(message: 'Field "days" is required.')]
    #[Assert\Type('integer', message: 'Field "days" must be an integer.')]
    #[Assert\Positive(message: 'Field "days" must be a positive number.')]
    public ?int $days = null;
}
