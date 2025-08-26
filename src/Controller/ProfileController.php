<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/mon-compte')]
final class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile_index', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', []);
    }

    #[Route('/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function editAccount (): Response
    {
        return $this->render('profile/edit_account.html.twig', []);
    }


    #[Route('/mot-de-passe/modifier', name: 'app_profile_password_edit', methods: ['GET', 'POST'])]
    public function editPassword(): Response
    {
        return $this->render('profile/edit_password.html.twig', []);
    }


    #[Route('/supprimer', name: 'app_profile_delete', methods: ['GET', 'POST'])]
    public function deleteAccount(): Response
    {
        return $this->redirectToRoute('app_profile_index');
    }


}
