<?php

namespace App\Repository;

use App\Entity\PromoCode;
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
}