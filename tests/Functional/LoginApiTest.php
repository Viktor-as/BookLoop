<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\ApiWebTestCase;
use App\Tests\Support\JsonTestAssertions;
use App\Tests\Support\TestEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginApiTest extends ApiWebTestCase
{
    public function testEmptyJsonObjectReturns422WithFieldErrors(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $data = JsonTestAssertions::assertJsonResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Validation failed.', $data['message']);
        self::assertIsArray($data['errors']);
        self::assertNotSame([], $data['errors']);
    }

    public function testSuccessfulLoginReturns200AndSetsCookies(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'LoginTestPwd123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain, 5);

        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'    => $member->getEmail(),
                'password' => $plain,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $jar = $this->client->getCookieJar();
        self::assertNotNull($jar->get('BEARER'));
        self::assertNotNull($jar->get('XSRF-TOKEN'));

        $data = JsonTestAssertions::assertJsonResponse($response, Response::HTTP_OK);
        self::assertSame('Login successful.', $data['message']);
        self::assertArrayHasKey('user', $data);
    }

    public function testWrongPasswordReturns401(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $plain = 'RightPwd123';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $member = TestEntityFactory::persistMember($em, $hasher, $suffix, $plain, 5);

        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'    => $member->getEmail(),
                'password' => 'wrong-password',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $data = JsonTestAssertions::assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('Invalid credentials.', $data['message']);
    }
}
