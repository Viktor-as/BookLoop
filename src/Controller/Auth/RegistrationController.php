<?php

namespace App\Controller\Auth;

use App\Entity\Users;
use App\Service\AuthCookieService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly ValidatorInterface          $validator,
        private readonly AuthCookieService           $cookieService,
    ) {}

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($data, new Assert\Collection([
            'fields' => [
                'firstName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'lastName'  => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'email'     => [new Assert\NotBlank(), new Assert\Email()],
                'password'  => [new Assert\NotBlank(), new Assert\Length(min: 8)],
            ],
            'allowExtraFields' => false,
        ]));

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json(
                ['message' => 'Validation failed.', 'errors' => $errors],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $existing = $this->em->getRepository(Users::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['message' => 'Email is already registered.'], Response::HTTP_CONFLICT);
        }

        $user = new Users();
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        $this->em->persist($user);
        $this->em->flush();

        $token    = $this->jwtManager->create($user);
        $response = $this->json([
            'message' => 'Registration successful.',
            'user'    => [
                'id'        => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'email'     => $user->getEmail(),
                'role'      => $user->getRole(),
            ],
        ], Response::HTTP_CREATED);

        $response->headers->setCookie($this->cookieService->createJwtCookie($token));
        $response->headers->setCookie($this->cookieService->createCsrfCookie());

        return $response;
    }
}
