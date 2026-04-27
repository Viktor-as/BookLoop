<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Dto\Response\BorrowingItemResponse;
use App\Repository\BorrowsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BorrowingDetailController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly BorrowsRepository $borrowsRepository,
    ) {}

    #[Route(
        '/api/borrows/{id}',
        name: 'api_borrows_get',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function __invoke(int $id): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $row = $this->borrowsRepository->findBorrowHistoryCatalogRowByIdForMember(
            $id,
            (int) $user->getId(),
        );
        if ($row === null) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_NOT_FOUND,
                code: 'borrow_not_found',
                title: 'Borrow not found',
                detail: 'The requested loan was not found or is not part of your history.',
            ));
        }

        return $this->json(BorrowingItemResponse::fromRow($row));
    }
}
