<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return list<Notification> */
    public function findRecentForUser(User $user, bool $unreadOnly = false): array
    {
        $queryBuilder = $this->createQueryBuilder('notification')
            ->andWhere('notification.user = :user')
            ->setParameter('user', $user)
            ->orderBy('notification.createdAt', 'DESC')
            ->setMaxResults(20);

        if ($unreadOnly) {
            $queryBuilder->andWhere('notification.isRead = false');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('notification')
            ->select('COUNT(notification.id)')
            ->andWhere('notification.user = :user')
            ->andWhere('notification.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}