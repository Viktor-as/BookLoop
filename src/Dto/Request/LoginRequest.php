<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body: {"email": "...", "password": "..."}.
 */
final class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Field "email" is required.')]
        #[Assert\Email(message: 'Field "email" must be a valid email address.')]
        #[Assert\Length(max: 180, maxMessage: 'Field "email" cannot exceed {{ limit }} characters.')]
        public readonly ?string $email = null,
        #[Assert\NotBlank(message: 'Field "password" is required.')]
        public readonly ?string $password = null,
    ) {}
}
