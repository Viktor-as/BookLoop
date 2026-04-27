<?php

namespace App\Repository;

use App\Dto\CatalogFilters;
use App\Entity\Books;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
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
                b.slug,
                b.title,
                (
                    (SELECT COUNT(*)
                     FROM borrows br0
                     WHERE br0.book_id = b.id AND br0.returned_at IS NULL) = 0
                ) AS available,
                GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) ORDER BY a.id SEPARATOR ', ') AS authors,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.id SEPARATOR ', ') AS categories
            FROM books b
            LEFT JOIN author_book ab ON ab.book_id = b.id
            LEFT JOIN authors a ON a.id = ab.author_id
            LEFT JOIN book_category bc ON bc.book_id = b.id
            LEFT JOIN categories c ON c.id = bc.category_id
            WHERE {$whereSql}
            GROUP BY b.id, b.slug, b.title, b.updated_at
            ORDER BY b.updated_at DESC, b.id DESC
            LIMIT :limit OFFSET :offset
            SQL;

        $rows = $conn->fetchAllAssociative($sql, $params, $types);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'            => (int) $row['id'],
                'slug'          => (string) $row['slug'],
                'title'         => (string) $row['title'],
                'authors'       => $row['authors'] !== null && $row['authors'] !== '' ? (string) $row['authors'] : null,
                'categories'    => $row['categories'] !== null && $row['categories'] !== '' ? (string) $row['categories'] : null,
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
            $parts[] = 'NOT EXISTS (
                SELECT 1 FROM borrows br_f
                WHERE br_f.book_id = b.id AND br_f.returned_at IS NULL
            )';
        }

        return [implode(' AND ', $parts), $params, $types];
    }

    /**
     * Same shape as a catalog item, plus borrowDaysLimit for the borrow panel.
     *
     * @return array{
     *     id: int,
     *     slug: string,
     *     title: string,
     *     authors: string|null,
     *     categories: string|null,
     *     description: string|null,
     *     available: bool,
     *     borrowDaysLimit: int|null
     * }|null
     */
    public function findCatalogDetailBySlug(string $slug): ?array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                b.id,
                b.slug,
                b.title,
                b.description,
                b.borrow_days_limit AS borrowDaysLimit,
                (
                    (SELECT COUNT(*)
                     FROM borrows br0
                     WHERE br0.book_id = b.id AND br0.returned_at IS NULL) = 0
                ) AS available,
                GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) ORDER BY a.id SEPARATOR ', ') AS authors,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.id SEPARATOR ', ') AS categories
            FROM books b
            LEFT JOIN author_book ab ON ab.book_id = b.id
            LEFT JOIN authors a ON a.id = ab.author_id
            LEFT JOIN book_category bc ON bc.book_id = b.id
            LEFT JOIN categories c ON c.id = bc.category_id
            WHERE b.slug = :slug
            GROUP BY b.id, b.slug, b.title, b.description, b.borrow_days_limit
            SQL;

        $row = $conn->fetchAssociative($sql, ['slug' => $slug], ['slug' => ParameterType::STRING]);
        if ($row === false) {
            return null;
        }

        $descRaw     = $row['description'];
        $description = $descRaw !== null && $descRaw !== '' ? (string) $descRaw : null;

        return [
            'id'               => (int) $row['id'],
            'slug'             => (string) $row['slug'],
            'title'            => (string) $row['title'],
            'authors'          => $row['authors'] !== null && $row['authors'] !== '' ? (string) $row['authors'] : null,
            'categories'       => $row['categories'] !== null && $row['categories'] !== '' ? (string) $row['categories'] : null,
            'description'      => $description,
            'available'        => (bool) (int) $row['available'],
            'borrowDaysLimit'  => $row['borrowDaysLimit'] !== null ? (int) $row['borrowDaysLimit'] : null,
        ];
    }

    public function findOneBySlugForUpdate(string $slug): ?Books
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }
}
