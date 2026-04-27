<?php

namespace App\Controller\Auth;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Service\AuthCookieService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LoginController extends AbstractController
{
    public function __construct(
        private readonly UsersRepository             $usersRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly ValidatorInterface          $validator,
        private readonly AuthCookieService           $cookieService,
    ) {}

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return $this->json(['message' => 'Request body is empty. Send JSON with email and password.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['message' => 'Invalid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($data)) {
            return $this->json(['message' => 'JSON root must be an object.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($data, new Assert\Collection(
            fields: [
                'email'    => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
                'password' => [new Assert\NotBlank()],
            ],
            allowExtraFields: false,
        ));

        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json(
                ['message' => 'Validation failed.', 'errors' => $errors],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $email = $data['email'];
        $plain = $data['password'];

        $user = $this->usersRepository->findOneBy(['email' => $email]);
        if (!$user instanceof Users || !$this->passwordHasher->isPasswordValid($user, $plain)) {
            return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token    = $this->jwtManager->create($user);
        $response = $this->json([
            'message' => 'Login successful.',
            'user'    => [
                'id'        => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'email'     => $user->getEmail(),
                'role'      => $user->getRole(),
            ],
        ], Response::HTTP_OK);

        $response->headers->setCookie($this->cookieService->createJwtCookie($token));
        $response->headers->setCookie($this->cookieService->createCsrfCookie());

        return $response;
    }
}
