<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\Request\LoginRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LoginRequestValidationTest extends TestCase
{
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testEmailAndPasswordAreRequired(): void
    {
        $input = new LoginRequest();
        $violations = $this->validator()->validate($input);

        self::assertGreaterThanOrEqual(2, $violations->count());

        $fields = [];
        foreach ($violations as $v) {
            $fields[(string) $v->getPropertyPath()] = true;
        }
        self::assertArrayHasKey('email', $fields);
        self::assertArrayHasKey('password', $fields);
    }

    public function testInvalidEmailFails(): void
    {
        $input = new LoginRequest(email: 'not-an-email', password: 'secret');
        $violations = $this->validator()->validate($input);
        self::assertGreaterThan(0, $violations->count());
    }

    public function testValidPayloadPasses(): void
    {
        $input = new LoginRequest(email: 'user@example.com', password: 'secret');
        $violations = $this->validator()->validate($input);
        self::assertCount(0, $violations);
    }

    public function testEmailLengthLimit(): void
    {
        $longLocal = str_repeat('a', 200);
        $input = new LoginRequest(email: $longLocal . '@example.com', password: 'secret');
        $violations = $this->validator()->validate($input);
        self::assertGreaterThan(0, $violations->count());
    }
}
