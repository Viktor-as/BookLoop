<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\BorrowBookService;
use App\Tests\Support\TestEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BorrowBookServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private UserPasswordHasherInterface $passwordHasher;

    private BorrowBookService $borrowBookService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->borrowBookService = $container->get(BorrowBookService::class);
    }

    public function testBorrowSuccessPersistsBorrow(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix, 'Secret123');
        $book = TestEntityFactory::persistBook($this->em, $suffix, 14);

        $result = $this->borrowBookService->borrow($member, $book->getSlug(), 7);

        self::assertTrue($result->ok);
        self::assertNotNull($result->borrowId);
        self::assertSame('Book borrowed successfully.', $result->message);
        self::assertNull($result->errorCode);
        self::assertNotNull($result->dueDate);
    }

    public function testBorrowNotFound(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix);

        $result = $this->borrowBookService->borrow($member, 'no-such-slug-' . $suffix, 7);

        self::assertFalse($result->ok);
        self::assertSame('not_found', $result->errorCode);
    }

    public function testOnLoanWhenAnotherMemberHasActiveBorrow(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $owner = TestEntityFactory::persistMember($this->em, $this->passwordHasher, 'a-' . $suffix);
        $other = TestEntityFactory::persistOtherMember($this->em, $this->passwordHasher, 'b-' . $suffix);
        $book = TestEntityFactory::persistBook($this->em, $suffix, 14);
        TestEntityFactory::persistActiveBorrow($this->em, $owner, $book);

        $result = $this->borrowBookService->borrow($other, $book->getSlug(), 7);

        self::assertFalse($result->ok);
        self::assertSame('on_loan', $result->errorCode);
    }

    public function testAlreadyBorrowedWhenSameMemberHasActiveLoan(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix);
        $book = TestEntityFactory::persistBook($this->em, $suffix, 14);
        TestEntityFactory::persistActiveBorrow($this->em, $member, $book);

        $result = $this->borrowBookService->borrow($member, $book->getSlug(), 7);

        self::assertFalse($result->ok);
        self::assertSame('already_borrowed', $result->errorCode);
    }

    public function testBorrowLimitReached(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix, 'Secret123', 1);
        $book1 = TestEntityFactory::persistBook($this->em, $suffix . '-1', 14);
        $book2 = TestEntityFactory::persistBook($this->em, $suffix . '-2', 14);
        TestEntityFactory::persistActiveBorrow($this->em, $member, $book1);

        $result = $this->borrowBookService->borrow($member, $book2->getSlug(), 7);

        self::assertFalse($result->ok);
        self::assertSame('borrow_limit', $result->errorCode);
    }

    public function testInvalidDaysAboveMax(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix);
        $book = TestEntityFactory::persistBook($this->em, $suffix, 5);

        $result = $this->borrowBookService->borrow($member, $book->getSlug(), 99);

        self::assertFalse($result->ok);
        self::assertSame('invalid_days', $result->errorCode);
    }

    public function testInvalidDaysBelowOne(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix);
        $book = TestEntityFactory::persistBook($this->em, $suffix, 14);

        $result = $this->borrowBookService->borrow($member, $book->getSlug(), 0);

        self::assertFalse($result->ok);
        self::assertSame('invalid_days', $result->errorCode);
    }
}
