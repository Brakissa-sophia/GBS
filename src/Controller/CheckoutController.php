<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/checkout')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository
    ) {}

    #[Route(name: 'app_checkout')]
    public function index(Request $request, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $cartWithData = [];

        foreach ($cart as $itemKey => $quantity) {
            $parts = explode('_', (string) $itemKey);
            if (count($parts) !== 2) continue;

            [$type, $id] = $parts;
            if (!in_array($type, ['product', 'device'], true)) continue;

            $id = (int) $id;
            $item = $type === 'product'
                ? $this->productRepository->find($id)
                : $this->deviceRepository->find($id);

            if ($item) {
                $cartWithData[] = [
                    'product'  => $item,
                    'quantity' => (int) $quantity,
                    'type'     => $type,
                ];
            }
        }

        if (empty($cartWithData)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_order');
        }

        $subtotal = array_sum(array_map(
            fn ($row) => $row['product']->getPrice() * $row['quantity'],
            $cartWithData
        ));
        $shippingCost = $subtotal > 49 ? 0.0 : 5.99;
        $finalTotal   = $subtotal + $shippingCost;

        // ➜ IMPORTANT : refs pour le POST PayPal
        $itemRefs = array_map(fn ($row) => [
            'type'     => $row['type'],
            'id'       => $row['product']->getId(),
            'quantity' => (int) $row['quantity'],
        ], $cartWithData);

        $session->set('cart_item_refs', $itemRefs);
        $session->set('cart_subtotal', $subtotal);

        $orderData = $session->get('order_data', []);
        if (empty($orderData)) {
            $this->addFlash('error', 'Veuillez d\'abord remplir vos informations de livraison.');
            return $this->redirectToRoute('app_order');
        }

        if ($request->isMethod('POST')) {
            $paymentMethod = $request->request->get('payment_method');
            if ($paymentMethod === 'card') {
                $this->addFlash('success', 'Paiement par carte traité avec succès !');
            } elseif ($paymentMethod === 'paypal') {
                $this->addFlash('info', 'Redirection vers PayPal...');
            }
        }

        return $this->render('checkout/index.html.twig', [
            'items'        => $cartWithData,
            'total'        => $subtotal,
            'orderData'    => $orderData,
            'shippingCost' => $shippingCost,
            'finalTotal'   => $finalTotal,
        ]);
    }
}
