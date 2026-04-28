<?php

namespace App\Controller\Auth;

use App\Api\ApiProblem;
use App\Controller\Api\ApiControllerTrait;
use App\Dto\Request\LoginRequest;
use App\Dto\Response\AuthSuccessResponse;
use App\Dto\Response\AuthUserResponse;
use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Service\AuthCookieService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

final class LoginController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly UsersRepository             $usersRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly AuthCookieService           $cookieService,
        #[Autowire(service: 'limiter.auth_login')]
        private readonly RateLimiterFactory          $authLoginLimiter,
    ) {}

    #[Route('/api/v1/auth/login', name: 'api_v1_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractObjectNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        )]
        LoginRequest $input,
    ): JsonResponse {
        $limiter = $this->authLoginLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            $response = $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_TOO_MANY_REQUESTS,
                code: 'rate_limit_exceeded',
                title: 'Too many requests',
                detail: 'Too many login attempts. Please wait before trying again.',
            ));

            $retryAfter = $limit->getRetryAfter();
            if ($retryAfter !== null) {
                $seconds = max(1, $retryAfter->getTimestamp() - time());
                $response->headers->set('Retry-After', (string) $seconds);
            }

            return $response;
        }

        $user = $this->usersRepository->findOneBy(['email' => (string) $input->email]);
        if (!$user instanceof Users || !$this->passwordHasher->isPasswordValid($user, (string) $input->password)) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_UNAUTHORIZED,
                code: 'invalid_credentials',
                title: 'Invalid credentials',
                detail: 'The email and password combination did not match any account.',
            ));
        }

        $token   = $this->jwtManager->create($user);
        $payload = new AuthSuccessResponse(
            message: 'Login successful.',
            user: AuthUserResponse::fromUser($user),
        );

        $response = $this->json($payload, Response::HTTP_OK);
        $response->headers->setCookie($this->cookieService->createJwtCookie($token));
        $response->headers->setCookie($this->cookieService->createCsrfCookie());

        return $response;
    }
}
