<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\PromoCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    public function findByCode(string $code): ?PromoCode
    {
        return $this->createQueryBuilder('p')
            ->where('UPPER(p.code) = :code')
            ->setParameter('code', strtoupper(trim($code)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActivePromoCodes(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de fois qu'un utilisateur a utilisé un code promo
     */
    public function countUsagesByUser(PromoCode $promoCode, User $user): int
    {
        return $this->getEntityManager()
            ->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.user = :user')
            ->andWhere('o.promoCode = :promoCode')
            ->setParameter('user', $user)
            ->setParameter('promoCode', $promoCode)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un utilisateur peut utiliser ce code promo
     */
    public function canBeUsedByUser(PromoCode $promoCode, User $user): bool
    {
        // Si pas de limite, toujours utilisable
        if ($promoCode->getMaxUsesPerUser() === null) {
            return true;
        }
        
        $usageCount = $this->countUsagesByUser($promoCode, $user);
        return $usageCount < $promoCode->getMaxUsesPerUser();
    }

    /**
     * Récupère les statistiques d'utilisation d'un code promo
     */
    public function getPromoCodeStats(PromoCode $promoCode): array
    {
        $qb = $this->getEntityManager()
            ->getRepository(Order::class)
            ->createQueryBuilder('o');

        return [
            'totalUses' => (int) $qb->select('COUNT(o.id)')
                ->where('o.promoCode = :promoCode')
                ->setParameter('promoCode', $promoCode)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'totalDiscount' => (float) $qb->select('SUM(o.discountAmount)')
                ->where('o.promoCode = :promoCode')
                ->setParameter('promoCode', $promoCode)
                ->getQuery()
                ->getSingleScalarResult() ?? 0,
            
            'uniqueUsers' => (int) $qb->select('COUNT(DISTINCT o.user)')
                ->where('o.promoCode = :promoCode')
                ->andWhere('o.user IS NOT NULL')
                ->setParameter('promoCode', $promoCode)
                ->getQuery()
                ->getSingleScalarResult()
        ];
    }
}