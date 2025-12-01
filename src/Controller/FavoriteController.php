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

#[Route('/favoris')] // Préfixe de route : toutes les routes commencent par "/favoris"
class FavoriteController extends AbstractController // Classe non finale (peut être héritée)
{
    #[Route('/', name: 'app_favorite')] // Route racine du préfixe = "/favoris/"
    #[IsGranted('ROLE_USER')] // Attribut de sécurité : seuls les utilisateurs authentifiés avec le rôle ROLE_USER peuvent accéder
    public function index(FavoriteRepository $favoriteRepository): Response // Méthode pour afficher la page des favoris
    {
        $user = $this->getUser(); // Récupère l'utilisateur actuellement connecté (objet User ou null si non connecté)
        $favorisWithDetails = $favoriteRepository->getFavorisWithDetails($user); // Appelle une méthode personnalisée du repository pour récupérer les favoris avec leurs détails
        
        return $this->render('home/favorite.html.twig', [ // Affiche le template des favoris
            'favoris' => $favorisWithDetails, // Passe les favoris au template
            'favorisCount' => count($favorisWithDetails) // count() : compte le nombre d'éléments dans le tableau
        ]);
    }

    #[Route('/toggle/{itemType}/{itemId}', name: 'app_favorite_toggle')] // Route avec 2 paramètres dynamiques : type et id de l'article
    #[IsGranted('ROLE_USER')] // Protection : utilisateur connecté requis
    public function toggle(
        string $itemType, // Type de l'article (product ou device)
        int $itemId, // ID de l'article
        FavoriteRepository $favoriteRepository, // Injection du repository des favoris
        ProductRepository $productRepository, // Injection du repository des produits
        DeviceRepository $deviceRepository // Injection du repository des appareils
    ): Response {
        $user = $this->getUser(); // Récupère l'utilisateur connecté

        if ($itemType === 'product') { // Si le type est 'product'
            $item = $productRepository->find($itemId); // Cherche le produit par son ID
        } else { // Sinon (type = 'device')
            $item = $deviceRepository->find($itemId); // Cherche l'appareil par son ID
        }

        if (!$item) { // Si l'article n'existe pas
            $this->addFlash('error', 'Produit non trouvé');
            return $this->redirectToRoute('app_catalog'); // Redirige vers le catalogue
        }

        $isFavorite = $favoriteRepository->isFavorite($user, $itemId, $itemType); // Appelle une méthode pour vérifier si l'article est déjà en favori
        
        if ($isFavorite) { // Si déjà en favori
            $favoriteRepository->removeFavorite($user, $itemId, $itemType); // Supprime des favoris
            $this->addFlash('success', 'Retiré des favoris');
        } else { // Sinon
            $favoriteRepository->addFavorite($user, $itemId, $itemType); // Ajoute aux favoris
            $this->addFlash('success', 'Ajouté aux favoris');
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? $this->generateUrl('app_catalog'); // $_SERVER['HTTP_REFERER'] : URL de la page précédente, ?? : si null utilise l'URL du catalogue
        return $this->redirect($referer); // Redirige vers la page d'où venait l'utilisateur
    }

    #[Route('/move-to-cart/{itemType}/{itemId}', name: 'app_favorite_move_to_cart')] // Route pour déplacer un favori vers le panier
    #[IsGranted('ROLE_USER')] // Protection utilisateur connecté
    public function moveToCart(string $itemType, int $itemId, FavoriteRepository $favoriteRepository): Response 
    {
        $user = $this->getUser(); // Récupère l'utilisateur connecté
        
        $favoriteRepository->removeFavorite($user, $itemId, $itemType); // Supprime l'article des favoris
        
        return $this->redirectToRoute('app_cart_add', [ // Redirige vers la route d'ajout au panier
            'type' => $itemType, // Passe le type en paramètre de route
            'id' => $itemId // Passe l'ID en paramètre de route
        ]);
    }
}