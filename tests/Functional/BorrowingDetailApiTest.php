<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\ApiWebTestCase;
use App\Tests\Support\JsonTestAssertions;
use App\Tests\Support\TestEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BorrowingDetailApiTest extends ApiWebTestCase
{
    public function testDetailNotFoundForUnknownBorrow(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'Detail404123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request('GET', '/api/borrows/999999999');

        JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_NOT_FOUND,
            'borrow_not_found',
        );
    }

    public function testDetailOkAfterBorrow(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'DetailOk123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain, 10);
        $book = TestEntityFactory::persistBook($em, $suffix, 14);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request(
            'POST',
            '/api/books/' . $book->getSlug() . '/borrow',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"days": 5}',
        );
        $borrowId = (int) (json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR))['borrowId'];

        $this->client->request('GET', '/api/borrows/' . $borrowId);

        $data = JsonTestAssertions::assertJsonResponse($this->client->getResponse(), Response::HTTP_OK);
        self::assertArrayHasKey('borrowId', $data);
        self::assertSame($borrowId, (int) $data['borrowId']);
    }
}
