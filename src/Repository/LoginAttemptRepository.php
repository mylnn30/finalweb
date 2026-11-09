<?php

namespace App\Repository;

use App\Entity\LoginAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LoginAttempt> */
class LoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    public function countRecentForUsername(string $username, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('attempt')
            ->select('COUNT(attempt.id)')
            ->andWhere('attempt.username = :username')
            ->andWhere('attempt.attemptedAt >= :since')
            ->setParameter('username', $username)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function clearForUsername(string $username): int
    {
        return $this->createQueryBuilder('attempt')
            ->delete()
            ->andWhere('attempt.username = :username')
            ->setParameter('username', $username)
            ->getQuery()
            ->execute();
    }
}