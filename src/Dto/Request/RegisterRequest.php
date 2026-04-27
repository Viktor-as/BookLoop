<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body: {"firstName": "...", "lastName": "...", "email": "...", "password": "..."}.
 */
final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Field "firstName" is required.')]
        #[Assert\Length(max: 100, maxMessage: 'Field "firstName" cannot exceed {{ limit }} characters.')]
        public readonly ?string $firstName = null,
        #[Assert\NotBlank(message: 'Field "lastName" is required.')]
        #[Assert\Length(max: 100, maxMessage: 'Field "lastName" cannot exceed {{ limit }} characters.')]
        public readonly ?string $lastName = null,
        #[Assert\NotBlank(message: 'Field "email" is required.')]
        #[Assert\Email(message: 'Field "email" must be a valid email address.')]
        #[Assert\Length(max: 180, maxMessage: 'Field "email" cannot exceed {{ limit }} characters.')]
        public readonly ?string $email = null,
        #[Assert\NotBlank(message: 'Field "password" is required.')]
        #[Assert\Length(min: 8, minMessage: 'Field "password" must be at least {{ limit }} characters long.')]
        public readonly ?string $password = null,
    ) {}
}
