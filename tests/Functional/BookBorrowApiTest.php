<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\JsonTestAssertions;
use App\Tests\Support\TestEntityFactory;
use App\Tests\Support\ApiWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BookBorrowApiTest extends ApiWebTestCase
{
    public function testBorrowWithoutAuthenticationIsRejected(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/borrows',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"bookSlug":"any-slug","days":7}',
        );

        $response = $this->client->getResponse();
        // Missing JWT: firewall responds before the controller (status may be 401 or 403 depending on entry point).
        self::assertContains(
            $response->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
            (string) $response->getContent(),
        );
    }

    public function testBorrowSuccessReturns201WithLocation(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'FunctionalBorrow123';
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

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertTrue($response->headers->has('Location'));
        $location = $response->headers->get('Location');
        self::assertIsString($location);
        self::assertStringContainsString('/api/v1/borrows/', $location);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $data = JsonTestAssertions::assertJsonResponse($response, Response::HTTP_CREATED);
        self::assertArrayHasKey('borrowId', $data);
        self::assertArrayHasKey('dueDate', $data);
        self::assertArrayHasKey('message', $data);
    }

    public function testEmptyBodyReturns400Problem(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'FunctionalBorrow123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain);
        $book = TestEntityFactory::persistBook($em, $suffix, 14);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request(
            'POST',
            '/api/v1/borrows',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '',
        );

        JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_BAD_REQUEST,
            'invalid_json',
        );
    }

    public function testInvalidDaysReturns422(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'FunctionalBorrow123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain);
        $book = TestEntityFactory::persistBook($em, $suffix, 14);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request(
            'POST',
            '/api/v1/borrows',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['bookSlug' => $book->getSlug(), 'days' => 0], \JSON_THROW_ON_ERROR),
        );

        JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'validation_error',
        );
    }

    public function testExtraJsonFieldReturns422ValidationError(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'FunctionalBorrow123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain);
        $book = TestEntityFactory::persistBook($em, $suffix, 14);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request(
            'POST',
            '/api/v1/borrows',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['bookSlug' => $book->getSlug(), 'days' => 7, 'foo' => 1], \JSON_THROW_ON_ERROR),
        );

        $data = JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'validation_error',
        );
        self::assertArrayHasKey('violations', $data);
        self::assertNotSame([], $data['violations']);
    }

    public function testOnLoanReturns409(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $owner = TestEntityFactory::persistMember($em, $hasher, 'o-' . $suffix, 'Owner123', 10);
        $other = TestEntityFactory::persistOtherMember($em, $hasher, 'x-' . $suffix, 'Other123');
        $book = TestEntityFactory::persistBook($em, $suffix, 14);
        TestEntityFactory::persistActiveBorrow($em, $owner, $book);

        $this->loginAs($other->getEmail(), 'Other123');
        $this->client->request(
            'POST',
            '/api/v1/borrows',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['bookSlug' => $book->getSlug(), 'days' => 7], \JSON_THROW_ON_ERROR),
        );

        JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_CONFLICT,
            'on_loan',
        );
    }

    public function testAlreadyBorrowedReturns409(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'MemberDup123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain, 10);
        $book = TestEntityFactory::persistBook($em, $suffix, 14);
        TestEntityFactory::persistActiveBorrow($em, $member, $book);

        $this->loginAs($member->getEmail(), $plain);
        $this->client->request(
            'POST',
            '/api/v1/borrows',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['bookSlug' => $book->getSlug(), 'days' => 7], \JSON_THROW_ON_ERROR),
        );

        JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_CONFLICT,
            'already_borrowed',
        );
    }
}
