<?php

namespace App\Controller;

use App\Repository\FavoriteRepository;
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/favoris')]
class FavoriteController extends AbstractController
{
    /**
     * PAGE DES FAVORIS
     */
    #[Route('/', name: 'app_favorite')]
    #[IsGranted('ROLE_USER')]
    public function index(FavoriteRepository $favoriteRepository): Response
    {
        $user = $this->getUser();
        $favorisWithDetails = $favoriteRepository->getFavorisWithDetails($user);
        
        return $this->render('home/favorite.html.twig', [
            'favoris' => $favorisWithDetails,
            'favorisCount' => count($favorisWithDetails)
        ]);
    }

    /**
     * AJOUTER/SUPPRIMER UN FAVORI
     */
    #[Route('/toggle/{itemType}/{itemId}', name: 'app_favorite_toggle')]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        string $itemType, 
        int $itemId, 
        FavoriteRepository $favoriteRepository,
        ProductRepository $productRepository,
        DeviceRepository $deviceRepository
    ): Response {
        $user = $this->getUser();

        // Vérifier que l'item existe
        if ($itemType === 'product') {
            $item = $productRepository->find($itemId);
        } else {
            $item = $deviceRepository->find($itemId);
        }

        if (!$item) {
            $this->addFlash('error', 'Produit non trouvé');
            return $this->redirectToRoute('app_catalog');
        }

        // Toggle le favori
        $isFavorite = $favoriteRepository->isFavorite($user, $itemId, $itemType);
        
        if ($isFavorite) {
            $favoriteRepository->removeFavorite($user, $itemId, $itemType);
            $this->addFlash('success', 'Retiré des favoris');
        } else {
            $favoriteRepository->addFavorite($user, $itemId, $itemType);
            $this->addFlash('success', 'Ajouté aux favoris');
        }

        // Rediriger vers la page précédente
        $referer = $_SERVER['HTTP_REFERER'] ?? $this->generateUrl('app_catalog');
        return $this->redirect($referer);
    }

    #[Route('/move-to-cart/{itemType}/{itemId}', name: 'app_favorite_move_to_cart')]
    #[IsGranted('ROLE_USER')]
    public function moveToCart(string $itemType, int $itemId, FavoriteRepository $favoriteRepository): Response 
    {
        $user = $this->getUser();
        
        // Supprimer des favoris
        $favoriteRepository->removeFavorite($user, $itemId, $itemType);
        
        // Rediriger vers ajout panier
        return $this->redirectToRoute('app_cart_add', [
            'type' => $itemType,
            'id' => $itemId
        ]);
    }
}