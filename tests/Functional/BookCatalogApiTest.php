<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\JsonTestAssertions;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookCatalogApiTest extends WebTestCase
{
    public function testCatalogReturnsPagedShape(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/books');

        $data = JsonTestAssertions::assertJsonResponse($client->getResponse(), 200);
        self::assertArrayHasKey('page', $data);
        self::assertArrayHasKey('perPage', $data);
        self::assertArrayHasKey('total', $data);
        self::assertArrayHasKey('lastPage', $data);
        self::assertArrayHasKey('items', $data);
        self::assertIsArray($data['items']);
    }

    public function testCatalogSupportsConditionalGetWithEtag(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/books');

        $firstResponse = $client->getResponse();
        self::assertSame(200, $firstResponse->getStatusCode());
        $cacheControl = (string) $firstResponse->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-cache', $cacheControl);
        self::assertStringContainsString('must-revalidate', $cacheControl);

        $etag = $firstResponse->headers->get('ETag');
        self::assertNotNull($etag);

        $client->request('GET', '/api/v1/books', [], [], ['HTTP_IF_NONE_MATCH' => $etag]);
        $secondResponse = $client->getResponse();
        self::assertSame(304, $secondResponse->getStatusCode());
        self::assertSame('', (string) $secondResponse->getContent());
    }
}
