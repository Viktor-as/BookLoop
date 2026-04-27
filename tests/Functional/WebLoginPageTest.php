<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class WebLoginPageTest extends WebTestCase
{
    public function testLoginPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('text/html', (string) $client->getResponse()->headers->get('Content-Type'));
    }

    public function testHomePageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }
}
