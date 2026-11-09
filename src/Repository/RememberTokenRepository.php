<?php

namespace App\Repository;

use App\Entity\RememberToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RememberToken> */
class RememberTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RememberToken::class);
    }

    public function deleteForUserId(int $userId): int
    {
        return $this->createQueryBuilder('token')
            ->delete()
            ->andWhere('token.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}