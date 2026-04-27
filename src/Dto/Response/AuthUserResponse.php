<?php

namespace App\Dto\Response;

use App\Entity\Users;

final readonly class AuthUserResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $role,
    ) {}

    public static function fromUser(Users $user): self
    {
        return new self(
            id: (int) $user->getId(),
            firstName: (string) $user->getFirstName(),
            lastName: (string) $user->getLastName(),
            email: (string) $user->getEmail(),
            role: (string) $user->getRole(),
        );
    }
}
