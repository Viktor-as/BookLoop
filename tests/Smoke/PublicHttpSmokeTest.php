<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PublicHttpSmokeTest extends WebTestCase
{
    public function testHomeReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testPublicCatalogReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/books');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }
}
