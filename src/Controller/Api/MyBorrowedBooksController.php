<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Dto\Response\BorrowedBooksPageResponse;
use App\Dto\Response\BorrowingItemResponse;
use App\Repository\BorrowsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MyBorrowedBooksController extends AbstractController
{
    use ApiControllerTrait;

    private const BORROWED_LIST_PER_PAGE_DEFAULT = 5;
    private const BORROWED_LIST_PER_PAGE_MAX     = 5;

    public function __construct(private readonly BorrowsRepository $borrowsRepository) {}

    #[Route('/api/v1/users/me/borrows', name: 'api_v1_users_me_borrows', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $scope = (string) $request->query->get('scope', '');
        if ($scope !== 'active' && $scope !== 'history') {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'invalid_query',
                title: 'Invalid query',
                detail: 'Query parameter "scope" is required and must be "active" or "history".',
            ));
        }

        $page = (int) $request->query->get('page', '1');
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) $request->query->get('perPage', (string) self::BORROWED_LIST_PER_PAGE_DEFAULT);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > self::BORROWED_LIST_PER_PAGE_MAX) {
            $perPage = self::BORROWED_LIST_PER_PAGE_MAX;
        }

        $memberId = (int) $user->getId();
        $data     = $scope === 'active'
            ? $this->borrowsRepository->findActiveBorrowHistoryCatalogPageForMember($memberId, $page, $perPage)
            : $this->borrowsRepository->findPastBorrowHistoryCatalogPageForMember($memberId, $page, $perPage);

        $items = array_map(
            static fn (array $row): BorrowingItemResponse => BorrowingItemResponse::fromRow($row),
            $data['items'],
        );

        $payload = new BorrowedBooksPageResponse(
            items: $items,
            page: $page,
            perPage: $perPage,
            total: $data['total'],
        );

        return $this->json(
            $payload,
            Response::HTTP_OK,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
