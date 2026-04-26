<?php

namespace App\Controller\Api;

use App\Entity\Users;
use App\Repository\BorrowsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BorrowReturnController extends AbstractController
{
    public function __construct(
        private readonly BorrowsRepository $borrowsRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route(
        '/api/borrows/{id}/return',
        name: 'api_borrows_return',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    public function __invoke(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $borrow = $this->borrowsRepository->find($id);
        if ($borrow === null) {
            return $this->json(['message' => 'Borrow not found.'], Response::HTTP_NOT_FOUND);
        }

        if ((int) $borrow->getMember()?->getId() !== (int) $user->getId()) {
            return $this->json(['message' => 'You cannot return this loan.'], Response::HTTP_FORBIDDEN);
        }

        if (!$borrow->isActive()) {
            return $this->json(['message' => 'This book has already been returned.'], Response::HTTP_BAD_REQUEST);
        }

        $borrow->setReturnedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json(['message' => 'Book returned successfully.']);
    }
}
