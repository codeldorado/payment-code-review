<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * Find subscriptions that are due for billing
     */
    public function findDueForBilling(\DateTime $date = null): array
    {
        $date = $date ?? new \DateTime();
        
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.nextBillingDate <= :date')
            ->andWhere('s.cancelledAt IS NULL')
            ->setParameter('status', 'active')
            ->setParameter('date', $date)
            ->orderBy('s.nextBillingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active subscriptions for a customer
     */
    public function findActiveByCustomer(string $customerId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.customerId = :customerId')
            ->andWhere('s.status = :status')
            ->andWhere('s.cancelledAt IS NULL')
            ->setParameter('customerId', $customerId)
            ->setParameter('status', 'active')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subscription by UUID
     */
    public function findByUuid(string $uuid): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get subscription statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('s');
        
        $total = $qb->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $qb->select('COUNT(s.id)')
            ->where('s.status = :status')
            ->andWhere('s.cancelledAt IS NULL')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $cancelled = $qb->select('COUNT(s.id)')
            ->where('s.cancelledAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'cancelled' => (int) $cancelled,
        ];
    }
}