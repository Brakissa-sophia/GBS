<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderProducts;
use App\Entity\User;
use App\Form\OrderForm;
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use App\Repository\OrderRepository;
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
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[Route('/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer
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

        // Debug : vérifier l'état du formulaire
        $isSubmitted = $form->isSubmitted();
        file_put_contents('debug.txt', 'Form submitted: ' . ($isSubmitted ? 'OUI' : 'NON') . PHP_EOL, FILE_APPEND);
        
        if ($isSubmitted) {
            $isValid = $form->isValid();
            file_put_contents('debug.txt', 'Form valid: ' . ($isValid ? 'OUI' : 'NON') . PHP_EOL, FILE_APPEND);
            
            if (!$isValid) {
                file_put_contents('debug.txt', 'ERREURS FORMULAIRE: ' . print_r($form->getErrors(true), true) . PHP_EOL, FILE_APPEND);
            }
        }

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

            // Code de débogage ajouté ici
            $saveInfo = $request->request->get('save-info', false);
            error_log('Checkbox save-info reçue: ' . ($saveInfo ? 'OUI' : 'NON'));
            $session->set('save_user_info', $saveInfo);

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
            $order->setIsCompleted(false);

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
                    $op->setProduct($line['entity']);
                } else {
                    $op->setDevice($line['entity']);
                }

                $this->entityManager->persist($op);
            }
            $this->entityManager->flush();

            // Envoi de l'email de confirmation
            $this->sendOrderConfirmationEmail($order, $recalcSubtotal, $shipping);

            // Nettoyage session
            foreach (['cart','order_data','cart_subtotal','cart_items','cart_item_refs','save_user_info'] as $k) {
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

        $shipping = $recalcSubtotal > 49 ? 0.0 : 5.99;
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
            $order->setPaymentMethod('card');
            $order->setPaymentId('CARD_' . uniqid());
            $order->setStatus('paid');
            $order->setIsCompleted(false);

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
                    $op->setProduct($line['entity']);
                } else {
                    $op->setDevice($line['entity']);
                }

                $this->entityManager->persist($op);
            }
            $this->entityManager->flush();

            // Envoi de l'email de confirmation
           // $this->sendOrderConfirmationEmail($order, $recalcSubtotal, $shipping);

            // Sauvegarder les informations utilisateur si connecté et si checkbox était cochée
            if ($this->getUser() && $session->get('save_user_info', false)) {
                $this->saveUserInformation($this->getUser(), $orderData);
            }

            // Nettoyage session
            foreach (['cart','order_data','cart_subtotal','cart_items','cart_item_refs','save_user_info'] as $k) {
                $session->remove($k);
            }

            return new JsonResponse([
                'success' => true,
                'redirect_url' => $this->generateUrl('app_order_confirmation', ['id' => $order->getId()])
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors du traitement par carte: ' . $e->getMessage()
            ]);
        }
    }

    private function sendOrderConfirmationEmail(Order $order, float $subtotal, float $shipping): void
    {
        try {
            // Forcer le rechargement de l'ordre avec ses relations
            $this->entityManager->refresh($order);
            
            // Préparer les données des produits avec images
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
                    'shipping' => $shipping,
                    'total' => $order->getTotalPrice(),
                    'user' => $order->getUser() ?: (object)['lastName' => $order->getLastName()]
                ]);

            $this->mailer->send($email);

        } catch (\Exception $e) {
            error_log('Erreur envoi email confirmation commande #' . $order->getOrderNumber() . ': ' . $e->getMessage());
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

        $order= $paginator->paginate(
            $data,
            $request->query->getInt('page', 1),
            5
        );

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
        
        $allowedStatuses = ['paid', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $order->setStatus($newStatus);
        
        if ($newStatus === 'paid') {
            $order->setIsCompleted(false);
        }
        
        $this->entityManager->flush();

        $statusLabel = $newStatus === 'paid' ? 'payée' : 'annulée';
        $this->addFlash('success', "Le statut de la commande a été mis à jour : {$statusLabel}.");

        return $this->redirectToRoute('app_admin_orders');
    }

    #[Route('/admin/orders/{id}/delivered/update', name: 'app_admin_order_delivered_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markAsDelivered(Order $order, EntityManagerInterface $em): Response
    {
        if ($order->getStatus() !== 'paid') {
            $this->addFlash('error', 'Seules les commandes payées peuvent être marquées comme livrées.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $order->setIsCompleted(true);
        $em->flush();

        $this->addFlash('success', 'La commande a été marquée comme livrée.');
        return $this->redirectToRoute('app_admin_orders');
    }

    #[Route('/admin/orders/{id}/delete', name: 'app_admin_order_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteOrder(Order $order): Response
    {
        $this->entityManager->remove($order);
        $this->entityManager->flush();

        $this->addFlash('success', 'La commande a été supprimée.');
        return $this->redirectToRoute('app_admin_orders');
    }

    private function saveUserInformation(User $user, array $orderData): void
    {
        try {
            // Mettre à jour le téléphone si pas encore renseigné ou différent
            if (empty($user->getPhone()) || $user->getPhone() !== $orderData['phone']) {
                $user->setPhone($orderData['phone']);
            }

            // Vérifier si l'adresse existe déjà
            $addressExists = false;
            foreach ($user->getAddresses() as $address) {
                if ($address->getStreet() === $orderData['street'] && 
                    $address->getCity() === $orderData['city'] && 
                    $address->getPostalCode() === $orderData['postalCode']) {
                    $addressExists = true;
                    break;
                }
            }

            // Créer une nouvelle adresse si elle n'existe pas
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
            error_log('Erreur sauvegarde informations utilisateur: ' . $e->getMessage());
        }
    }
}