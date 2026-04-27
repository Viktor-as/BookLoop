<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

final class JsonTestAssertions
{
    /**
     * @return array<string, mixed>
     */
    public static function assertJsonProblem(Response $response, int $expectedStatus, string $expectedCode): array
    {
        Assert::assertSame($expectedStatus, $response->getStatusCode());

        $raw = (string) $response->getContent();
        Assert::assertNotSame('', $raw, 'Response body must not be empty for problem+json');

        $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        Assert::assertIsArray($data);
        Assert::assertArrayHasKey('status', $data);
        Assert::assertArrayHasKey('title', $data);
        Assert::assertArrayHasKey('code', $data);
        Assert::assertArrayHasKey('detail', $data);
        Assert::assertSame($expectedCode, $data['code']);
        Assert::assertSame($expectedStatus, $data['status']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function assertJsonResponse(Response $response, int $expectedStatus): array
    {
        Assert::assertSame($expectedStatus, $response->getStatusCode());
        $raw = (string) $response->getContent();
        Assert::assertNotSame('', $raw);

        return json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
    }
}
