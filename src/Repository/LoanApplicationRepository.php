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
    public function findForAdmin(?string $status = null): array
    {
        $queryBuilder = $this->createQueryBuilder('application')
            ->join('application.user', 'user')
            ->addSelect('user')
            ->orderBy('application.submittedAt', 'DESC');

        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $queryBuilder->andWhere('application.status = :status')->setParameter('status', $status);
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