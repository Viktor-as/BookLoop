<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ApplicationSmokeTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();
        self::assertSame('test', static::$kernel->getEnvironment());
    }

    public function testRouterGeneratesCoreRoutes(): void
    {
        self::bootKernel();
        $router = static::getContainer()->get('router');
        self::assertSame('/', $router->generate('home'));
        self::assertSame('/api/books/catalog', $router->generate('api_books_catalog'));
        self::assertSame('/login', $router->generate('login_page'));
    }
}
