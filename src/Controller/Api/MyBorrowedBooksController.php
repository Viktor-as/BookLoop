<?php

namespace App\Controller\Api;

use App\Entity\Users;
use App\Repository\BorrowsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MyBorrowedBooksController extends AbstractController
{
    public function __construct(private readonly BorrowsRepository $borrowsRepository) {}

    #[Route('/api/me/borrowed-books', name: 'api_me_borrowed_books', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $rows = $this->borrowsRepository->findBorrowHistoryCatalogRowsForMember((int) $user->getId());

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'borrowId'   => $row['borrowId'],
                'bookId'     => $row['bookId'],
                'slug'       => $row['slug'],
                'title'      => $row['title'],
                'authors'    => $row['authors'],
                'categories' => $row['categories'],
                'borrowedAt' => $row['borrowedAt']->format(\DateTimeInterface::ATOM),
                'dueDate'    => $row['dueDate']->format(\DateTimeInterface::ATOM),
                'returnedAt' => $row['returnedAt']?->format(\DateTimeInterface::ATOM),
                'isActive'   => $row['isActive'],
            ];
        }

        return $this->json(['items' => $items]);
    }
}
