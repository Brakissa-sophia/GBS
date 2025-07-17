<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', []);
    }

    
     #[Route('/catalogue', name: 'app_catalog')]
    public function catalog(): Response
    {
        return $this->render('home/catalogue.html.twig', []);
    }




     #[Route ('/marque', 'app_brand')]
    public function brand(): Response 
    {

         return $this-> render('home/brand.html.twig',[]);

    }

     #[Route ('/produit', 'app_product')]
    public function product(): Response 
    {

         return $this-> render('home/product.html.twig',[]);

    }


     #[Route ('/appareil', 'app_device')]
    public function device(): Response 
    {

         return $this-> render('home/device.html.twig',[]);

    }


         #[Route ('/contact', 'app_contact')]
    public function contact(): Response 
    {

         return $this-> render('home/contact.html.twig',[]);

    }
 


          #[Route ('/favoris', 'app_favorite')]
    public function favorite(): Response 
    {

         return $this-> render('home/favorite.html.twig',[]);

    }




  
 
 
 
 


}



