<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderProducts;
use App\Entity\User;
use App\Form\OrderForm;
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use App\Repository\OrderRepository;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[Route('/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly PromoCodeRepository $promoCodeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer
    ) {}

    #[Route('/test-flasher', name: 'app_test_flasher')]
    public function testFlasher(): Response
    {
        flash()
        ->option('position', 'top-center') 
        ->option('timeout', 10000)
        ->success(' Test réussi : aFlasher fonctionne !');       
        flash()->error(' Ceci est un message d\'erreur');
        flash()->info('Message d\'information');
        flash()->warning('Message d\'avertissement');
        
        return $this->redirectToRoute('app_admin_orders');
    }

    #[Route(name: 'app_order')]
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

        // Calcul sous-total
        $subtotal = array_sum(array_map(
            fn ($row) => $row['product']->getPrice() * $row['quantity'],
            $cartWithData
        ));

        // === GESTION CODE PROMO ===
        $discount = 0;
        $promoCodeEntity = null;
        $appliedPromo = $session->get('promo_code');
        
        if ($appliedPromo) {
            $promoCodeEntity = $this->promoCodeRepository->findByCode($appliedPromo);
            
            if ($promoCodeEntity && $promoCodeEntity->isValid()) {
                // Vérifier si l'utilisateur peut utiliser ce code
                $currentUser = $this->getUser();
                
                if (!$this->promoCodeRepository->canBeUsedByUser($promoCodeEntity, $currentUser)) {
                    $maxUses = $promoCodeEntity->getMaxUsesPerUser();
                    if ($maxUses !== null) {
                        flash()->error("Vous avez déjà utilisé ce code promo le nombre maximum de fois autorisé ({$maxUses}).");
                    } else {
                        flash()->error("Vous ne pouvez pas utiliser ce code promo.");
                    }
                    $session->remove('promo_code');
                    $appliedPromo = null;
                    $promoCodeEntity = null;
                } else {
                    // Calculer la réduction uniquement sur les produits éligibles
                    $eligibleAmount = 0;
                    foreach ($cartWithData as $item) {
                        if ($promoCodeEntity->isEligible($item['product'])) {
                            $eligibleAmount += $item['product']->getPrice() * $item['quantity'];
                        }
                    }
                    $discount = $promoCodeEntity->calculateDiscount($eligibleAmount);
                }
            } else {
                flash()->error('Ce code promo n\'est plus valide.');
                $session->remove('promo_code');
                $appliedPromo = null;
            }
        }

        $subtotalAfterDiscount = $subtotal - $discount;
        $shipping = $subtotalAfterDiscount >= 49 ? 0 : 5.99;
        $finalTotal = $subtotalAfterDiscount + $shipping;
        // === FIN GESTION PROMO ===

        // Stockage refs pour paiement
        $itemRefs = array_map(fn ($row) => [
            'type'     => $row['type'],
            'id'       => $row['product']->getId(),
            'quantity' => (int) $row['quantity'],
        ], $cartWithData);

        $session->set('cart_item_refs', $itemRefs);
        $session->set('cart_subtotal', $subtotal);
        $session->set('cart_discount', $discount);
        $session->set('cart_promo_code_id', $promoCodeEntity?->getId());

        // Pré-remplissage formulaire
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
                flash()->error('Veuillez compléter tous les champs requis.');
                return $this->redirectToRoute('app_order');
            }

            $saveInfo = $request->request->get('save-info');
            $saveInfoBool = $saveInfo === '1' || $saveInfo === 'on' || $saveInfo === true;
            
            $session->set('save_user_info', $saveInfoBool);
            $session->set('order_data', $payload);
            
            return $this->redirectToRoute('app_checkout');
        }

        return $this->render('order/index.html.twig', [
            'form'  => $form->createView(),
            'items' => $cartWithData,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'subtotalAfterDiscount' => $subtotalAfterDiscount,
            'finalTotal' => $finalTotal,
            'appliedPromo' => $appliedPromo,
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

        $itemRefs = $session->get('cart_item_refs', []);
        $orderData = $session->get('order_data', []);
        $discount = $session->get('cart_discount', 0);
        $promoCodeId = $session->get('cart_promo_code_id');

        if (empty($itemRefs) || empty($orderData)) {
            return new JsonResponse(['success' => false, 'error' => 'Données de commande manquantes']);
        }

        $lines = [];
        $recalcSubtotal = 0.0;

        foreach ($itemRefs as $ref) {
            $qty = (float) ($ref['quantity'] ?? 0);
            if ($qty <= 0) continue;

            $entity = $ref['type'] === 'product'
                ? $this->productRepository->find((int)$ref['id'])
                : $this->deviceRepository->find((int)$ref['id']);
            
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

        $subtotalAfterDiscount = $recalcSubtotal - $discount;
        $shipping = $subtotalAfterDiscount >= 49 ? 0.0 : 5.99;
        $finalTotal = round($subtotalAfterDiscount + $shipping, 2);

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

            // Enregistrer le code promo
            $promoCodeEntity = null;
            if ($promoCodeId) {
                $promoCodeEntity = $this->promoCodeRepository->find($promoCodeId);
                if ($promoCodeEntity) {
                    $order->setPromoCode($promoCodeEntity);
                    $order->setDiscountAmount($discount);
                }
            }

            $order->setTotalPrice($finalTotal);
            $order->setPaymentMethod('paypal');
            $order->setPaymentId($paypalOrderId);
            $order->setStatus('paid');
            $order->setIsCompleted(false);

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // Enregistrer l'utilisation du code promo dans PromoCodeUsage
            if ($promoCodeEntity && $discount > 0) {
                $promoUsage = new \App\Entity\PromoCodeUsage();
                $promoUsage->setPromoCode($promoCodeEntity);
                $promoUsage->setUser($this->getUser());
                $promoUsage->setOrderRef($order);
                $promoUsage->setDiscountApplied($discount);
                $this->entityManager->persist($promoUsage);
                $this->entityManager->flush();
            }

            // Lignes de commande
            foreach ($lines as $line) {
                $op = new OrderProducts();
                $op->setOrder($order);
                $op->setQte($line['quantity']);
                $op->setPrice($line['price']);
                $op->setTotalPrice($line['price'] * $line['quantity']);

                if ($line['type'] === 'product') {
                    $op->setProduct($line['entity']);
                } else {
                    $op->setDevice($line['entity']);
                }
                $this->entityManager->persist($op);
            }
            $this->entityManager->flush();

            if ($this->getUser() && $session->get('save_user_info', false)) {
                $this->saveUserInformation($this->getUser(), $orderData);
            }

            $this->sendOrderConfirmationEmail($order, $recalcSubtotal, $discount, $shipping);

            foreach (['cart','order_data','cart_subtotal','cart_items','cart_item_refs','save_user_info','promo_code','cart_discount','cart_promo_code_id'] as $k) {
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
        $itemRefs = $session->get('cart_item_refs', []);
        $orderData = $session->get('order_data', []);
        $discount = $session->get('cart_discount', 0);
        $promoCodeId = $session->get('cart_promo_code_id');

        if (empty($itemRefs) || empty($orderData)) {
            return new JsonResponse(['success' => false, 'error' => 'Données de commande manquantes']);
        }

        $lines = [];
        $recalcSubtotal = 0.0;

        foreach ($itemRefs as $ref) {
            $qty = (float) ($ref['quantity'] ?? 0);
            if ($qty <= 0) continue;

            $entity = $ref['type'] === 'product'
                ? $this->productRepository->find((int)$ref['id'])
                : $this->deviceRepository->find((int)$ref['id']);
            
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

        $subtotalAfterDiscount = $recalcSubtotal - $discount;
        $shipping = $subtotalAfterDiscount >= 49 ? 0.0 : 5.99;
        $finalTotal = round($subtotalAfterDiscount + $shipping, 2);

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

            // Enregistrer le code promo
            $promoCodeEntity = null;
            if ($promoCodeId) {
                $promoCodeEntity = $this->promoCodeRepository->find($promoCodeId);
                if ($promoCodeEntity) {
                    $order->setPromoCode($promoCodeEntity);
                    $order->setDiscountAmount($discount);
                }
            }

            $order->setTotalPrice($finalTotal);
            $order->setPaymentMethod('card');
            $order->setPaymentId('CARD_' . uniqid());
            $order->setStatus('paid');
            $order->setIsCompleted(false);

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // Enregistrer l'utilisation du code promo
            if ($promoCodeEntity && $discount > 0) {
                $promoUsage = new \App\Entity\PromoCodeUsage();
                $promoUsage->setPromoCode($promoCodeEntity);
                $promoUsage->setUser($this->getUser());
                $promoUsage->setOrderRef($order);
                $promoUsage->setDiscountApplied($discount);
                $this->entityManager->persist($promoUsage);
                $this->entityManager->flush();
            }

            foreach ($lines as $line) {
                $op = new OrderProducts();
                $op->setOrder($order);
                $op->setQte($line['quantity']);
                $op->setPrice($line['price']);
                $op->setTotalPrice($line['price'] * $line['quantity']);

                if ($line['type'] === 'product') {
                    $op->setProduct($line['entity']);
                } else {
                    $op->setDevice($line['entity']);
                }
                $this->entityManager->persist($op);
            }
            $this->entityManager->flush();

            if ($this->getUser() && $session->get('save_user_info', false)) {
                $this->saveUserInformation($this->getUser(), $orderData);
            }

            $this->sendOrderConfirmationEmail($order, $recalcSubtotal, $discount, $shipping);

            foreach (['cart','order_data','cart_subtotal','cart_items','cart_item_refs','save_user_info','promo_code','cart_discount','cart_promo_code_id'] as $k) {
                $session->remove($k);
            }

            return new JsonResponse([
                'success' => true,
                'redirect_url' => $this->generateUrl('app_order_confirmation', ['id' => $order->getId()])
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }

    private function sendOrderConfirmationEmail(Order $order, float $subtotal, float $discount, float $shipping): void
    {
        try {
            $this->entityManager->refresh($order);
            
            $orderItems = [];
            foreach ($order->getOrderProducts() as $orderProduct) {
                $item = null;
                $type = '';
                
                if ($product = $orderProduct->getProduct()) {
                    $item = $product;
                    $type = 'product';
                } elseif ($device = $orderProduct->getDevice()) {
                    $item = $device;
                    $type = 'device';
                }

                if ($item) {
                    $orderItems[] = [
                        'name' => $item->getTitle(),
                        'description' => $item->getDescription(),
                        'quantity' => $orderProduct->getQte(),
                        'price' => $orderProduct->getPrice(),
                        'total' => $orderProduct->getTotalPrice(),
                        'image' => $this->findMainImage($type === 'product' ? 'products' : 'devices', $item->getId()),
                        'type' => $type
                    ];
                }
            }

            $email = (new TemplatedEmail())
                ->from('noreply@glowbeautyskin.com')
                ->to($order->getEmail())
                ->subject('Confirmation de votre commande #' . $order->getOrderNumber())
                ->htmlTemplate('email/order_confirmation.html.twig')
                ->context([
                    'order' => $order,
                    'orderItems' => $orderItems,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'shipping' => $shipping,
                    'total' => $order->getTotalPrice(),
                    'user' => $order->getUser() ?: (object)['lastName' => $order->getLastName()],
                    'promoCode' => $order->getPromoCode()
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Erreur email: ' . $e->getMessage());
        }
    }

    #[Route('/confirmation/{id}', name: 'app_order_confirmation')]
    public function confirmation(Order $order): Response
    {
        if ($this->getUser() && !$this->isGranted('ROLE_ADMIN') && $order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('order/confirmation.html.twig', [
            'order' => $order
        ]);
    }

    #[Route('/admin/orders', name: 'app_admin_orders')]
    #[IsGranted('ROLE_ADMIN')]
    public function getAllOrders(OrderRepository $orderRepository, Request $request, PaginatorInterface $paginator): Response
    {   
        $data = $orderRepository->findBy([], ['orderDate' => 'DESC']);
        $order= $paginator->paginate($data, $request->query->getInt('page', 1), 10);

        return $this->render('order/orders.html.twig', [
            'orders' => $order
        ]);
    }

    #[Route('/admin/orders/{id}', name: 'app_admin_order_details')]
    #[IsGranted('ROLE_ADMIN')]
    public function orderDetails(Order $order): Response
    {
        if ($this->getUser() && $order->getUser() !== $this->getUser()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException();
            }
        }

        $productsData = [];
        $devicesData  = [];
        $subtotalProducts = 0.0;
        $subtotalDevices  = 0.0;

        foreach ($order->getOrderProducts() as $op) {
            if ($product = $op->getProduct()) {
                $productsData[] = [
                    'title'       => $product->getTitle(),
                    'description' => $product->getDescription(),
                    'quantity'    => $op->getQte(),
                    'price'       => $op->getPrice(),
                    'total'       => $op->getTotalPrice(),
                    'image'       => $this->findMainImage('products', $product->getId()),
                ];
                $subtotalProducts += (float) $op->getTotalPrice();
            } elseif ($device = $op->getDevice()) {
                $devicesData[] = [
                    'title'       => $device->getTitle(),
                    'description' => $device->getDescription(),
                    'quantity'    => $op->getQte(),
                    'price'       => $op->getPrice(),
                    'total'       => $op->getTotalPrice(),
                    'image'       => $this->findMainImage('devices', $device->getId()),
                ];
                $subtotalDevices += (float) $op->getTotalPrice();
            }
        }

        return $this->render('order/order_details.html.twig', [
            'order'            => $order,
            'productsData'     => $productsData,
            'devicesData'      => $devicesData,
            'subtotalProducts' => $subtotalProducts,
            'subtotalDevices'  => $subtotalDevices,
        ]);
    }

    private function findMainImage(string $folder, int $id): ?string
    {
        $pattern = $_SERVER['DOCUMENT_ROOT'] . "/uploads/{$folder}/*-{$id}-1-*.*";
        $files = glob($pattern);
        return !empty($files) ? "/uploads/{$folder}/" . basename($files[0]) : null;
    }

    #[Route('/admin/orders/{id}/status', name: 'app_admin_order_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateOrderStatus(Order $order, Request $request): Response 
    {
        $newStatus = $request->get('status');
        if (!in_array($newStatus, ['paid', 'cancelled'])) {
            flash()->error('Statut invalide.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $order->setStatus($newStatus);
        if ($newStatus === 'paid') $order->setIsCompleted(false);
        $this->entityManager->flush();

        flash()->success('Statut mis à jour avec succès.');
        return $this->redirectToRoute('app_admin_orders');
    }

    #[Route('/admin/orders/{id}/delivered/update', name: 'app_admin_order_delivered_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markAsDelivered(Order $order, EntityManagerInterface $em): Response
    {
        if ($order->getStatus() !== 'paid') {
            flash()->error('Seules les commandes payées peuvent être livrées.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $order->setIsCompleted(true);
        $em->flush();

        flash()->success('Commande marquée comme livrée.');
        return $this->redirectToRoute('app_admin_orders');
    }

    #[Route('/admin/orders/{id}/delete', name: 'app_admin_order_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteOrder(Order $order): Response
    {
        $this->entityManager->remove($order);
        $this->entityManager->flush();

        flash()->success('Commande supprimée avec succès.');
        return $this->redirectToRoute('app_admin_orders');
    }

    private function saveUserInformation(User $user, array $orderData): void
    {
        try {
            $currentPhone = $user->getPhone();
            $newPhone = $orderData['phone'];
            
            if (empty($currentPhone) || $currentPhone !== $newPhone) {
                $user->setPhone($newPhone);
            }

            $addresses = $user->getAddresses();
            $addressExists = false;
            
            foreach ($addresses as $address) {
                if ($address->getStreet() === $orderData['street'] && 
                    $address->getCity() === $orderData['city'] && 
                    $address->getPostalCode() === $orderData['postalCode']) {
                    $addressExists = true;
                    break;
                }
            }

            if (!$addressExists) {
                $newAddress = new \App\Entity\Address();
                $newAddress->setStreet($orderData['street']);
                $newAddress->setCity($orderData['city']);
                $newAddress->setPostalCode($orderData['postalCode']);
                $newAddress->setUser($user);
                
                $this->entityManager->persist($newAddress);
                $user->addAddress($newAddress);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Erreur sauvegarde: ' . $e->getMessage());
        }
    }
}