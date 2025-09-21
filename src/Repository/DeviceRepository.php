<?php

namespace App\Repository;

use App\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Device>
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    public function searchDevices(string $searchTerm): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.brand', 'b')
            ->leftJoin('d.category', 'c')
            ->where('d.title LIKE :searchTerm')
            ->orWhere('d.description LIKE :searchTerm')
            ->orWhere('b.title LIKE :searchTerm')
            ->orWhere('c.name LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function advancedSearch(array $criteria): array
{
    $qb = $this->createQueryBuilder('d')
        ->leftJoin('d.brand', 'b')
        ->leftJoin('d.category', 'c')
        ->leftJoin('d.skin_type', 's');
    
    $hasWhere = false;

    if (!empty($criteria['search'])) {
        $qb->where('d.title LIKE :search OR d.description LIKE :search OR b.title LIKE :search OR c.name LIKE :search')
           ->setParameter('search', '%' . $criteria['search'] . '%');
        $hasWhere = true;
    }

    if (!empty($criteria['brand_id'])) {
        if ($hasWhere) {
            $qb->andWhere('d.brand = :brand_id');
        } else {
            $qb->where('d.brand = :brand_id');
            $hasWhere = true;
        }
        $qb->setParameter('brand_id', $criteria['brand_id']);
    }

    if (!empty($criteria['category_id'])) {
        if ($hasWhere) {
            $qb->andWhere('d.category = :category_id');
        } else {
            $qb->where('d.category = :category_id');
            $hasWhere = true;
        }
        $qb->setParameter('category_id', $criteria['category_id']);
    }

    if (!empty($criteria['min_price'])) {
        if ($hasWhere) {
            $qb->andWhere('d.price >= :min_price');
        } else {
            $qb->where('d.price >= :min_price');
            $hasWhere = true;
        }
        $qb->setParameter('min_price', $criteria['min_price']);
    }

    if (!empty($criteria['max_price'])) {
        if ($hasWhere) {
            $qb->andWhere('d.price <= :max_price');
        } else {
            $qb->where('d.price <= :max_price');
        }
        $qb->setParameter('max_price', $criteria['max_price']);
    }

    return $qb->orderBy('d.title', 'ASC')
              ->getQuery()
              ->getResult();
}

public function countSearchResults(string $searchTerm): int
{
    return $this->createQueryBuilder('d')
        ->select('COUNT(d.id)')
        ->leftJoin('d.brand', 'b')
        ->leftJoin('d.category', 'c')
        ->where('d.title LIKE :searchTerm')
        ->orWhere('d.description LIKE :searchTerm')
        ->orWhere('b.title LIKE :searchTerm')
        ->orWhere('c.name LIKE :searchTerm')
        ->setParameter('searchTerm', '%' . $searchTerm . '%')
        ->getQuery()
        ->getSingleScalarResult();
}
}