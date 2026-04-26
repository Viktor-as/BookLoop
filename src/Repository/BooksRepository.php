<?php

namespace App\Repository;

use App\Dto\CatalogFilters;
use App\Entity\Books;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Books>
 */
class BooksRepository extends ServiceEntityRepository
{
    /** MySQL LIKE escape character (backslash) */
    private const LIKE_ESCAPE = " ESCAPE '\\\\' ";

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Books::class);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function findCatalogPage(CatalogFilters $filters, int $page, int $perPage): array
    {
        $conn = $this->getEntityManager()->getConnection();

        [$whereSql, $params, $types] = $this->buildCatalogWhere($filters);

        $countSql = <<<SQL
            SELECT COUNT(*) FROM (
                SELECT b.id
                FROM books b
                WHERE {$whereSql}
                GROUP BY b.id
            ) cnt
            SQL;

        $total = (int) $conn->fetchOne($countSql, $params, $types);

        $offset = ($page - 1) * $perPage;

        $params['limit']  = $perPage;
        $params['offset'] = $offset;
        $types['limit']   = ParameterType::INTEGER;
        $types['offset']  = ParameterType::INTEGER;

        $sql = <<<SQL
            SELECT
                b.id,
                b.title,
                b.copies_total AS copiesTotal,
                (
                    SELECT COUNT(*)
                    FROM borrows br
                    WHERE br.book_id = b.id AND br.returned_at IS NULL
                ) AS activeBorrows,
                (
                    b.copies_total > (
                        SELECT COUNT(*)
                        FROM borrows br2
                        WHERE br2.book_id = b.id AND br2.returned_at IS NULL
                    )
                ) AS available,
                GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) ORDER BY a.id SEPARATOR ', ') AS authors,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.id SEPARATOR ', ') AS categories
            FROM books b
            LEFT JOIN author_book ab ON ab.book_id = b.id
            LEFT JOIN authors a ON a.id = ab.author_id
            LEFT JOIN book_category bc ON bc.book_id = b.id
            LEFT JOIN categories c ON c.id = bc.category_id
            WHERE {$whereSql}
            GROUP BY b.id, b.title, b.copies_total
            ORDER BY b.id ASC
            LIMIT :limit OFFSET :offset
            SQL;

        $rows = $conn->fetchAllAssociative($sql, $params, $types);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'            => (int) $row['id'],
                'title'         => (string) $row['title'],
                'authors'       => $row['authors'] !== null && $row['authors'] !== '' ? (string) $row['authors'] : null,
                'categories'    => $row['categories'] !== null && $row['categories'] !== '' ? (string) $row['categories'] : null,
                'copiesTotal'   => (int) $row['copiesTotal'],
                'activeBorrows' => (int) $row['activeBorrows'],
                'available'     => (bool) (int) $row['available'],
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, int>}
     */
    private function buildCatalogWhere(CatalogFilters $filters): array
    {
        $parts = ['1=1'];
        $params = [];
        $types  = [];

        if ($filters->title !== null && $filters->title !== '') {
            $parts[] = 'LOWER(b.title) LIKE LOWER(:titlePat)' . self::LIKE_ESCAPE;
            $params['titlePat'] = '%' . CatalogFilters::likeEscape($filters->title) . '%';
            $types['titlePat']  = ParameterType::STRING;
        }

        if ($filters->author !== null && $filters->author !== '') {
            $parts[] = "EXISTS (
                SELECT 1 FROM author_book ab_f
                INNER JOIN authors au ON au.id = ab_f.author_id
                WHERE ab_f.book_id = b.id
                  AND LOWER(CONCAT(au.first_name, ' ', au.last_name)) LIKE LOWER(:authorPat)" . self::LIKE_ESCAPE . '
            )';
            $params['authorPat'] = '%' . CatalogFilters::likeEscape($filters->author) . '%';
            $types['authorPat']   = ParameterType::STRING;
        }

        if ($filters->categoryId !== null) {
            $parts[] = 'EXISTS (
                SELECT 1 FROM book_category bc_f
                WHERE bc_f.book_id = b.id AND bc_f.category_id = :categoryId
            )';
            $params['categoryId'] = $filters->categoryId;
            $types['categoryId'] = ParameterType::INTEGER;
        }

        if ($filters->onlyAvailable) {
            $parts[] = 'b.copies_total > (
                SELECT COUNT(*) FROM borrows br_f
                WHERE br_f.book_id = b.id AND br_f.returned_at IS NULL
            )';
        }

        return [implode(' AND ', $parts), $params, $types];
    }
}
