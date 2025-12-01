<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        //  Rediriger si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_account');
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        // Dernier nom d'utilisateur saisi
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error
        ]);
    }

    //  Protection : seuls les utilisateurs connectés peuvent accéder
    #[Route('/compte', name: 'app_account')]
    #[IsGranted('ROLE_USER')]
    public function account(): Response 
    {
        return $this->render('home/account.html.twig');
    }

    //  Correction du nom de la route (addresse → address)
    #[Route('/adresses', name: 'app_address')]
    #[IsGranted('ROLE_USER')]
    public function address(): Response 
    {
        return $this->render('security/address.html.twig');
    }

    #[Route('/commandes', name: 'app_order_user')]
    #[IsGranted('ROLE_USER')]
    public function order(): Response 
    {
        return $this->render('security/my-orders.html.twig');
    }

    //  Correction de l'URL (pas d'accent dans les URLs)
    #[Route('/parametres', name: 'app_setting')]
    #[IsGranted('ROLE_USER')]
    public function setting(): Response 
    {
        return $this->render('security/setting.html.twig');
    }

    #[Route(path: '/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}