<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\BorrowBookResult;
use PHPUnit\Framework\TestCase;

final class BorrowBookResultTest extends TestCase
{
    public function testNotFoundFactory(): void
    {
        $r = BorrowBookResult::notFound();
        self::assertFalse($r->ok);
        self::assertSame('not_found', $r->errorCode);
        self::assertSame('Book not found.', $r->message);
        self::assertNull($r->dueDate);
        self::assertNull($r->borrowId);
    }
}
