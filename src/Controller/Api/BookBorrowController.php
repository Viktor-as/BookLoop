<?php

namespace App\Controller\Api;

use App\Entity\Users;
use App\Service\BorrowBookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookBorrowController extends AbstractController
{
    public function __construct(private readonly BorrowBookService $borrowBookService) {}

    #[Route(
        '/api/books/{slug}/borrow',
        name: 'api_books_borrow',
        methods: ['POST'],
        requirements: ['slug' => '[a-z0-9\-]+'],
    )]
    public function __invoke(string $slug, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !array_key_exists('days', $payload)) {
            return $this->json(
                ['message' => 'Invalid JSON body. Expected {"days": <integer>}'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $daysRaw = $payload['days'];
        if (!is_numeric($daysRaw)) {
            return $this->json(['message' => 'Field "days" must be an integer.'], Response::HTTP_BAD_REQUEST);
        }

        $days = (int) $daysRaw;

        $result = $this->borrowBookService->borrow($user, $slug, $days);

        if (!$result->ok) {
            $status = $result->errorCode === 'not_found'
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_BAD_REQUEST;

            return $this->json(['message' => $result->message], $status);
        }

        return $this->json([
            'message' => $result->message,
            'dueDate' => $result->dueDate->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
