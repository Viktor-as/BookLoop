<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body: {"returned": true}.
 */
final class BorrowPatchRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Field "returned" is required.')]
        #[Assert\IdenticalTo(
            value: true,
            message: 'Field "returned" must be true to mark a borrow as returned.',
        )]
        public readonly ?bool $returned = null,
    ) {}
}
