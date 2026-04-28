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
        $input = new BorrowBookRequest(bookSlug: 'clean-code');
        $violations = $this->validator()->validate($input);
        self::assertGreaterThan(0, $violations->count());
        self::assertContains(
            'days',
            array_map(
                static fn ($violation) => (string) $violation->getPropertyPath(),
                iterator_to_array($violations)
            )
        );
    }

    public function testDaysMustBePositiveInteger(): void
    {
        $input = new BorrowBookRequest(bookSlug: 'clean-code', days: 0);
        $violations = $this->validator()->validate($input);
        self::assertGreaterThan(0, $violations->count());
    }

    public function testValidDaysPasses(): void
    {
        $input = new BorrowBookRequest(bookSlug: 'clean-code', days: 7);
        $violations = $this->validator()->validate($input);
        self::assertCount(0, $violations);
    }
}
