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
    public function searchUsers(string $term = '', ?string $role = null): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC');

        if (trim($term) !== '') {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    'u.username LIKE :term',
                    'u.firstName LIKE :term',
                    'u.lastName LIKE :term',
                    'u.email LIKE :term',
                ))
                ->setParameter('term', '%' . trim($term) . '%');
        }

        if ($role === 'admin') {
            $queryBuilder->andWhere('u.isAdmin = true');
        } elseif ($role === 'staff') {
            $queryBuilder->andWhere('u.isStaff = true');
        } elseif ($role === 'borrower') {
            $queryBuilder->andWhere('u.isAdmin = false AND u.isStaff = false');
        }

        return $queryBuilder->getQuery()->getResult();
    }
}