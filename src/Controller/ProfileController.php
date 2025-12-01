<?php
// src/Controller/ProfileController.php

// Déclaration du namespace du contrôleur
namespace App\Controller;

// Importation des entités utilisées
use App\Entity\Address;  // Entité représentant une adresse de livraison
use App\Entity\Order;    // Entité représentant une commande

// Importation des formulaires
use App\Form\AddressForm;        // Formulaire d'ajout/modification d'adresse
use App\Form\PasswordEditForm;   // Formulaire de changement de mot de passe
use App\Form\ProfileEditForm;    // Formulaire d'édition du profil

// Importation du repository
use App\Repository\OrderRepository;  // Repository pour accéder aux commandes

// Importation de Doctrine
use Doctrine\ORM\EntityManagerInterface;

// Importation des classes Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;  // Service pour hasher les mots de passe
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;  // Attribut pour restreindre l'accès

/**
 * Contrôleur de gestion du profil utilisateur
 * Gère toutes les fonctionnalités du compte client:
 * - Modification des informations personnelles
 * - Changement de mot de passe
 * - Gestion des adresses de livraison
 * - Consultation de l'historique des commandes
 * - Suppression du compte
 * 
 * Toutes les routes sont préfixées par '/mon-compte'
 * Accessible uniquement aux utilisateurs authentifiés
 */
#[Route('/mon-compte')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]  // Toutes les routes nécessitent une authentification complète
final class ProfileController extends AbstractController
{
    // ========== PAGE PRINCIPALE DU PROFIL ==========
    
    /**
     * Page d'accueil du compte utilisateur
     * Affiche un tableau de bord avec les dernières commandes
     * 
     * @param OrderRepository $orderRepository Repository des commandes
     * @return Response Rendu de la page de profil
     */
    #[Route('/', name: 'app_profile_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        // Récupérer toutes les commandes de l'utilisateur connecté
        // Triées par date de commande décroissante (plus récente en premier)
        $orders = $orderRepository->findBy(
            ['user' => $this->getUser()],    // Critère: utilisateur connecté
            ['orderDate' => 'DESC']          // Tri: plus récente en premier
        );

