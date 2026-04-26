<?php

namespace App\Controller\Api;

use App\Api\BorrowingItemJson;
use App\Repository\BorrowsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MyBorrowedBooksController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(private readonly BorrowsRepository $borrowsRepository) {}

    #[Route('/api/me/borrowed-books', name: 'api_me_borrowed_books', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $page    = (int) $request->query->get('page', '1');
        $perPage = (int) $request->query->get('perPage', '20');
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $data = $this->borrowsRepository->findBorrowHistoryCatalogPageForMember(
            (int) $user->getId(),
            $page,
            $perPage,
        );

        $items = array_map(
            static fn (array $row) => BorrowingItemJson::encodeItem($row),
            $data['items'],
        );

        return $this->json(
            [
                'items'   => $items,
                'page'    => $page,
                'perPage' => $perPage,
                'total'   => $data['total'],
            ],
            Response::HTTP_OK,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
