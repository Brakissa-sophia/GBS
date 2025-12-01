<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur d'administration
 * Gère toutes les fonctionnalités réservées aux administrateurs
 * 
 * Fonctionnalités:
 * - Page d'accueil du back-office
 * - Gestion complète des utilisateurs (liste, promotion, rétrogradation, suppression)
 * - Attribution et retrait des rôles administrateur
 * 
 * Sécurité: Toutes les routes nécessitent le rôle ROLE_ADMIN
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    /**
     * Page d'accueil de l'administration
     * Route: /admin/
     */
    #[Route('/', name: 'app_admin', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * Liste de tous les utilisateurs (gestion des membres)
     * Route: /admin/user
     */
    #[Route('/user', name: 'app_admin_user', methods: ['GET'])]
    public function listUsers(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    /**
     * Attribution du rôle administrateur à un utilisateur
     * Permet à un admin de promouvoir un utilisateur simple en administrateur
     * 
     * Le rôle ROLE_ADMIN permet:
     * - Accès complet à toutes les fonctionnalités d'administration
     * - Gestion des produits et appareils
     * - Gestion des catégories et marques
     * - Gestion des commandes
     * - Gestion des utilisateurs et attribution des rôles
     */
    #[Route('/user/{id}/to/admin', name: 'app_admin_user_to_admin', methods: ['POST'])]
    public function promoteToAdmin(Request $request, EntityManagerInterface $entityManager, User $user): Response
    {
        // === VÉRIFICATION 1: TOKEN CSRF ===
        if (!$this->isCsrfTokenValid('admin_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide. Action annulée.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === VÉRIFICATION 2: AUTO-MODIFICATION ===
        $currentUser = $this->getUser();
        
        if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas modifier vos propres rôles.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === VÉRIFICATION 3: RÔLE DÉJÀ EXISTANT ===
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('warning', $user->getLastName() . ' possède déjà le rôle administrateur.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === ATTRIBUTION DU RÔLE ===
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $entityManager->flush();

        $this->addFlash('success', $user->getLastName() . ' a désormais le rôle administrateur.');
        return $this->redirectToRoute('app_admin_user');
    }

    /**
     * Retrait du rôle administrateur
     * Rétrograde un administrateur en utilisateur simple
     */
    #[Route('/user/{id}/remove/admin/role', name: 'app_admin_user_remove_admin_role', methods: ['POST'])]
    public function removeAdminRole(Request $request, EntityManagerInterface $entityManager, User $user): Response
    {
        // === VÉRIFICATION 1: TOKEN CSRF ===
        if (!$this->isCsrfTokenValid('remove_admin_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide. Action annulée.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === VÉRIFICATION 2: AUTO-MODIFICATION ===
        $currentUser = $this->getUser();
        
        if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas modifier vos propres rôles.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === VÉRIFICATION 3: DERNIER ADMIN ===
        $adminCount = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        if ($adminCount <= 1) {
            $this->addFlash('danger', 'Impossible de retirer le rôle du dernier administrateur.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === RETRAIT DU RÔLE ===
        $user->setRoles(['ROLE_USER']);
        $entityManager->flush();

        $this->addFlash('success', $user->getLastName() . ' ne détient plus le rôle administrateur.');
        return $this->redirectToRoute('app_admin_user');
    }

    /**
     * Suppression d'un utilisateur
     * Supprime définitivement un compte utilisateur
     */
    #[Route('/user/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        // === VÉRIFICATION 1: TOKEN CSRF ===
        if (!$this->isCsrfTokenValid('delete_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide. Suppression annulée.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === VÉRIFICATION 2: AUTO-SUPPRESSION ===
        $currentUser = $this->getUser();
        
        if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === VÉRIFICATION 3: DERNIER ADMIN ===
        $adminCount = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        if (in_array('ROLE_ADMIN', $user->getRoles()) && $adminCount <= 1) {
            $this->addFlash('danger', 'Impossible de supprimer le dernier administrateur.');
            return $this->redirectToRoute('app_admin_user');
        }

        // === SUPPRESSION ===
        $lastName = $user->getLastName();
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'Le membre "' . $lastName . '" a bien été supprimé.');
        return $this->redirectToRoute('app_admin_user');
    }
}