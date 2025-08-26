<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends AbstractController
{
   public function __construct(
       private readonly ProductRepository $productRepository,
       private readonly DeviceRepository $deviceRepository
   ){
   }

   #[Route(name: 'app_cart')]
   public function index(SessionInterface $session): Response
   {
       $cart = $session->get('cart', []);
       $cartWithData = [];
       
       foreach ($cart as $itemKey => $quantity) {
           // Vérifier le format de la clé et séparer le type et l'ID
           $parts = explode('_', $itemKey);
           
           // S'assurer qu'on a bien 2 parties (type et id)
           if (count($parts) !== 2) {
               // Ignorer les entrées mal formatées
               continue;
           }
           
           [$type, $id] = $parts;
           
           // Vérifier que le type est valide
           if (!in_array($type, ['product', 'device'])) {
               continue;
           }
           
           if ($type === 'product') {
               $item = $this->productRepository->find($id);
               $uploadPath = 'products';
           } else { // device
               $item = $this->deviceRepository->find($id);
               $uploadPath = 'devices';
           }
           
           if ($item) {
               // Récupérer l'image
               $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $uploadPath . '/*-' . $item->getId() . '-1-*.*';
               $files = glob($pattern);
               $image = !empty($files) ? '/uploads/' . $uploadPath . '/' . basename($files[0]) : '/images/no-image.jpg';
               
               $cartWithData[] = [
                   'product' => $item, // On garde 'product' pour la compatibilité avec le template
                   'quantity' => $quantity,
                   'image' => $image,
                   'type' => $type
               ];
           }
       }

       $total = array_sum(array_map(function ($item){
           return $item['product']->getPrice() * $item['quantity'];
       }, $cartWithData));

       return $this->render('cart/index.html.twig', [
           'items' => $cartWithData,
           'total' => $total
       ]);
   }

   #[Route('/add/{type}/{id}', name: 'app_cart_add', methods: ['GET'], requirements: ['type' => 'product|device'])]
   public function addToCart(string $type, int $id, SessionInterface $session): Response
   {
       $cart = $session->get('cart', []);
       $itemKey = $type . '_' . $id; // Clé unique : "product_1" ou "device_5"
       
       if (!empty($cart[$itemKey])) {
           $cart[$itemKey]++;
       } else {
           $cart[$itemKey] = 1;
       }

       $session->set('cart', $cart);

       return $this->redirectToRoute('app_cart');
   }

   #[Route('/remove/{type}/{id}', name: 'app_cart_remove', methods: ['GET'], requirements: ['type' => 'product|device'])]
   public function removeFromCart(string $type, int $id, SessionInterface $session): Response
   {
       $cart = $session->get('cart', []);
       $itemKey = $type . '_' . $id; // Clé unique : "product_1" ou "device_5"
       
       if (!empty($cart[$itemKey])) {
           unset($cart[$itemKey]);
       }

         $session->set('cart', $cart);

       return $this->redirectToRoute('app_cart');
   }



   #[Route('/clear', name: 'app_cart_clear', methods: ['GET'])]
   public function clearCart(SessionInterface $session): Response
   {
       $session->remove('cart');
       return $this->redirectToRoute('app_cart');
   }

}

