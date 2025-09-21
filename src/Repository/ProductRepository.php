<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function searchProducts(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.category', 'c')
            ->where('p.title LIKE :searchTerm')
            ->orWhere('p.description LIKE :searchTerm')
            ->orWhere('b.title LIKE :searchTerm')
            ->orWhere('c.name LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function advancedSearch(array $criteria): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.skin_type', 's');

        $hasWhere = false;

        if (!empty($criteria['search'])) {
            $qb->where('p.title LIKE :search OR p.description LIKE :search OR b.title LIKE :search OR c.name LIKE :search')
               ->setParameter('search', '%' . $criteria['search'] . '%');
            $hasWhere = true;
        }

        if (!empty($criteria['brand_id'])) {
            if ($hasWhere) {
                $qb->andWhere('p.brand = :brand_id');
            } else {
                $qb->where('p.brand = :brand_id');
                $hasWhere = true;
            }
            $qb->setParameter('brand_id', $criteria['brand_id']);
        }

        if (!empty($criteria['category_id'])) {
            if ($hasWhere) {
                $qb->andWhere('p.category = :category_id');
            } else {
                $qb->where('p.category = :category_id');
                $hasWhere = true;
            }
            $qb->setParameter('category_id', $criteria['category_id']);
        }

        if (!empty($criteria['min_price'])) {
            if ($hasWhere) {
                $qb->andWhere('p.price >= :min_price');
            } else {
                $qb->where('p.price >= :min_price');
                $hasWhere = true;
            }
            $qb->setParameter('min_price', $criteria['min_price']);
        }

        if (!empty($criteria['max_price'])) {
            if ($hasWhere) {
                $qb->andWhere('p.price <= :max_price');
            } else {
                $qb->where('p.price <= :max_price');
            }
            $qb->setParameter('max_price', $criteria['max_price']);
        }

        return $qb->orderBy('p.title', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    public function countSearchResults(string $searchTerm): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.category', 'c')
            ->where('p.title LIKE :searchTerm')
            ->orWhere('p.description LIKE :searchTerm')
            ->orWhere('b.title LIKE :searchTerm')
            ->orWhere('c.name LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}