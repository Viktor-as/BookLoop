<?php

namespace App\EventListener;

use App\Service\AuthCookieService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class CsrfProtectionListener
{
    private const MUTATING_METHODS  = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const EXEMPT_PATH_PREFIX = '/api/auth/';

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!in_array($request->getMethod(), self::MUTATING_METHODS, true)) {
            return;
        }

        // Auth endpoints are public — no JWT cookie, so no CSRF cookie either.
        if (str_starts_with($request->getPathInfo(), self::EXEMPT_PATH_PREFIX)) {
            return;
        }

        $cookieToken = $request->cookies->get(AuthCookieService::CSRF_COOKIE_NAME);
        $headerToken = $request->headers->get('X-XSRF-TOKEN');

        if (!$cookieToken || !$headerToken || !hash_equals($cookieToken, $headerToken)) {
            $event->setResponse(
                new JsonResponse(['message' => 'Invalid or missing CSRF token.'], Response::HTTP_FORBIDDEN)
            );
        }
    }
}
