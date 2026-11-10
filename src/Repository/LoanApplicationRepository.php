<?php

namespace App\Repository;

use App\Entity\LoanApplication;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LoanApplication> */
class LoanApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanApplication::class);
    }

    /** @return list<LoanApplication> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('application')
            ->andWhere('application.user = :user')
            ->setParameter('user', $user)
            ->orderBy('application.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<LoanApplication> */
    public function findForAdmin(?string $status = null, string $search = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('application')
            ->join('application.user', 'user')
            ->addSelect('user')
            ->orderBy('application.submittedAt', 'DESC');

        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $queryBuilder->andWhere('application.status = :status')->setParameter('status', $status);
        }

        if (trim($search) !== '') {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    'user.username LIKE :search',
                    'user.firstName LIKE :search',
                    'user.lastName LIKE :search',
                    'user.email LIKE :search',
                ))
                ->setParameter('search', '%' . trim($search) . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<LoanApplication> */
    public function findForAssignedStaff(User $staff, ?string $status = null, string $search = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('application')
            ->join('application.user', 'borrower')
            ->addSelect('borrower')
            ->andWhere('application.assignedStaff = :staff')
            ->setParameter('staff', $staff)
            ->orderBy('application.submittedAt', 'DESC');

        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $queryBuilder->andWhere('application.status = :status')->setParameter('status', $status);
        }

        if (trim($search) !== '') {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    'borrower.username LIKE :search',
                    'borrower.firstName LIKE :search',
                    'borrower.lastName LIKE :search',
                    'borrower.email LIKE :search',
                ))
                ->setParameter('search', '%' . trim($search) . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->andWhere('application.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByDocumentFilename(string $filename): ?LoanApplication
    {
        return $this->createQueryBuilder('application')
            ->join('application.user', 'user')
            ->addSelect('user')
            ->andWhere('application.validIdPath = :filename OR application.proofOfIncomePath = :filename OR application.proofOfAddressPath = :filename OR application.employmentCertificatePath = :filename')
            ->setParameter('filename', $filename)
            ->getQuery()
            ->getOneOrNullResult();
    }
}