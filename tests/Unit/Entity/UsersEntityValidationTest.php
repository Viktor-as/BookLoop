<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UsersEntityValidationTest extends KernelTestCase
{
    public function testUserWithoutHashedPasswordPassesValidationWhenNamesAndEmailSet(): void
    {
        self::bootKernel();
        $validator = static::getContainer()->get(ValidatorInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $user = new Users();
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setEmail($suffix.'@validation-test.example');

        $violations = $validator->validate($user);
        self::assertCount(0, $violations, (string) $violations);
    }
}
