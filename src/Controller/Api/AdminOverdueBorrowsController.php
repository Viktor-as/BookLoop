<?php

namespace App\Controller\Api;

use App\Repository\BorrowsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminOverdueBorrowsController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly BorrowsRepository $borrowsRepository,
    ) {}

    #[Route('/api/admin/overdue-borrows', name: 'api_admin_overdue_borrows', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        $pageRaw    = $request->query->get('page', '1');
        $perPageRaw = $request->query->get('perPage', (string) self::DEFAULT_PER_PAGE);

        if (!is_numeric($pageRaw) || (int) $pageRaw < 1) {
            return $this->json(['message' => 'Invalid page. Must be a positive integer.'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_numeric($perPageRaw) || (int) $perPageRaw < 1) {
            return $this->json(['message' => 'Invalid perPage. Must be a positive integer.'], Response::HTTP_BAD_REQUEST);
        }

        $page    = (int) $pageRaw;
        $perPage = min((int) $perPageRaw, self::MAX_PER_PAGE);

        $startOfToday = new \DateTimeImmutable('today');
        $result       = $this->borrowsRepository->findOverdueBorrowPageForAdmin($startOfToday, $page, $perPage);
        $total        = $result['total'];
        $items        = $result['items'];

        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            return $this->json([
                'message' => sprintf('Page %d is out of range. Last page is %d.', $page, $lastPage),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'items'    => $items,
            'page'     => $page,
            'perPage'  => $perPage,
            'total'    => $total,
            'lastPage' => $lastPage,
        ], Response::HTTP_OK, [], ['json_encode_options' => \JSON_UNESCAPED_SLASHES]);
    }
}
