<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Payment> */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /** @return list<Payment> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('payment')
            ->join('payment.loanApplication', 'application')
            ->addSelect('application')
            ->andWhere('application.user = :user')
            ->setParameter('user', $user)
            ->orderBy('payment.paymentDate', 'DESC')
            ->addOrderBy('payment.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Payment> */
    public function findForAdmin(?string $status = null, ?int $loanId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('payment')
            ->join('payment.loanApplication', 'application')
            ->join('application.user', 'user')
            ->addSelect('application', 'user')
            ->orderBy('payment.paymentDate', 'DESC')
            ->addOrderBy('payment.id', 'DESC');

        if ($status !== null && in_array($status, ['pending', 'confirmed', 'failed'], true)) {
            $queryBuilder->andWhere('payment.status = :status')->setParameter('status', $status);
        }
        if ($loanId !== null) {
            $queryBuilder->andWhere('application.id = :loanId')->setParameter('loanId', $loanId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function sumConfirmedForLoan(int $loanId): string
    {
        return (string) ($this->createQueryBuilder('payment')
            ->select('COALESCE(SUM(payment.amountPaid), 0)')
            ->andWhere('payment.loanApplication = :loanId')
            ->andWhere('payment.status = :status')
            ->setParameter('loanId', $loanId)
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getSingleScalarResult());
    }
}