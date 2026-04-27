<?php

namespace App\EventListener;

use App\Service\AuthCookieService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Lexik always responds with JSON on JWT failure. For browser pages (non-API)
 * show HTML and clear stale cookies.
 */
final class WebJwtFailureResponseListener
{
    public function __construct(
        private readonly Environment $twig,
        private readonly AuthCookieService $cookieService,
    ) {}

    #[AsEventListener(event: Events::JWT_INVALID)]
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($request === null || str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $html = $this->twig->render('error/session_invalid.html.twig');

        $response = new Response($html, Response::HTTP_UNAUTHORIZED, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
        $response->headers->setCookie($this->cookieService->createExpiredJwtCookie());
        $response->headers->setCookie($this->cookieService->createExpiredCsrfCookie());

        $event->setResponse($response);
    }
}
