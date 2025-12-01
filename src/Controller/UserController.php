<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur des fonctionnalités utilisateur
 * Gère les actions qu'un utilisateur connecté peut faire sur son propre compte
 * 
 * Fonctionnalités:
 * - Voir son profil
 * - Modifier ses informations personnelles
 * - Gérer ses adresses
 * - Consulter ses commandes
 * - Gérer ses favoris
 * 
 * Sécurité: Accessible aux utilisateurs connectés (ROLE_USER)
 */
#[Route('/mon-compte')]
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    /**
     * Page d'accueil du compte utilisateur
     */
    #[Route('/', name: 'app_user_account', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('user/account.html.twig');
    }

    /**
     * Afficher le profil de l'utilisateur
     */
    #[Route('/profil', name: 'app_user_profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('user/profile.html.twig', [
            'user' => $this->getUser()
        ]);
    }

    // Ici tu ajouteras d'autres méthodes pour:
    // - Modifier le profil
    // - Gérer les adresses
    // - Voir les commandes
    // - Gérer les favoris
    // etc.
}