<?php

namespace App\Controller\Api;

use App\Dto\CatalogFilters;
use App\Repository\BooksRepository;
use App\Repository\CategoriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookCatalogController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly BooksRepository $booksRepository,
        private readonly CategoriesRepository $categoriesRepository,
    ) {}

    #[Route('/api/books/catalog', name: 'api_books_catalog', methods: ['GET'])]
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

        $filters = $this->buildCatalogFilters($request);
        if ($filters instanceof JsonResponse) {
            return $filters;
        }

        $result = $this->booksRepository->findCatalogPage($filters, $page, $perPage);
        $total  = $result['total'];
        $items  = $result['items'];

        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            return $this->json([
                'message' => sprintf('Page %d is out of range. Last page is %d.', $page, $lastPage),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'page'     => $page,
            'perPage'  => $perPage,
            'total'    => $total,
            'lastPage' => $lastPage,
            'items'    => $items,
        ]);
    }

    private function buildCatalogFilters(Request $request): CatalogFilters|JsonResponse
    {
        $q      = trim((string) $request->query->get('q', ''));
        $author = trim((string) $request->query->get('author', ''));

        $title  = $q === '' ? null : $q;
        $authorFilter = $author === '' ? null : $author;

        $categoryIdRaw = $request->query->get('categoryId');
        $categoryId    = null;
        if ($categoryIdRaw !== null && $categoryIdRaw !== '') {
            if (!is_numeric($categoryIdRaw) || (int) $categoryIdRaw < 1) {
                return $this->json(
                    ['message' => 'Invalid categoryId. Must be a positive integer.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            $cid = (int) $categoryIdRaw;
            if ($this->categoriesRepository->find($cid) === null) {
                return $this->json(
                    ['message' => sprintf('Category %d does not exist.', $cid)],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            $categoryId = $cid;
        }

        $onlyAvailable = self::parseAvailableFlag($request->query->get('available'));

        return new CatalogFilters($title, $authorFilter, $categoryId, $onlyAvailable);
    }

    private static function parseAvailableFlag(mixed $raw): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        $v = strtolower((string) $raw);

        return in_array($v, ['1', 'true', 'on'], true);
    }
}
