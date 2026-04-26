<?php

namespace App\Repository;

use App\Entity\Borrows;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
