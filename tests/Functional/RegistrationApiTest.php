<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\ApiWebTestCase;
use App\Tests\Support\JsonTestAssertions;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationApiTest extends ApiWebTestCase
{
    public function testEmptyJsonObjectSurfacesViolationsOnAllFields(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        JsonTestAssertions::assertJsonProblemHasViolationsOnFields(
            $this->client->getResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'validation_error',
            ['firstName', 'lastName', 'email', 'password'],
        );
    }

    public function testEmptyStringPayloadSurfacesViolationsOnAllFields(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => '',
                'lastName'  => '',
                'email'     => '',
                'password'  => '',
            ], \JSON_THROW_ON_ERROR),
        );

        JsonTestAssertions::assertJsonProblemHasViolationsOnFields(
            $this->client->getResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'validation_error',
            ['firstName', 'lastName', 'email', 'password'],
        );
    }

    public function testExtraFieldReturns422ValidationProblem(): void
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
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $data = JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'validation_error',
        );
        self::assertArrayHasKey('violations', $data);
        self::assertNotSame([], $data['violations']);
    }

    public function testInvalidJsonReturns400Problem(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{not json',
        );

        JsonTestAssertions::assertJsonProblem(
            $this->client->getResponse(),
            Response::HTTP_BAD_REQUEST,
            'invalid_json',
        );
    }
}
