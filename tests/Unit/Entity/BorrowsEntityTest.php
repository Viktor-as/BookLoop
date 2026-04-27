<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Books;
use App\Entity\Borrows;
use App\Entity\Users;
use PHPUnit\Framework\TestCase;

final class BorrowsEntityTest extends TestCase
{
    public function testIsActiveWhenReturnedAtIsNull(): void
    {
        $borrow = (new Borrows())
            ->setReturnedAt(null);

        self::assertTrue($borrow->isActive());
    }

    public function testIsNotActiveWhenReturnedAtIsSet(): void
    {
        $borrow = (new Borrows())
            ->setReturnedAt(new \DateTimeImmutable());

        self::assertFalse($borrow->isActive());
    }

    public function testIsOverdueWhenActiveAndDueDateInPast(): void
    {
        $borrow = (new Borrows())
            ->setReturnedAt(null)
            ->setDueDate((new \DateTimeImmutable())->modify('-1 day'));

        self::assertTrue($borrow->isOverdue());
    }

    public function testIsNotOverdueWhenDueDateInFuture(): void
    {
        $borrow = (new Borrows())
            ->setReturnedAt(null)
            ->setDueDate((new \DateTimeImmutable())->modify('+7 days'));

        self::assertFalse($borrow->isOverdue());
    }

    public function testReturnedBorrowIsNotOverdueEvenIfDueDatePast(): void
    {
        $borrow = (new Borrows())
            ->setReturnedAt(new \DateTimeImmutable())
            ->setDueDate((new \DateTimeImmutable())->modify('-30 days'));

        self::assertFalse($borrow->isOverdue());
    }

    public function testFluentSettersReturnSameInstance(): void
    {
        $book = $this->createMock(Books::class);
        $member = $this->createMock(Users::class);
        $borrow = new Borrows();
        $at = new \DateTimeImmutable();
        $due = $at->modify('+10 days');

        $chain = $borrow
            ->setBook($book)
            ->setMember($member)
            ->setBorrowedAt($at)
            ->setDueDate($due)
            ->setReturnedAt(null);

        self::assertSame($borrow, $chain);
    }
}
