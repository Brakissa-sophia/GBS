<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FooterController extends AbstractController
{
    #[Route('/mentions-legales', name: 'app_legal_notices')]
    public function legalNotices(): Response
    {
        return $this->render('footer/legal_notices.html.twig', [
           
        ]);
    }

    #[Route('/cgv', name: 'app_cgv')]
    public function cgv(): Response
    {
        return $this->render('footer/cgv.html.twig', [
           
        ]);
    }


    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response
    {
        return $this->render('footer/cgu.html.twig', [
           
        ]);
    }


    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('footer/contact.html.twig', [
           
        ]);
    }

    #[Route('/politique-de-condidentialite', name: 'app_politique_confidentialite')]
    public function politiqueConfidentialite(): Response
    {
        return $this->render('footer/politique_confidentialite.html.twig', [
           
        ]);
    }
   
    
}
  