<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\ApiWebTestCase;
use App\Tests\Support\JsonTestAssertions;
use App\Tests\Support\TestEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MyBorrowedBooksApiTest extends ApiWebTestCase
{
    public function testMyBorrowsResponseIsNotStored(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'MyBorrows123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain, 10);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request('GET', '/api/v1/users/me/borrows?scope=active');

        $response = $this->client->getResponse();
        JsonTestAssertions::assertJsonResponse($response, Response::HTTP_OK);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
