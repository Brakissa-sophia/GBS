<?php

namespace App\Repository;

use App\Entity\PromoCode;
use App\Entity\PromoCodeUsage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PromoCodeUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCodeUsage::class);
    }

    /**
     * Vérifie si un utilisateur a déjà utilisé un code promo
     */
    public function hasUserUsedPromoCode(User $user, PromoCode $promoCode): bool
    {
        return $this->createQueryBuilder('pcu')
            ->select('COUNT(pcu.id)')
            ->where('pcu.user = :user')
            ->andWhere('pcu.promoCode = :promoCode')
            ->setParameter('user', $user)
            ->setParameter('promoCode', $promoCode)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Compte le nombre d'utilisations par un utilisateur
     */
    public function countUsagesByUser(User $user, PromoCode $promoCode): int
    {
        return $this->createQueryBuilder('pcu')
            ->select('COUNT(pcu.id)')
            ->where('pcu.user = :user')
            ->andWhere('pcu.promoCode = :promoCode')
            ->setParameter('user', $user)
            ->setParameter('promoCode', $promoCode)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Statistiques d'un code promo
     */
    public function getPromoCodeStats(PromoCode $promoCode): array
    {
        $qb = $this->createQueryBuilder('pcu');
        
        return [
            'totalUses' => (int) $qb->select('COUNT(pcu.id)')
                ->where('pcu.promoCode = :promoCode')
                ->setParameter('promoCode', $promoCode)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'totalDiscount' => (float) $qb->select('SUM(pcu.discountApplied)')
                ->where('pcu.promoCode = :promoCode')
                ->setParameter('promoCode', $promoCode)
                ->getQuery()
                ->getSingleScalarResult() ?? 0,
            
            'uniqueUsers' => (int) $qb->select('COUNT(DISTINCT pcu.user)')
                ->where('pcu.promoCode = :promoCode')
                ->andWhere('pcu.user IS NOT NULL')
                ->setParameter('promoCode', $promoCode)
                ->getQuery()
                ->getSingleScalarResult()
        ];
    }
}