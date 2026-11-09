<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> */
    public function searchUsers(string $term = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC');

        if (trim($term) !== '') {
            $queryBuilder
                ->andWhere('u.username LIKE :term OR u.firstName LIKE :term OR u.lastName LIKE :term OR u.email LIKE :term')
                ->setParameter('term', '%' . trim($term) . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }
}