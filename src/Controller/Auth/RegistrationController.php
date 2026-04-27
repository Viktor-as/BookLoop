<?php

namespace App\Controller\Auth;

use App\Api\ApiProblem;
use App\Controller\Api\ApiControllerTrait;
use App\Dto\Request\RegisterRequest;
use App\Dto\Response\AuthSuccessResponse;
use App\Dto\Response\AuthUserResponse;
use App\Entity\Users;
use App\Service\AuthCookieService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

final class RegistrationController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly AuthCookieService           $cookieService,
    ) {}

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractObjectNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        )]
        RegisterRequest $input,
    ): JsonResponse {
        $existing = $this->em->getRepository(Users::class)->findOneBy(['email' => (string) $input->email]);
        if ($existing) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_CONFLICT,
                code: 'email_taken',
                title: 'Email already registered',
                detail: 'An account with this email already exists.',
            ));
        }

        $user = new Users();
        $user->setFirstName((string) $input->firstName);
        $user->setLastName((string) $input->lastName);
        $user->setEmail((string) $input->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $input->password));

        $this->em->persist($user);
        $this->em->flush();

        $token   = $this->jwtManager->create($user);
        $payload = new AuthSuccessResponse(
            message: 'Registration successful.',
            user: AuthUserResponse::fromUser($user),
        );

        $response = $this->json($payload, Response::HTTP_CREATED);
        $response->headers->setCookie($this->cookieService->createJwtCookie($token));
        $response->headers->setCookie($this->cookieService->createCsrfCookie());

        return $response;
    }
}
