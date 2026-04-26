<?php

namespace App\Controller\Auth;

use App\Service\AuthCookieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class LogoutController extends AbstractController
{
    public function __construct(private readonly AuthCookieService $cookieService) {}

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): RedirectResponse
    {
        $response = $this->redirectToRoute('home');
        $response->headers->setCookie($this->cookieService->createExpiredJwtCookie());
        $response->headers->setCookie($this->cookieService->createExpiredCsrfCookie());

        return $response;
    }
}
