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
}
