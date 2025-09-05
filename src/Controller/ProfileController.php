<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-compte')]
final class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $orders = $orderRepository->findBy(
            ['user' => $this->getUser()],
            ['orderDate' => 'DESC']
        );

        return $this->render('profile/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editAccount (): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->render('profile/edit_account.html.twig');
    }

    #[Route('/mot-de-passe/modifier', name: 'app_profile_password_edit', methods: ['GET', 'POST'])]
    public function editPassword(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->render('profile/edit_password.html.twig');
    }

    #[Route('/supprimer', name: 'app_profile_delete', methods: ['GET', 'POST'])]
    public function deleteAccount(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->redirectToRoute('app_profile_index');
    }

    // Détail d’une commande depuis l’espace client
    #[Route('/commandes/{id}', name: 'app_profile_order_show', methods: ['GET'])]
    public function orderShow(Order $order): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('profile/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/commandes', name: 'app_profile_orders', methods: ['GET'])]
    public function orders(OrderRepository $orderRepository): Response
    {
        // Sécurité simple : page accessible uniquement connecté
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $orders = $orderRepository->findBy(
            ['user' => $this->getUser()],
            ['orderDate' => 'DESC']
        );

        return $this->render('profile/orders.html.twig', [
            'orders' => $orders,
        ]);
    }
}

