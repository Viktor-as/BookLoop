<?php

namespace App\Dto\Response;

final readonly class AuthSuccessResponse
{
    public function __construct(
        public string $message,
        public AuthUserResponse $user,
    ) {}
}
