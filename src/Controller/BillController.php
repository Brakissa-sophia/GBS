<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillController extends AbstractController
{
    #[Route('/editor/order/{id}bill', name: 'app_bill')]
    public function index(): Response
    {
        return $this->render('bill/index.html.twig', [
            'controller_name' => 'BillController',
        ]);
    }
}

