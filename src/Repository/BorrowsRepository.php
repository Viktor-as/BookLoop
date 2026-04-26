<?php

namespace App\Repository;

use App\Entity\Borrows;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Borrows>
 */
class BorrowsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Borrows::class);
    }

    public function countActiveForMember(int $memberId): int
    {
        $member = $this->getEntityManager()->getReference(Users::class, $memberId);

        return (int) $this->createQueryBuilder('br')
            ->select('COUNT(br.id)')
            ->andWhere('br.member = :member')
            ->andWhere('br.returnedAt IS NULL')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasActiveBorrowForMemberAndBook(int $memberId, int $bookId): bool
    {
        $count = (int) $this->createQueryBuilder('br')
            ->select('COUNT(br.id)')
            ->andWhere('IDENTITY(br.member) = :memberId')
            ->andWhere('IDENTITY(br.book) = :bookId')
            ->andWhere('br.returnedAt IS NULL')
            ->setParameter('memberId', $memberId)
            ->setParameter('bookId', $bookId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function countActiveLoansForBook(int $bookId): int
    {
        return (int) $this->createQueryBuilder('br')
            ->select('COUNT(br.id)')
            ->andWhere('IDENTITY(br.book) = :bookId')
            ->andWhere('br.returnedAt IS NULL')
            ->setParameter('bookId', $bookId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<int>
     */
    public function findActiveBorrowedBookIdsForMember(int $memberId): array
    {
        $rows = $this->createQueryBuilder('br')
            ->select('b.id AS bookId')
            ->join('br.book', 'b')
            ->andWhere('IDENTITY(br.member) = :memberId')
            ->andWhere('br.returnedAt IS NULL')
            ->setParameter('memberId', $memberId)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): int => (int) $r['bookId'], $rows);
    }

    private const BORROW_HISTORY_CATALOG_BASE_SQL = <<<'SQL'
        SELECT
            br.id AS borrow_id,
            br.borrowed_at AS borrowed_at,
            br.due_date AS due_date,
            br.returned_at AS returned_at,
            (br.returned_at IS NULL) AS is_active,
            b.id AS book_id,
            b.slug,
            b.title,
            GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) ORDER BY a.id SEPARATOR ', ') AS authors,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.id SEPARATOR ', ') AS categories
        FROM borrows br
        INNER JOIN books b ON b.id = br.book_id
        LEFT JOIN author_book ab ON ab.book_id = b.id
        LEFT JOIN authors a ON a.id = ab.author_id
        LEFT JOIN book_category bc ON bc.book_id = b.id
        LEFT JOIN categories c ON c.id = bc.category_id
        SQL;

    private const BORROW_HISTORY_ORDER_BY = <<<'SQL'
        ORDER BY
            (br.returned_at IS NOT NULL) ASC,
            CASE WHEN br.returned_at IS NULL THEN br.due_date END ASC,
            br.returned_at DESC
        SQL;

    public function countBorrowHistoryForMember(int $memberId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT COUNT(*) FROM borrows br WHERE br.member_id = :memberId';

        return (int) $conn->fetchOne($sql, ['memberId' => $memberId], ['memberId' => ParameterType::INTEGER]);
    }

    /**
     * Paged list of active and past borrows for a member (same row shape as single-item detail).
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function findBorrowHistoryCatalogPageForMember(int $memberId, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        $total = $this->countBorrowHistoryForMember($memberId);

        $conn = $this->getEntityManager()->getConnection();

        $sql = self::BORROW_HISTORY_CATALOG_BASE_SQL
            .' WHERE br.member_id = :memberId'
            .' GROUP BY br.id, br.borrowed_at, br.due_date, br.returned_at, b.id, b.slug, b.title'
            .' '.self::BORROW_HISTORY_ORDER_BY
            .' LIMIT :limit OFFSET :offset';

        $rows = $conn->fetchAllAssociative(
            $sql,
            [
                'memberId' => $memberId,
                'limit'    => $perPage,
                'offset'   => $offset,
            ],
            [
                'memberId' => ParameterType::INTEGER,
                'limit'    => ParameterType::INTEGER,
                'offset'   => ParameterType::INTEGER,
            ],
        );

        $items = array_map(
            fn (array $r): array => $this->mapBorrowHistoryRowToItem($r),
            $rows,
        );

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * One catalog-style borrow row for a member, or null if not found / not owned.
     *
     * @return ?array{
     *     borrowId: int,
     *     bookId: int,
     *     slug: string,
     *     title: string,
     *     authors: string|null,
     *     categories: string|null,
     *     borrowedAt: \DateTimeImmutable,
     *     dueDate: \DateTimeImmutable,
     *     returnedAt: \DateTimeImmutable|null,
     *     isActive: bool
     * }
     */
    public function findBorrowHistoryCatalogRowByIdForMember(int $borrowId, int $memberId): ?array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = self::BORROW_HISTORY_CATALOG_BASE_SQL
            .' WHERE br.member_id = :memberId AND br.id = :borrowId'
            .' GROUP BY br.id, br.borrowed_at, br.due_date, br.returned_at, b.id, b.slug, b.title'
            .' '.self::BORROW_HISTORY_ORDER_BY
            .' LIMIT 1';

        $row = $conn->fetchAssociative(
            $sql,
            [
                'memberId'  => $memberId,
                'borrowId'  => $borrowId,
            ],
            [
                'memberId'  => ParameterType::INTEGER,
                'borrowId'  => ParameterType::INTEGER,
            ],
        );

        if ($row === false) {
            return null;
        }

        return $this->mapBorrowHistoryRowToItem($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     borrowId: int,
     *     bookId: int,
     *     slug: string,
     *     title: string,
     *     authors: string|null,
     *     categories: string|null,
     *     borrowedAt: \DateTimeImmutable,
     *     dueDate: \DateTimeImmutable,
     *     returnedAt: \DateTimeImmutable|null,
     *     isActive: bool
     * }
     */
    private function mapBorrowHistoryRowToItem(array $row): array
    {
        $borrowedAt = new \DateTimeImmutable((string) $row['borrowed_at']);
        $dueDate     = new \DateTimeImmutable((string) $row['due_date']);
        $returnedRaw = $row['returned_at'];
        $returnedAt  = $returnedRaw !== null && $returnedRaw !== ''
            ? new \DateTimeImmutable((string) $returnedRaw)
            : null;

        return [
            'borrowId'   => (int) $row['borrow_id'],
            'bookId'     => (int) $row['book_id'],
            'slug'       => (string) $row['slug'],
            'title'      => (string) $row['title'],
            'authors'    => $row['authors'] !== null && $row['authors'] !== '' ? (string) $row['authors'] : null,
            'categories' => $row['categories'] !== null && $row['categories'] !== '' ? (string) $row['categories'] : null,
            'borrowedAt' => $borrowedAt,
            'dueDate'    => $dueDate,
            'returnedAt' => $returnedAt,
            'isActive'   => (bool) (int) $row['is_active'],
        ];
    }

    //    /**
    //     * @return Borrows[] Returns an array of Borrows objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Borrows
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
