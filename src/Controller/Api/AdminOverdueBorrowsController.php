<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Dto\Response\OverdueBorrowItemResponse;
use App\Dto\Response\OverdueBorrowsPageResponse;
use App\Repository\BorrowsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminOverdueBorrowsController extends AbstractController
{
    use ApiControllerTrait;

    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly BorrowsRepository $borrowsRepository,
    ) {}

    #[Route('/api/v1/admin/borrows/overdue', name: 'api_v1_admin_borrows_overdue', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        $pageRaw    = $request->query->get('page', '1');
        $perPageRaw = $request->query->get('perPage', (string) self::DEFAULT_PER_PAGE);

        if (!is_numeric($pageRaw) || (int) $pageRaw < 1) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_query',
                title: 'Invalid query',
                detail: 'Query parameter "page" must be a positive integer.',
            ));
        }

        if (!is_numeric($perPageRaw) || (int) $perPageRaw < 1) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_query',
                title: 'Invalid query',
                detail: 'Query parameter "perPage" must be a positive integer.',
            ));
        }

        $page    = (int) $pageRaw;
        $perPage = min((int) $perPageRaw, self::MAX_PER_PAGE);

        $startOfToday = new \DateTimeImmutable('today');
        $result       = $this->borrowsRepository->findOverdueBorrowPageForAdmin($startOfToday, $page, $perPage);
        $total        = $result['total'];
        $items        = $result['items'];

        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_query',
                title: 'Invalid query',
                detail: sprintf('Page %d is out of range. Last page is %d.', $page, $lastPage),
            ));
        }

        $payload = new OverdueBorrowsPageResponse(
            items: array_map(
                static fn (array $row): OverdueBorrowItemResponse => OverdueBorrowItemResponse::fromRow($row),
                $items,
            ),
            page: $page,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
        );

        return $this->json($payload, Response::HTTP_OK, [], ['json_encode_options' => \JSON_UNESCAPED_SLASHES]);
    }
}
