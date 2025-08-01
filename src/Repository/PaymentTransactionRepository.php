<?php

namespace App\Repository;

use App\Entity\PaymentTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends ServiceEntityRepository<PaymentTransaction>
 */
class PaymentTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentTransaction::class);
    }

    public function by(Request $request): array
    {
        $transactionId = $request->get('transaction-id');

        if ($transactionId) {
            // Use parameterized query to prevent SQL injection
            return $this->getEntityManager()
                ->createQuery('SELECT t FROM App\Entity\PaymentTransaction t WHERE t.transaction_id = :transactionId')
                ->setParameter('transactionId', $transactionId)
                ->getResult();
        }

        // Return all transactions ordered by creation date (newest first)
        return $this->findBy([], ['createdAt' => 'DESC']);
    }
}
