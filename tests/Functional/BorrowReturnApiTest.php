<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\ApiWebTestCase;
use App\Tests\Support\JsonTestAssertions;
use App\Tests\Support\TestEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BorrowReturnApiTest extends ApiWebTestCase
{
    public function testReturnBorrowedBookReturns200(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'ReturnFlow123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain, 10);
        $book = TestEntityFactory::persistBook($em, $suffix, 14);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request(
            'POST',
            '/api/v1/borrows',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['bookSlug' => $book->getSlug(), 'days' => 7], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $borrowPayload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $borrowId = (int) $borrowPayload['borrowId'];

        $this->client->request(
            'PATCH',
            '/api/v1/borrows/' . $borrowId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"returned": true}',
        );

        $response = $this->client->getResponse();
        $data = JsonTestAssertions::assertJsonResponse($response, Response::HTTP_OK);
        self::assertArrayHasKey('message', $data);
        self::assertArrayHasKey('item', $data);
    }

    public function testReturnUnknownBorrowReturns404(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'Return404123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request(
            'PATCH',
            '/api/v1/borrows/999999999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"returned": true}',
        );

        JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_NOT_FOUND,
            'borrow_not_found',
        );
    }
}
