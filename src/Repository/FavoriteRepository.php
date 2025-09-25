<?php

namespace App\Repository;

use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * Récupère tous les favoris d'un utilisateur avec les détails des produits/appareils
     */
    public function getFavorisWithDetails(User $user): array
    {
        $favoris = $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $favorisWithDetails = [];
        
        foreach ($favoris as $favori) {
            $details = null;
            $image = '/images/no-image.jpg';
            
            if ($favori->getItemType() === 'product') {
                $product = $this->getEntityManager()
                    ->getRepository('App\Entity\Product')
                    ->find($favori->getItemId());
                
                if ($product) {
                    // Récupérer l'image du produit
                    $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/*-' . $product->getId() . '-1-*.*';
                    $files = glob($pattern);
                    $image = !empty($files) ? '/uploads/products/' . basename($files[0]) : '/images/no-image.jpg';
                    
                    $details = [
                        'id' => $product->getId(),
                        'title' => $product->getTitle(),
                        'price' => $product->getPrice(),
                        'image' => $image,
                        'type' => 'product',
                        'entity' => $product
                    ];
                }
            } elseif ($favori->getItemType() === 'device') {
                $device = $this->getEntityManager()
                    ->getRepository('App\Entity\Device')
                    ->find($favori->getItemId());
                
                if ($device) {
                    // Récupérer l'image de l'appareil
                    $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/devices/*-' . $device->getId() . '-1-*.*';
                    $files = glob($pattern);
                    $image = !empty($files) ? '/uploads/devices/' . basename($files[0]) : '/images/no-image.jpg';
                    
                    $details = [
                        'id' => $device->getId(),
                        'title' => $device->getTitle(),
                        'price' => $device->getPrice(),
                        'image' => $image,
                        'type' => 'device',
                        'entity' => $device
                    ];
                }
            }
            
            if ($details) {
                $favorisWithDetails[] = $details;
            }
        }
        
        return $favorisWithDetails;
    }

    /**
     * Vérifie si un item est en favoris pour un utilisateur
     */
    public function isFavorite(User $user, int $itemId, string $itemType): bool
    {
        $result = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.user = :user')
            ->andWhere('f.itemId = :itemId')
            ->andWhere('f.itemType = :itemType')
            ->setParameter('user', $user)
            ->setParameter('itemId', $itemId)
            ->setParameter('itemType', $itemType)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Ajoute un item aux favoris
     */
    public function addFavorite(User $user, int $itemId, string $itemType): bool
    {
        // Vérifier si déjà en favoris
        if ($this->isFavorite($user, $itemId, $itemType)) {
            return false;
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setItemId($itemId);
        $favorite->setItemType($itemType);
        $favorite->setCreatedAt(new \DateTimeImmutable()); // CORRECTION: Ajout de la date

        $this->getEntityManager()->persist($favorite);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Supprime un item des favoris
     */
    public function removeFavorite(User $user, int $itemId, string $itemType): bool
    {
        $favorite = $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->andWhere('f.itemId = :itemId')
            ->andWhere('f.itemType = :itemType')
            ->setParameter('user', $user)
            ->setParameter('itemId', $itemId)
            ->setParameter('itemType', $itemType)
            ->getQuery()
            ->getOneOrNullResult();

        if ($favorite) {
            $this->getEntityManager()->remove($favorite);
            $this->getEntityManager()->flush();
            return true;
        }

        return false;
    }

    /**
     * Compte le nombre de favoris d'un utilisateur
     */
    public function countUserFavorites(User $user): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Supprime tous les favoris d'un utilisateur
     */
    public function clearUserFavorites(User $user): int
    {
        return $this->createQueryBuilder('f')
            ->delete()
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}