        // Rendu du tableau de bord avec l'historique des commandes
        return $this->render('profile/index.html.twig', [
            'orders' => $orders,  // Liste des commandes pour affichage
        ]);
    }

    // ========== MODIFICATION DU PROFIL (Nom, Prénom, Email, Téléphone) ==========
    
    /**
     * Modification des informations personnelles
     * Permet de modifier: prénom, nom, email, téléphone
     * 
     * @param Request $request Requête HTTP
     * @param EntityManagerInterface $em Gestionnaire d'entités
     * @return Response Rendu du formulaire ou redirection
     */
    #[Route('/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editAccount(Request $request, EntityManagerInterface $em): Response
    {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();
        
        // Créer le formulaire de modification du profil avec les données actuelles
        $form = $this->createForm(ProfileEditForm::class, $user);
        
        // Traiter la soumission du formulaire
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Les données ont été automatiquement mises à jour dans l'entité $user
            // Il suffit de les sauvegarder en base de données
            $em->flush();
            
            // Message de confirmation
            $this->addFlash('success', 'Vos informations ont été mises à jour avec succès.');
            
            // Redirection vers la page d'accueil du profil
            return $this->redirectToRoute('app_profile_index');
        }

        // Affichage du formulaire de modification
        return $this->render('profile/edit_account.html.twig', [
            'profileForm' => $form,
        ]);
    }

    // ========== MODIFICATION DU MOT DE PASSE ==========
    
    /**
     * Changement du mot de passe
     * Nécessite de fournir l'ancien mot de passe pour validation
     * 
     * @param Request $request Requête HTTP
     * @param UserPasswordHasherInterface $passwordHasher Service de hashage des mots de passe
     * @param EntityManagerInterface $em Gestionnaire d'entités
     * @return Response Rendu du formulaire ou redirection
     */
    #[Route('/mot-de-passe/modifier', name: 'app_profile_password_edit', methods: ['GET', 'POST'])]
    public function editPassword(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response
    {
        // Récupérer l'utilisateur connecté avec annotation de type pour l'autocomplétion
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Créer le formulaire de changement de mot de passe
        // Ce formulaire n'est pas lié à une entité (pas de mapping automatique)
        $form = $this->createForm(PasswordEditForm::class);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les données du formulaire
            $currentPassword = $form->get('currentPassword')->getData();  // Mot de passe actuel
            $newPassword = $form->get('newPassword')->getData();          // Nouveau mot de passe

            // === VÉRIFICATION DE SÉCURITÉ ===
            // Vérifier que l'ancien mot de passe fourni est correct
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                // Si le mot de passe actuel est incorrect
                $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile_password_edit');
            }

            // === HASHAGE ET ENREGISTREMENT DU NOUVEAU MOT DE PASSE ===
            // Hasher le nouveau mot de passe (bcrypt par défaut dans Symfony)
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            
            // Mettre à jour le mot de passe de l'utilisateur
            $user->setPassword($hashedPassword);
            
            // Sauvegarder en base de données
            $em->flush();

            // Message de succès
            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            
            // Redirection vers le profil
            return $this->redirectToRoute('app_profile_index');
        }

        // Affichage du formulaire de changement de mot de passe
        return $this->render('profile/edit_password.html.twig', [
            'passwordForm' => $form,
        ]);
    }

    // ========== AJOUTER UNE ADRESSE ==========
    
    /**
     * Ajout d'une nouvelle adresse de livraison
     * Permet d'enregistrer plusieurs adresses pour faciliter les futures commandes
     * 
     * @param Request $request Requête HTTP
     * @param EntityManagerInterface $em Gestionnaire d'entités
     * @return Response Rendu du formulaire ou redirection
     */
    #[Route('/adresse/ajouter', name: 'app_profile_address_add', methods: ['GET', 'POST'])]
    public function addAddress(Request $request, EntityManagerInterface $em): Response
    {
        // Créer une nouvelle entité Address vide
        $address = new Address();
        
        // Créer le formulaire d'adresse
        $form = $this->createForm(AddressForm::class, $address);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Associer l'adresse à l'utilisateur connecté
            $address->setUser($this->getUser());
            
            // Persister la nouvelle adresse
            $em->persist($address);
            $em->flush();

            // Message de confirmation
            $this->addFlash('success', 'Votre adresse a été ajoutée avec succès.');
            
            // Redirection vers le profil
            return $this->redirectToRoute('app_profile_index');
        }

        // Affichage du formulaire d'ajout d'adresse
        return $this->render('profile/add_address.html.twig', [
            'addressForm' => $form,
        ]);
    }

    // ========== MODIFIER UNE ADRESSE ==========
    
    /**
     * Modification d'une adresse existante
     * Permet de corriger ou mettre à jour une adresse de livraison
     * 
     * @param Address $address Adresse à modifier (injection automatique)
     * @param Request $request Requête HTTP
     * @param EntityManagerInterface $em Gestionnaire d'entités
     * @return Response Rendu du formulaire ou redirection
     */
    #[Route('/adresse/{id}/modifier', name: 'app_profile_address_edit', methods: ['GET', 'POST'])]
    public function editAddress(Address $address, Request $request, EntityManagerInterface $em): Response
    {
        // === CONTRÔLE DE SÉCURITÉ ===
        // Vérifier que l'adresse appartient bien à l'utilisateur connecté
        // Empêche un utilisateur de modifier l'adresse d'un autre utilisateur
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette adresse.');
        }

        // Créer le formulaire avec l'adresse existante
        $form = $this->createForm(AddressForm::class, $address);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Les modifications ont été automatiquement appliquées à l'entité
            // Il suffit de sauvegarder en base de données
            $em->flush();

            // Message de confirmation
            $this->addFlash('success', 'Votre adresse a été modifiée avec succès.');
            
            // Redirection vers le profil
            return $this->redirectToRoute('app_profile_index');
        }

        // Affichage du formulaire de modification
        return $this->render('profile/edit_address.html.twig', [
            'addressForm' => $form,
            'address' => $address,  // Pour afficher l'adresse actuelle
        ]);
    }

    // ========== SUPPRIMER UNE ADRESSE ==========
    
    /**
     * Suppression d'une adresse de livraison
     * Méthode POST uniquement pour éviter la suppression accidentelle
     * Protection par token CSRF
     * 
     * @param Address $address Adresse à supprimer
     * @param Request $request Requête HTTP
     * @param EntityManagerInterface $em Gestionnaire d'entités
     * @return Response Redirection vers le profil
     */
    #[Route('/adresse/{id}/supprimer', name: 'app_profile_address_delete', methods: ['POST'])]
    public function deleteAddress(Address $address, Request $request, EntityManagerInterface $em): Response
    {
        // === CONTRÔLE DE SÉCURITÉ 1: PROPRIÉTÉ ===
        // Vérifier que l'adresse appartient à l'utilisateur connecté
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette adresse.');
        }

        // === CONTRÔLE DE SÉCURITÉ 2: TOKEN CSRF ===
        // Vérifier que le token CSRF est valide pour éviter les attaques CSRF
        // Le token doit correspondre à 'delete' + ID de l'adresse
        if ($this->isCsrfTokenValid('delete'.$address->getId(), $request->request->get('_token'))) {
            // Token valide: procéder à la suppression
            $em->remove($address);
            $em->flush();

            // Message de confirmation
            $this->addFlash('success', 'Votre adresse a été supprimée avec succès.');
        } else {
            // Token invalide: tentative d'attaque CSRF détectée
            $this->addFlash('danger', 'Token CSRF invalide. Suppression annulée.');
        }

        // Redirection vers le profil dans tous les cas
        return $this->redirectToRoute('app_profile_index');
    }

    // ========== SUPPRIMER LE COMPTE ==========
    
    /**
     * Suppression définitive du compte utilisateur
     * Action irréversible: supprime l'utilisateur et toutes ses données
     * Affiche d'abord une page de confirmation (GET)
     * Puis effectue la suppression (POST) avec protection CSRF
     * 
     * @param Request $request Requête HTTP
     * @param EntityManagerInterface $em Gestionnaire d'entités
     * @return Response Rendu de la confirmation ou redirection
     */
    #[Route('/supprimer', name: 'app_profile_delete', methods: ['GET', 'POST'])]
    public function deleteAccount(Request $request, EntityManagerInterface $em): Response
    {
        // Si la requête est en POST (confirmation de suppression)
        if ($request->isMethod('POST')) {
            // === VÉRIFICATION DU TOKEN CSRF ===
            if ($this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
                // Récupérer l'utilisateur connecté
                $user = $this->getUser();
                
                // === DÉCONNEXION DE L'UTILISATEUR ===
                // Invalider la session actuelle
                $request->getSession()->invalidate();
                
                // Supprimer le token de sécurité (déconnecter complètement)
                $this->container->get('security.token_storage')->setToken(null);

                // === SUPPRESSION DU COMPTE ===
                // Supprimer l'utilisateur de la base de données
                // Les adresses seront automatiquement supprimées grâce à orphanRemoval=true
                // dans la relation User->Address
                $em->remove($user);
                $em->flush();

                // Message de confirmation
                $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
                
                // Redirection vers la page d'accueil du site
                return $this->redirectToRoute('app_home');
            } else {
                // Token CSRF invalide
                $this->addFlash('danger', 'Token CSRF invalide. Suppression annulée.');
            }
        }

        // Si GET ou token invalide: afficher la page de confirmation
        return $this->render('profile/delete_account.html.twig');
    }

    // ========== DÉTAIL D'UNE COMMANDE ==========
    
    /**
     * Affichage des détails complets d'une commande
     * Permet de voir les produits commandés, le montant, le statut, etc.
     * 
     * @param Order $order Commande à afficher (injection automatique)
     * @return Response Rendu de la page de détails de la commande
     */
    #[Route('/commandes/{id}', name: 'app_profile_order_show', methods: ['GET'])]
    public function orderShow(Order $order): Response
    {
        // === CONTRÔLE DE SÉCURITÉ ===
        // Vérifier que la commande appartient bien à l'utilisateur connecté
        // Un utilisateur ne peut voir que ses propres commandes
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter cette commande.');
        }

        // Affichage des détails de la commande
        return $this->render('profile/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    // ========== LISTE DES COMMANDES ==========
    
    /**
     * Historique complet des commandes de l'utilisateur
     * Affiche toutes les commandes triées par date décroissante
     * 
     * @param OrderRepository $orderRepository Repository des commandes
     * @return Response Rendu de la page d'historique des commandes
     */
    #[Route('/commandes', name: 'app_profile_orders', methods: ['GET'])]
    public function orders(OrderRepository $orderRepository): Response
    {
        // Récupérer toutes les commandes de l'utilisateur connecté
        // Triées de la plus récente à la plus ancienne
        $orders = $orderRepository->findBy(
            ['user' => $this->getUser()],    // Filtre: utilisateur connecté
            ['orderDate' => 'DESC']          // Tri: plus récente en premier
        );

        // Affichage de la liste complète des commandes
        return $this->render('profile/orders.html.twig', [
            'orders' => $orders,
        ]);
    }
}