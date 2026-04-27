<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\Request\RegisterRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterRequestValidationTest extends TestCase
{
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testAllFieldsAreRequired(): void
    {
        $input = new RegisterRequest();
        $violations = $this->validator()->validate($input);

        $fields = [];
        foreach ($violations as $v) {
            $fields[(string) $v->getPropertyPath()] = true;
        }

        foreach (['firstName', 'lastName', 'email', 'password'] as $expected) {
            self::assertArrayHasKey($expected, $fields, sprintf('Expected violation for "%s".', $expected));
        }
    }

    public function testPasswordTooShortFails(): void
    {
        $input = new RegisterRequest(
            firstName: 'Jane',
            lastName: 'Doe',
            email: 'jane@example.com',
            password: 'short',
        );
        $violations = $this->validator()->validate($input);

        $hasPasswordViolation = false;
        foreach ($violations as $v) {
            if ((string) $v->getPropertyPath() === 'password') {
                $hasPasswordViolation = true;
                break;
            }
        }
        self::assertTrue($hasPasswordViolation, 'Password shorter than 8 characters must produce a violation.');
    }

    public function testValidPayloadPasses(): void
    {
        $input = new RegisterRequest(
            firstName: 'Jane',
            lastName: 'Doe',
            email: 'jane@example.com',
            password: 'password12',
        );
        $violations = $this->validator()->validate($input);
        self::assertCount(0, $violations);
    }
}
