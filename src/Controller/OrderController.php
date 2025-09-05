<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderProducts;
use App\Entity\User;
use App\Form\OrderForm;
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route(name: 'app_order')]
    public function index(Request $request, SessionInterface $session): Response
    {
        // Panier depuis la session
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

        // Sous-total
        $subtotal = array_sum(array_map(
            fn ($row) => $row['product']->getPrice() * $row['quantity'],
            $cartWithData
        ));

        // ➜ IMPORTANT : on stocke des RÉFÉRENCES (pas les objets)
        $itemRefs = array_map(fn ($row) => [
            'type'     => $row['type'],
            'id'       => $row['product']->getId(),
            'quantity' => (int) $row['quantity'],
        ], $cartWithData);

        $session->set('cart_item_refs', $itemRefs);
        $session->set('cart_subtotal', $subtotal);

        // Pré-remplissage du formulaire
        $order = new Order();

        /** @var User|null $user */
        $user = $this->getUser();
        if ($user !== null) {
            if ($user->getFirstName()) { $order->setFirstName($user->getFirstName()); }
            if ($user->getLastName())  { $order->setLastName($user->getLastName()); }
            if ($user->getEmail())     { $order->setEmail($user->getEmail()); }
            if ($user->getPhone())     { $order->setPhone($user->getPhone()); }

            $addresses = $user->getAddresses();
            if (!$addresses->isEmpty()) {
                $firstAddress = $addresses->first();
                if ($firstAddress !== false) {
                    $order->setStreet($firstAddress->getStreet());
                    $order->setCity($firstAddress->getCity());
                    $order->setPostalCode($firstAddress->getPostalCode());
                }
            }
        }

        $form = $this->createForm(OrderForm::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Order $orderData */
            $orderData = $form->getData();

            $payload = [
                'firstName'  => trim((string) $orderData->getFirstName()),
                'lastName'   => trim((string) $orderData->getLastName()),
                'email'      => trim((string) $orderData->getEmail()),
                'phone'      => trim((string) $orderData->getPhone()),
                'street'     => trim((string) $orderData->getStreet()),
                'city'       => trim((string) $orderData->getCity()),
                'postalCode' => trim((string) $orderData->getPostalCode()),
            ];

            $required = ['firstName','lastName','email','phone','street','city','postalCode'];
            $missing  = array_filter($required, fn($f) => $payload[$f] === '');
            if ($missing) {
                $this->addFlash('error', 'Veuillez compléter tous les champs requis.');
                return $this->redirectToRoute('app_order');
            }

            $session->set('order_data', $payload);
            return $this->redirectToRoute('app_checkout');
        }

        return $this->render('order/index.html.twig', [
            'form'  => $form->createView(),
            'items' => $cartWithData,
            'total' => $subtotal,
        ]);
    }

    #[Route('/process-payment', name: 'app_process_payment', methods: ['POST'])]
    public function processPayment(Request $request, SessionInterface $session): JsonResponse
    {
        try {
            $paymentMethod = $request->get('payment_method');

            return match ($paymentMethod) {
                'paypal' => $this->processPaypalPayment($request, $session),
                'card'   => $this->processCardPayment($request, $session),
                default  => new JsonResponse(['success' => false, 'error' => 'Méthode de paiement non supportée']),
            };
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Erreur lors du traitement: ' . $e->getMessage()
            ]);
        }
    }

    private function processPaypalPayment(Request $request, SessionInterface $session): JsonResponse
    {
        $paypalOrderId = $request->get('paypal_order_id');

        if (!$paypalOrderId) {
            return new JsonResponse(['success' => false, 'error' => 'Order ID manquant']);
        }

        // On lit les RÉFÉRENCES
        $itemRefs       = $session->get('cart_item_refs', []);
        $orderData      = $session->get('order_data', []);
        if (empty($itemRefs) || empty($orderData)) {
            return new JsonResponse(['success' => false, 'error' => 'Données de commande manquantes']);
        }

        // Recharger les entités et recalculer
        $lines = [];
        $recalcSubtotal = 0.0;

        foreach ($itemRefs as $ref) {
            $qty = (float) ($ref['quantity'] ?? 0);
            if ($qty <= 0) continue;

            if ($ref['type'] === 'product') {
                $entity = $this->productRepository->find((int)$ref['id']);
            } else {
                $entity = $this->deviceRepository->find((int)$ref['id']);
            }
            if (!$entity) continue;

            $price = (float) $entity->getPrice();
            $recalcSubtotal += $price * $qty;

            $lines[] = [
                'type'     => $ref['type'],
                'entity'   => $entity,
                'quantity' => $qty,
                'price'    => $price,
            ];
        }

        // Règle livraison + total (arrondi sécurité)
        $shipping   = $recalcSubtotal > 49 ? 0.0 : 5.99;
        $finalTotal = round($recalcSubtotal + $shipping, 2);

        try {
            $order = new Order();

            if ($this->getUser()) {
                $order->setUser($this->getUser());
            }

            $order->setFirstName((string) $orderData['firstName']);
            $order->setLastName((string) $orderData['lastName']);
            $order->setEmail((string) $orderData['email']);
            $order->setPhone((string) $orderData['phone']);
            $order->setStreet((string) $orderData['street']);
            $order->setCity((string) $orderData['city']);
            $order->setPostalCode((string) $orderData['postalCode']);

            $order->setTotalPrice($finalTotal);
            $order->setPaymentMethod('paypal');
            $order->setPaymentId($paypalOrderId);
            $order->setStatus('paid');

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // Lignes de commande
            foreach ($lines as $line) {
                $op = new OrderProducts();
                $op->setOrder($order);
                $op->setQte($line['quantity']);
                $op->setPrice($line['price']);
                $op->setTotalPrice($line['price'] * $line['quantity']);

                if ($line['type'] === 'product') {
                    $op->setProduct($line['entity']);   // device reste null
                } else {
                    $op->setDevice($line['entity']);    // product reste null
                }

                $this->entityManager->persist($op);
            }
            $this->entityManager->flush();

            // Nettoyage session
            foreach (['cart','order_data','cart_subtotal','cart_items','cart_item_refs'] as $k) {
                $session->remove($k);
            }

            return new JsonResponse([
                'success'      => true,
                'redirect_url' => $this->generateUrl('app_order_confirmation', ['id' => $order->getId()])
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ]);
        }
    }

    private function processCardPayment(Request $request, SessionInterface $session): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error'   => 'Paiement par carte non encore implémenté'
        ]);
    }

    #[Route('/confirmation/{id}', name: 'app_order_confirmation')]
    public function confirmation(Order $order): Response
    {
        if ($this->getUser() && $order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('order/confirmation.html.twig', [
            'order' => $order
        ]);
    }
}
