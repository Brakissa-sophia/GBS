<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use App\Repository\PromoCodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly PromoCodeRepository $promoCodeRepository
    ){}

    #[Route(name: 'app_cart')]
    public function index(SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $cartWithData = [];
        
        foreach ($cart as $itemKey => $quantity) {
            $parts = explode('_', $itemKey);
            if (count($parts) !== 2) continue;
            
            [$type, $id] = $parts;
            if (!in_array($type, ['product', 'device'])) continue;
            
            $item = $type === 'product' 
                ? $this->productRepository->find($id)
                : $this->deviceRepository->find($id);
            
            if ($item) {
                $uploadPath = $type === 'product' ? 'products' : 'devices';
                $pattern = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $uploadPath . '/*-' . $item->getId() . '-1-*.*';
                $files = glob($pattern);
                $image = !empty($files) ? '/uploads/' . $uploadPath . '/' . basename($files[0]) : '/images/no-image.jpg';
                
                $cartWithData[] = [
                    'product' => $item,
                    'quantity' => $quantity,
                    'image' => $image,
                    'type' => $type
                ];
            }
        }

        // Calcul du sous-total
        $subtotal = array_sum(array_map(fn($item) => $item['product']->getPrice() * $item['quantity'], $cartWithData));

        // Gestion du code promo
        $discount = 0;
        $appliedPromo = $session->get('promo_code');
        
        if ($appliedPromo) {
            $promoCode = $this->promoCodeRepository->findByCode($appliedPromo);
            if ($promoCode && $promoCode->isValid()) {
                $eligibleAmount = 0;
                foreach ($cartWithData as $item) {
                    if ($promoCode->isEligible($item['product'])) {
                        $eligibleAmount += $item['product']->getPrice() * $item['quantity'];
                    }
                }
                $discount = $promoCode->calculateDiscount($eligibleAmount);
            } else {
                // Code invalide ou expiré, le retirer
                $session->remove('promo_code');
                $appliedPromo = null;
            }
        }

        // Sous-total après réduction
        $subtotalAfterDiscount = $subtotal - $discount;

        // Calcul livraison
        $shipping = $subtotalAfterDiscount >= 49 ? 0 : 5.99;

        // Total final
        $finalTotal = $subtotalAfterDiscount + $shipping;

        return $this->render('cart/index.html.twig', [
            'items' => $cartWithData,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'subtotalAfterDiscount' => $subtotalAfterDiscount,
            'finalTotal' => $finalTotal,
            'total' => $subtotal, // Pour compatibilité
            'appliedPromo' => $appliedPromo
        ]);
    }

    #[Route('/add/{type}/{id}', name: 'app_cart_add', methods: ['GET'], requirements: ['type' => 'product|device'])]
    public function addToCart(string $type, int $id, Request $request, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $itemKey = $type . '_' . $id;
        $quantity = max(1, (int) $request->query->get('quantity', 1));
        
        $cart[$itemKey] = ($cart[$itemKey] ?? 0) + $quantity;
        $session->set('cart', $cart);

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/update/{type}/{id}/{action}', name: 'app_cart_update_quantity', methods: ['GET'], requirements: ['type' => 'product|device', 'action' => 'increase|decrease'])]
    public function updateQuantity(string $type, int $id, string $action, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $itemKey = $type . '_' . $id;
        
        if (isset($cart[$itemKey])) {
            if ($action === 'increase') {
                $cart[$itemKey]++;
            } elseif ($action === 'decrease' && $cart[$itemKey] > 1) {
                $cart[$itemKey]--;
            }
            $session->set('cart', $cart);
        }
        
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/remove/{type}/{id}', name: 'app_cart_remove', methods: ['GET'], requirements: ['type' => 'product|device'])]
    public function removeFromCart(string $type, int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        unset($cart[$type . '_' . $id]);
        $session->set('cart', $cart);

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['GET'])]
    public function clearCart(SessionInterface $session): Response
    {
        $session->remove('cart');
        $session->remove('promo_code');
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/promo/remove', name: 'app_cart_remove_promo', methods: ['GET'])]
    public function removePromo(SessionInterface $session): Response
    {
        $session->remove('promo_code');
        $this->addFlash('info', 'Code promo retiré.');
        return $this->redirectToRoute('app_cart');
    }
}