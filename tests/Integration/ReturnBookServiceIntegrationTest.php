<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\ReturnBookService;
use App\Tests\Support\TestEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ReturnBookServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private UserPasswordHasherInterface $passwordHasher;

    private ReturnBookService $returnBookService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->returnBookService = $container->get(ReturnBookService::class);
    }

    public function testReturnSuccess(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix);
        $book = TestEntityFactory::persistBook($this->em, $suffix, 14);
        $borrow = TestEntityFactory::persistActiveBorrow($this->em, $member, $book);
        $borrowId = (int) $borrow->getId();

        $result = $this->returnBookService->returnForMember($member, $borrowId);

        self::assertTrue($result->ok);
        self::assertNull($result->errorCode);
        self::assertNotNull($result->borrow);
        self::assertNotNull($result->borrow->getReturnedAt());
    }

    public function testBorrowNotFound(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix);

        $result = $this->returnBookService->returnForMember($member, 999999999);

        self::assertFalse($result->ok);
        self::assertSame('borrow_not_found', $result->errorCode);
    }

    public function testReturnForbiddenForAnotherMemberLoan(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $owner = TestEntityFactory::persistMember($this->em, $this->passwordHasher, 'own-' . $suffix);
        $intruder = TestEntityFactory::persistOtherMember($this->em, $this->passwordHasher, 'intr-' . $suffix);
        $book = TestEntityFactory::persistBook($this->em, $suffix, 14);
        $borrow = TestEntityFactory::persistActiveBorrow($this->em, $owner, $book);

        $result = $this->returnBookService->returnForMember($intruder, (int) $borrow->getId());

        self::assertFalse($result->ok);
        self::assertSame('return_forbidden', $result->errorCode);
    }

    public function testAlreadyReturned(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $member = TestEntityFactory::persistMember($this->em, $this->passwordHasher, $suffix);
        $book = TestEntityFactory::persistBook($this->em, $suffix, 14);
        $borrow = TestEntityFactory::persistActiveBorrow($this->em, $member, $book);
        $borrowId = (int) $borrow->getId();

        $first = $this->returnBookService->returnForMember($member, $borrowId);
        self::assertTrue($first->ok);

        $second = $this->returnBookService->returnForMember($member, $borrowId);
        self::assertFalse($second->ok);
        self::assertSame('already_returned', $second->errorCode);
    }
}
