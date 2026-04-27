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
     * Asserts a problem response contains at least one validation violation per
     * expected field (matches what auth forms and MapRequestPayload surface).
     *
     * A field can appear in multiple violations; extra fields are allowed.
     *
     * @param list<string> $fieldNames
     *
     * @return array<string, mixed>
     */
    public static function assertJsonProblemHasViolationsOnFields(
        Response $response,
        int $expectedStatus,
        string $expectedCode,
        array $fieldNames,
    ): array {
        $data = self::assertJsonProblem($response, $expectedStatus, $expectedCode);
        Assert::assertIsArray(
            $data['violations'] ?? null,
            'Problem must include a violations list when validating fields.',
        );
        $violations = $data['violations'];
        Assert::assertNotEmpty($violations, 'violations must be non-empty');

        $seen = [];
        foreach ($violations as $i => $v) {
            Assert::assertIsArray(
                $v,
                'Each violation must be a map, index = '.$i,
            );
            $raw = (string) ($v['field'] ?? $v['propertyPath'] ?? '');
            $name = self::normalizeProblemViolationField($raw);
            if ($name !== '') {
                $seen[$name] = true;
            }
        }
        Assert::assertNotEmpty($seen, 'At least one violation must be tied to a field name');

        foreach ($fieldNames as $expected) {
            Assert::assertArrayHasKey(
                $expected,
                $seen,
                sprintf('Expected a validation error for field "%s".', $expected),
            );
        }

        return $data;
    }

    public static function normalizeProblemViolationField(string $raw): string
    {
        if ($raw === '' || ltrim($raw) === '') {
            return trim($raw);
        }
        $s = trim($raw);
        if (str_starts_with($s, '[') && str_ends_with($s, ']')) {
            $s = substr($s, 1, -1);
        }
        if (str_contains($s, '.')) {
            $s = substr($s, (int) strrpos($s, '.') + 1);
        }
        if (str_contains($s, '[')) {
            $s = substr($s, 0, (int) strpos($s, '['));
        }

        return $s;
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
