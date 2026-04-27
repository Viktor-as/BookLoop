<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\ApiWebTestCase;
use App\Tests\Support\JsonTestAssertions;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationApiTest extends ApiWebTestCase
{
    public function testEmptyJsonObjectReturns422ValidationFailed(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
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

    public function testExtraFieldReturns422ValidationFailed(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $payload = [
            'firstName' => 'Fn',
            'lastName'  => 'Ln',
            'email'     => $suffix.'@example.com',
            'password'  => 'password12',
            'extra'     => true,
        ];

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $data = JsonTestAssertions::assertJsonResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Validation failed.', $data['message']);
        self::assertIsArray($data['errors']);
    }

    public function testInvalidJsonReturns400(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{not json',
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = JsonTestAssertions::assertJsonResponse($response, Response::HTTP_BAD_REQUEST);
        self::assertSame('Invalid JSON.', $data['message']);
    }
}
