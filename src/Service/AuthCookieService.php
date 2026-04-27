<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;

class AuthCookieService
{
    public const JWT_COOKIE_NAME  = 'BEARER';
    public const CSRF_COOKIE_NAME = 'XSRF-TOKEN';
    public const TOKEN_TTL        = 3600;

    public function __construct(
        private readonly bool $useSecureCookies = true,
    ) {}

    public function createJwtCookie(string $token): Cookie
    {
        return Cookie::create(self::JWT_COOKIE_NAME)
            ->withValue($token)
            ->withExpires(time() + self::TOKEN_TTL)
            ->withPath('/')
            ->withSecure($this->useSecureCookies)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function createCsrfCookie(): Cookie
    {
        $csrfToken = bin2hex(random_bytes(32));

        return Cookie::create(self::CSRF_COOKIE_NAME)
            ->withValue($csrfToken)
            ->withExpires(time() + self::TOKEN_TTL)
            ->withPath('/')
            ->withSecure($this->useSecureCookies)
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function createExpiredJwtCookie(): Cookie
    {
        return Cookie::create(self::JWT_COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($this->useSecureCookies)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function createExpiredCsrfCookie(): Cookie
    {
        return Cookie::create(self::CSRF_COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($this->useSecureCookies)
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }
}
