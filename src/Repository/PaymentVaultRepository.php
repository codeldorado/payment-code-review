<?php

namespace App\Repository;

use App\Entity\PaymentVault;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentVault>
 */
class PaymentVaultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentVault::class);
    }

    /**
     * Find active payment methods for a customer
     */
    public function findActiveByCustomer(string $customerId): array
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.customerId = :customerId')
            ->andWhere('pv.isActive = :active')
            ->setParameter('customerId', $customerId)
            ->setParameter('active', true)
            ->orderBy('pv.isDefault', 'DESC')
            ->addOrderBy('pv.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find default payment method for a customer
     */
    public function findDefaultByCustomer(string $customerId): ?PaymentVault
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.customerId = :customerId')
            ->andWhere('pv.isActive = :active')
            ->andWhere('pv.isDefault = :default')
            ->setParameter('customerId', $customerId)
            ->setParameter('active', true)
            ->setParameter('default', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find payment method by UUID
     */
    public function findByUuid(string $uuid): ?PaymentVault
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find payment method by gateway customer ID and token
     */
    public function findByGatewayToken(string $gatewayCustomerId, string $token): ?PaymentVault
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.gatewayCustomerId = :gatewayCustomerId')
            ->andWhere('pv.paymentMethodToken = :token')
            ->setParameter('gatewayCustomerId', $gatewayCustomerId)
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find expired payment methods
     */
    public function findExpired(): array
    {
        $currentDate = new \DateTime();
        $currentMonth = $currentDate->format('m');
        $currentYear = $currentDate->format('Y');

        return $this->createQueryBuilder('pv')
            ->where('pv.isActive = :active')
            ->andWhere('pv.paymentMethodType = :type')
            ->andWhere(
                '(pv.expiryYear < :currentYear) OR 
                 (pv.expiryYear = :currentYear AND pv.expiryMonth < :currentMonth)'
            )
            ->setParameter('active', true)
            ->setParameter('type', 'credit_card')
            ->setParameter('currentYear', $currentYear)
            ->setParameter('currentMonth', $currentMonth)
            ->getQuery()
            ->getResult();
    }

    /**
     * Set a payment method as default (and unset others for the customer)
     */
    public function setAsDefault(PaymentVault $paymentVault): void
    {
        // First, unset all other default payment methods for this customer
        $this->createQueryBuilder('pv')
            ->update()
            ->set('pv.isDefault', ':false')
            ->set('pv.updatedAt', ':now')
            ->where('pv.customerId = :customerId')
            ->andWhere('pv.id != :currentId')
            ->setParameter('false', false)
            ->setParameter('now', new \DateTime())
            ->setParameter('customerId', $paymentVault->getCustomerId())
            ->setParameter('currentId', $paymentVault->getId())
            ->getQuery()
            ->execute();

        // Set the current payment method as default
        $paymentVault->setIsDefault(true);
        $paymentVault->setUpdatedAt(new \DateTime());
        
        $this->getEntityManager()->persist($paymentVault);
        $this->getEntityManager()->flush();
    }

    /**
     * Deactivate a payment method
     */
    public function deactivate(PaymentVault $paymentVault): void
    {
        $paymentVault->setIsActive(false);
        $paymentVault->setUpdatedAt(new \DateTime());
        
        // If this was the default, we need to set another as default
        if ($paymentVault->isDefault()) {
            $paymentVault->setIsDefault(false);
            
            // Find another active payment method to set as default
            $alternative = $this->createQueryBuilder('pv')
                ->where('pv.customerId = :customerId')
                ->andWhere('pv.isActive = :active')
                ->andWhere('pv.id != :currentId')
                ->setParameter('customerId', $paymentVault->getCustomerId())
                ->setParameter('active', true)
                ->setParameter('currentId', $paymentVault->getId())
                ->orderBy('pv.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($alternative) {
                $alternative->setIsDefault(true);
                $alternative->setUpdatedAt(new \DateTime());
                $this->getEntityManager()->persist($alternative);
            }
        }
        
        $this->getEntityManager()->persist($paymentVault);
        $this->getEntityManager()->flush();
    }

    /**
     * Get vault statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('pv');
        
        $total = $qb->select('COUNT(pv.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $qb->select('COUNT(pv.id)')
            ->where('pv.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $expired = $qb->select('COUNT(pv.id)')
            ->where('pv.isActive = :active')
            ->andWhere('pv.paymentMethodType = :type')
            ->andWhere(
                '(pv.expiryYear < :currentYear) OR 
                 (pv.expiryYear = :currentYear AND pv.expiryMonth < :currentMonth)'
            )
            ->setParameter('active', true)
            ->setParameter('type', 'credit_card')
            ->setParameter('currentYear', date('Y'))
            ->setParameter('currentMonth', date('m'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'expired' => (int) $expired,
        ];
    }
}