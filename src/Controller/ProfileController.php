<?php
// src/Controller/ProfileController.php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Form\AddressForm;
use App\Form\PasswordEditForm;
use App\Form\ProfileEditForm;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-compte')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProfileController extends AbstractController
{
    // ========== PAGE PRINCIPALE DU PROFIL ==========
    #[Route('/', name: 'app_profile_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findBy(
            ['user' => $this->getUser()],
            ['orderDate' => 'DESC']
        );

        return $this->render('profile/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    // ========== MODIFICATION DU PROFIL (Nom, Prénom, Email, Téléphone) ==========
    #[Route('/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editAccount(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileEditForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            
            $this->addFlash('success', 'Vos informations ont été mises à jour avec succès.');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/edit_account.html.twig', [
            'profileForm' => $form,
        ]);
    }

    // ========== MODIFICATION DU MOT DE PASSE ==========
    #[Route('/mot-de-passe/modifier', name: 'app_profile_password_edit', methods: ['GET', 'POST'])]
    public function editPassword(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response
    {
    /** @var \App\Entity\User $user */
    $user = $this->getUser(); // Déclarer $user au début
    $form = $this->createForm(PasswordEditForm::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $currentPassword = $form->get('currentPassword')->getData();
        $newPassword = $form->get('newPassword')->getData();

        // Vérifier que l'ancien mot de passe est correct
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_profile_password_edit');
        }

        // Hasher et enregistrer le nouveau mot de passe
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $em->flush();

        $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
        return $this->redirectToRoute('app_profile_index');
    }

    return $this->render('profile/edit_password.html.twig', [
        'passwordForm' => $form,
    ]);
}

    // ========== AJOUTER UNE ADRESSE ==========
    #[Route('/adresse/ajouter', name: 'app_profile_address_add', methods: ['GET', 'POST'])]
    public function addAddress(Request $request, EntityManagerInterface $em): Response
    {
        $address = new Address();
        $form = $this->createForm(AddressForm::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $address->setUser($this->getUser());
            $em->persist($address);
            $em->flush();

            $this->addFlash('success', 'Votre adresse a été ajoutée avec succès.');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/add_address.html.twig', [
            'addressForm' => $form,
        ]);
    }

    // ========== MODIFIER UNE ADRESSE ==========
    #[Route('/adresse/{id}/modifier', name: 'app_profile_address_edit', methods: ['GET', 'POST'])]
    public function editAddress(Address $address, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier que l'adresse appartient bien à l'utilisateur connecté
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette adresse.');
        }

        $form = $this->createForm(AddressForm::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Votre adresse a été modifiée avec succès.');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/edit_address.html.twig', [
            'addressForm' => $form,
            'address' => $address,
        ]);
    }

    // ========== SUPPRIMER UNE ADRESSE ==========
    #[Route('/adresse/{id}/supprimer', name: 'app_profile_address_delete', methods: ['POST'])]
    public function deleteAddress(Address $address, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier que l'adresse appartient bien à l'utilisateur connecté
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette adresse.');
        }

        if ($this->isCsrfTokenValid('delete'.$address->getId(), $request->request->get('_token'))) {
            $em->remove($address);
            $em->flush();

            $this->addFlash('success', 'Votre adresse a été supprimée avec succès.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide. Suppression annulée.');
        }

        return $this->redirectToRoute('app_profile_index');
    }

    // ========== SUPPRIMER LE COMPTE ==========
    #[Route('/supprimer', name: 'app_profile_delete', methods: ['GET', 'POST'])]
    public function deleteAccount(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
                $user = $this->getUser();
                
                // Déconnecter l'utilisateur
                $request->getSession()->invalidate();
                $this->container->get('security.token_storage')->setToken(null);

                // Supprimer l'utilisateur (et ses adresses grâce à orphanRemoval)
                $em->remove($user);
                $em->flush();

                $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
                return $this->redirectToRoute('app_home'); // Changez 'app_home' par votre route d'accueil
            } else {
                $this->addFlash('danger', 'Token CSRF invalide. Suppression annulée.');
            }
        }

        return $this->render('profile/delete_account.html.twig');
    }

    // ========== DÉTAIL D'UNE COMMANDE ==========
    #[Route('/commandes/{id}', name: 'app_profile_order_show', methods: ['GET'])]
    public function orderShow(Order $order): Response
    {
        // Vérifier que la commande appartient bien à l'utilisateur connecté
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter cette commande.');
        }

        return $this->render('profile/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    // ========== LISTE DES COMMANDES ==========
    #[Route('/commandes', name: 'app_profile_orders', methods: ['GET'])]
    public function orders(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findBy(
            ['user' => $this->getUser()],
            ['orderDate' => 'DESC']
        );

        return $this->render('profile/orders.html.twig', [
            'orders' => $orders,
        ]);
    }
}