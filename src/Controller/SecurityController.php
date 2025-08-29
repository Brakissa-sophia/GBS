<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    
          #[Route ('/compte', 'app_account')]
    public function account(): Response 
    {

         return $this-> render('home/account.html.twig',[]);

    }


    #[Route ('/adresse', 'app_addresse')]
    public function adresse(): Response 
    {

         return $this-> render('security/addresse.html.twig',[]);

    }

    #[Route ('/commandes', 'app_order_user')]
    public function order(): Response 
    {

         return $this-> render('security/my-orders.html.twig',[]);

    }

    #[Route ('/paramètres', 'app_setting')]
    public function setting(): Response 
    {

         return $this-> render('security/setting.html.twig',[]);

    }

    #[Route(path: '/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

}
