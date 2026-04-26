<?php

namespace App\EventListener;

use App\Service\AuthCookieService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
class AuthenticationSuccessListener
{
    public function __construct(private readonly AuthCookieService $cookieService) {}

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        // LexikJWT already sets the JWT cookie via set_cookies config.
        // We add the CSRF cookie (readable by JS) for the double-submit pattern.
        $event->getResponse()->headers->setCookie(
            $this->cookieService->createCsrfCookie()
        );

        // Remove the token from the response body — it lives in the cookie now.
        $data = $event->getData();
        unset($data['token']);
        $event->setData($data);
    }
}
