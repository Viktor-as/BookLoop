<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    protected function loginAs(string $email, string $password): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR),
        );

        $status = $this->client->getResponse()->getStatusCode();
        // Lexik may respond with 204 No Content and Set-Cookie (JWT) instead of 200 + JSON body.
        if (!\in_array($status, [Response::HTTP_OK, Response::HTTP_NO_CONTENT], true)) {
            self::fail(sprintf('Login failed for %s (HTTP %d): %s', $email, $status, $this->client->getResponse()->getContent()));
        }

        $csrf = $this->client->getCookieJar()->get('XSRF-TOKEN');
        if ($csrf !== null) {
            $this->client->setServerParameter('HTTP_X_XSRF_TOKEN', $csrf->getValue());
        }
    }
}
