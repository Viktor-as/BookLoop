<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Books;
use App\Entity\Borrows;
use App\Entity\Settings;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Minimal persisted entities for integration / functional tests.
 * Prefer unique emails/slugs per test via $suffix (e.g. bin2hex(random_bytes(4))).
 */
final class TestEntityFactory
{
    public static function persistMember(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        string $emailSuffix,
        string $plainPassword = 'TestMember123',
        ?int $borrowLimit = 10,
    ): Users {
        $email = 'member-' . $emailSuffix . '@test.example';
        $user = (new Users())
            ->setFirstName('Test')
            ->setLastName('Member')
            ->setEmail($email)
            ->setRole(Users::ROLE_MEMBER)
            ->setBorrowLimit($borrowLimit);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public static function persistOtherMember(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        string $emailSuffix,
        string $plainPassword = 'OtherMember123',
    ): Users {
        $email = 'other-' . $emailSuffix . '@test.example';
        $user = (new Users())
            ->setFirstName('Other')
            ->setLastName('Member')
            ->setEmail($email)
            ->setRole(Users::ROLE_MEMBER)
            ->setBorrowLimit(10);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public static function persistBook(
        EntityManagerInterface $em,
        string $slugSuffix,
        ?int $borrowDaysLimit = 14,
    ): Books {
        $slug = 'test-book-' . $slugSuffix;
        $book = (new Books())
            ->setTitle('Test Book ' . $slugSuffix)
            ->setSlug($slug)
            ->setDescription('Test description')
            ->setBorrowDaysLimit($borrowDaysLimit);
        $em->persist($book);
        $em->flush();

        return $book;
    }

    public static function persistSetting(
        EntityManagerInterface $em,
        string $key,
        string $value,
    ): Settings {
        $row = (new Settings())
            ->setKey($key)
            ->setValue($value);
        $em->persist($row);
        $em->flush();

        return $row;
    }

    public static function persistActiveBorrow(
        EntityManagerInterface $em,
        Users $member,
        Books $book,
        ?\DateTimeImmutable $dueDate = null,
    ): Borrows {
        $now = new \DateTimeImmutable();
        $borrow = (new Borrows())
            ->setBook($book)
            ->setMember($member)
            ->setBorrowedAt($now)
            ->setDueDate($dueDate ?? $now->modify('+14 days'))
            ->setReturnedAt(null);
        $em->persist($borrow);
        $em->flush();

        return $borrow;
    }
}
