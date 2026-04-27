<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\Request\BorrowBookRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class BorrowBookRequestValidationTest extends TestCase
{
    private function validator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testDaysRequired(): void
    {
        $input = new BorrowBookRequest();
        $violations = $this->validator()->validate($input);
        self::assertGreaterThan(0, $violations->count());
        self::assertStringContainsString('days', (string) $violations[0]->getPropertyPath());
    }

    public function testDaysMustBePositiveInteger(): void
    {
        $input = new BorrowBookRequest(days: 0);
        $violations = $this->validator()->validate($input);
        self::assertGreaterThan(0, $violations->count());
    }

    public function testValidDaysPasses(): void
    {
        $input = new BorrowBookRequest(days: 7);
        $violations = $this->validator()->validate($input);
        self::assertCount(0, $violations);
    }
}
