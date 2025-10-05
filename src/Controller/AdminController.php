<?php

namespace App\Controller;

// Import des classes nécessaires de Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur gérant les routes de l'administration
 * Le préfixe '/admin' est appliqué à toutes les routes de ce contrôleur
 */
#[Route('/admin')]
final class AdminController extends AbstractController
{
    /**
     * Page d'accueil de l'administration
     * Route: /admin/
     * Nom de la route: app_admin
     * Méthodes HTTP autorisées: GET et POST
     *
     * @return Response Renvoie la vue index.html.twig du dossier admin
     */
    #[Route('/', name: 'app_admin', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            // Tableau vide pour le moment, vous pourrez y passer des variables 
            // à votre template Twig si nécessaire
        ]);
    }
}
