<?php

// Déclaration du namespace du contrôleur
namespace App\Controller;

// Importation des entités utilisées
use App\Entity\Order;           // Entité représentant une commande
use App\Entity\OrderProducts;   // Entité représentant les lignes de commande (produits commandés)
use App\Entity\User;            // Entité représentant un utilisateur

// Importation du formulaire de commande
use App\Form\OrderForm;

// Importation des repositories pour accéder aux données
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use App\Repository\OrderRepository;
use App\Repository\PromoCodeRepository;

// Importation de Doctrine pour la gestion de la base de données
use Doctrine\ORM\EntityManagerInterface;

// Importation du paginateur
use Knp\Component\Pager\PaginatorInterface;

// Importation des classes de base Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Importation des classes pour l'envoi d'emails
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/**
 * Contrôleur de gestion des commandes
 * Gère le processus complet de commande: panier, paiement, confirmation
 * Inclut également l'interface d'administration des commandes
 * 
 * Toutes les routes sont préfixées par '/order'
 */
#[Route('/order')]
class OrderController extends AbstractController
{
    /**
     * Constructeur avec injection de dépendances
     * Les propriétés readonly sont injectées automatiquement par Symfony
     * et ne peuvent pas être modifiées après initialisation
     * 
     * @param ProductRepository $productRepository Repository des produits
     * @param DeviceRepository $deviceRepository Repository des appareils
     * @param PromoCodeRepository $promoCodeRepository Repository des codes promo
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
     * @param MailerInterface $mailer Service d'envoi d'emails
     */
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly PromoCodeRepository $promoCodeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer
    ) {}


    /**
     * Page principale du processus de commande
     * Affiche le panier, calcule les totaux avec réductions éventuelles,
     * et gère le formulaire de saisie des informations de livraison
     * 
     * @param Request $request Requête HTTP
     * @param SessionInterface $session Service de gestion des sessions
     * @return Response Rendu de la page de commande
     */
    #[Route(name: 'app_order')]
    public function index(Request $request, SessionInterface $session): Response
    {
        // Récupération du panier depuis la session (tableau vide par défaut)
        $cart = $session->get('cart', []);
        
        // Tableau pour stocker les articles du panier avec leurs données complètes
        $cartWithData = [];

        // Parcourir chaque article du panier
        foreach ($cart as $itemKey => $quantity) {
            // Le format de la clé est: "type_id" (ex: "product_5" ou "device_12")
            $parts = explode('_', (string) $itemKey);
            
            // Vérifier que la clé a bien 2 parties (type et id)
            if (count($parts) !== 2) continue;

            // Extraction du type et de l'id
            [$type, $id] = $parts;
            
            // Vérifier que le type est valide (product ou device)
            if (!in_array($type, ['product', 'device'], true)) continue;

            // Convertir l'id en entier
            $id = (int) $id;
            
            // Récupérer l'entité correspondante selon le type
            $item = $type === 'product'
                ? $this->productRepository->find($id)
                : $this->deviceRepository->find($id);

            // Si l'article existe en base de données
            if ($item) {
                // Ajouter l'article avec ses données au tableau
                $cartWithData[] = [
                    'product'  => $item,              // L'entité Product ou Device
                    'quantity' => (int) $quantity,    // Quantité commandée
                    'type'     => $type,              // Type: 'product' ou 'device'
                ];
            }
        }

        // === CALCUL DU SOUS-TOTAL ===
        // Somme de tous les (prix × quantité) des articles
        $subtotal = array_sum(array_map(
            fn ($row) => $row['product']->getPrice() * $row['quantity'],
            $cartWithData
        ));

        // === GESTION DES CODES PROMO ===
        $discount = 0;                                      // Montant de la réduction
        $promoCodeEntity = null;                           // Entité du code promo
        $appliedPromo = $session->get('promo_code');       // Code promo en session
        
        // Si un code promo est appliqué
        if ($appliedPromo) {
            // Rechercher le code promo dans la base de données
            $promoCodeEntity = $this->promoCodeRepository->findByCode($appliedPromo);
            
            // Vérifier que le code existe et est valide (dates de validité, actif, etc.)
            if ($promoCodeEntity && $promoCodeEntity->isValid()) {
                // Récupérer l'utilisateur connecté
                $currentUser = $this->getUser();
                
                // ✅ CORRECTION : Vérifier uniquement si l'utilisateur est connecté ET que le code a une limite
                if ($currentUser && $promoCodeEntity->getMaxUsesPerUser() !== null) {
                    // Vérifier si l'utilisateur peut utiliser ce code
                    if (!$this->promoCodeRepository->canBeUsedByUser($promoCodeEntity, $currentUser)) {
                        $maxUses = $promoCodeEntity->getMaxUsesPerUser();
                        flash()->error("Vous avez déjà utilisé ce code promo le nombre maximum de fois autorisé ({$maxUses}).");
                        
                        // Retirer le code promo de la session
                        $session->remove('promo_code');
                        $appliedPromo = null;
                        $promoCodeEntity = null;
                    }
                }
                
                // Si le code promo est toujours valide, calculer la réduction
                if ($promoCodeEntity) {
                    // Calculer le montant éligible à la réduction
                    $eligibleAmount = 0;
                    foreach ($cartWithData as $item) {
                        if ($promoCodeEntity->isEligible($item['product'])) {
                            $eligibleAmount += $item['product']->getPrice() * $item['quantity'];
                        }
                    }
                    
                    // Calculer la réduction selon le type (pourcentage ou montant fixe)
                    $discount = $promoCodeEntity->calculateDiscount($eligibleAmount);
                }
            } else {
                // Le code promo n'est plus valide (expiré, désactivé, etc.)
                flash()->error('Ce code promo n\'est plus valide.');
                $session->remove('promo_code');
                $appliedPromo = null;
            }
        }

        // === CALCULS FINAUX ===
        $subtotalAfterDiscount = $subtotal - $discount;                      // Sous-total après réduction
        $shipping = $subtotalAfterDiscount >= 49 ? 0 : 5.99;                 // Frais de port (gratuits si > 49€)
        $finalTotal = $subtotalAfterDiscount + $shipping;                    // Total final à payer
        // === FIN GESTION PROMO ===

        // === PRÉPARATION DES DONNÉES POUR LE PAIEMENT ===
        // Créer un tableau simplifié des articles (uniquement type, id, quantité)
        // pour stocker en session (plus léger que les entités complètes)
        $itemRefs = array_map(fn ($row) => [
            'type'     => $row['type'],
            'id'       => $row['product']->getId(),
            'quantity' => (int) $row['quantity'],
        ], $cartWithData);

        // Stocker les informations nécessaires pour le processus de paiement
        $session->set('cart_item_refs', $itemRefs);                          // Références des articles
        $session->set('cart_subtotal', $subtotal);                           // Sous-total
        $session->set('cart_discount', $discount);                           // Montant de la réduction
        $session->set('cart_promo_code_id', $promoCodeEntity?->getId());     // ID du code promo (null si aucun)

        // === PRÉ-REMPLISSAGE DU FORMULAIRE ===
        // Créer une nouvelle entité Order vide
        $order = new Order();
        
        // Récupérer l'utilisateur connecté (peut être null si non connecté)
        /** @var User|null $user */
        $user = $this->getUser();
        
        // Si un utilisateur est connecté, pré-remplir le formulaire avec ses données
        if ($user !== null) {
            // Pré-remplir les informations personnelles
            if ($user->getFirstName()) { $order->setFirstName($user->getFirstName()); }
            if ($user->getLastName())  { $order->setLastName($user->getLastName()); }
            if ($user->getEmail())     { $order->setEmail($user->getEmail()); }
            if ($user->getPhone())     { $order->setPhone($user->getPhone()); }

            // Pré-remplir l'adresse de livraison avec la première adresse enregistrée
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

        // === GESTION DU FORMULAIRE ===
        // Créer le formulaire avec l'entité Order pré-remplie
        $form = $this->createForm(OrderForm::class, $order);
        
        // Traiter la soumission du formulaire
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les données du formulaire
            /** @var Order $orderData */
            $orderData = $form->getData();

            // Créer un tableau avec toutes les données nettoyées (trim des espaces)
            $payload = [
                'firstName'  => trim((string) $orderData->getFirstName()),
                'lastName'   => trim((string) $orderData->getLastName()),
                'email'      => trim((string) $orderData->getEmail()),
                'phone'      => trim((string) $orderData->getPhone()),
                'street'     => trim((string) $orderData->getStreet()),
                'city'       => trim((string) $orderData->getCity()),
                'postalCode' => trim((string) $orderData->getPostalCode()),
            ];

            // Liste des champs obligatoires
            $required = ['firstName','lastName','email','phone','street','city','postalCode'];
            
            // Vérifier qu'aucun champ obligatoire n'est vide
            $missing  = array_filter($required, fn($f) => $payload[$f] === '');
            
            // Si des champs sont manquants
            if ($missing) {
                flash()->error('Veuillez compléter tous les champs requis.');
                return $this->redirectToRoute('app_order');
            }

            // Récupérer la case à cocher "Enregistrer mes informations"
            $saveInfo = $request->request->get('save-info');
            
            // Convertir en booléen (vérifie plusieurs formats possibles)
            $saveInfoBool = $saveInfo === '1' || $saveInfo === 'on' || $saveInfo === true;
            
            // Stocker les informations en session pour la page de paiement
            $session->set('save_user_info', $saveInfoBool);     // Enregistrer ou non les infos
            $session->set('order_data', $payload);              // Données de la commande
            
            // Rediriger vers la page de paiement
            return $this->redirectToRoute('app_checkout');
        }

        // Rendu de la page de commande avec toutes les données
        return $this->render('order/index.html.twig', [
            'form'  => $form->createView(),                  // Vue du formulaire
            'items' => $cartWithData,                        // Articles du panier avec données
            'subtotal' => $subtotal,                         // Sous-total
            'discount' => $discount,                         // Montant de la réduction
            'subtotalAfterDiscount' => $subtotalAfterDiscount, // Sous-total après réduction
            'finalTotal' => $finalTotal,                     // Total final
            'appliedPromo' => $appliedPromo,                 // Code promo appliqué
        ]);
    }

    /**
     * Traitement du paiement
     * Point d'entrée pour tous les types de paiement (PayPal, carte)
     * Route appelée en AJAX depuis le frontend
     * 
     * @param Request $request Requête HTTP contenant les données de paiement
     * @param SessionInterface $session Service de session
     * @return JsonResponse Réponse JSON avec le résultat du paiement
     */
    #[Route('/process-payment', name: 'app_process_payment', methods: ['POST'])]
    public function processPayment(Request $request, SessionInterface $session): JsonResponse
    {
        try {
            // Récupérer la méthode de paiement choisie
            $paymentMethod = $request->get('payment_method');
            
            // Utiliser match pour rediriger vers la bonne méthode de paiement
            // match est similaire à switch mais retourne une valeur
            return match ($paymentMethod) {
                'paypal' => $this->processPaypalPayment($request, $session),
                'card'   => $this->processCardPayment($request, $session),
                default  => new JsonResponse([
                    'success' => false, 
                    'error' => 'Méthode de paiement non supportée'
                ]),
            };
        } catch (\Throwable $e) {
            // En cas d'erreur, retourner une réponse JSON avec l'erreur
            return new JsonResponse([
                'success' => false,
                'error'   => 'Erreur lors du traitement: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Traitement spécifique du paiement PayPal
     * Méthode privée appelée par processPayment()
     * 
     * @param Request $request Requête contenant l'ID de transaction PayPal
     * @param SessionInterface $session Service de session
     * @return JsonResponse Résultat du traitement
     */
    private function processPaypalPayment(Request $request, SessionInterface $session): JsonResponse
    {
        // Récupérer l'ID de la transaction PayPal
        $paypalOrderId = $request->get('paypal_order_id');
        
        // Vérifier que l'ID PayPal est présent
        if (!$paypalOrderId) {
            return new JsonResponse([
                'success' => false, 
                'error' => 'Order ID manquant'
            ]);
        }

        // Récupérer les données stockées en session
        $itemRefs = $session->get('cart_item_refs', []);      // Références des articles
        $orderData = $session->get('order_data', []);         // Données client
        $discount = $session->get('cart_discount', 0);        // Réduction
        $promoCodeId = $session->get('cart_promo_code_id');  // ID code promo

        // Vérifier que les données essentielles sont présentes
        if (empty($itemRefs) || empty($orderData)) {
            return new JsonResponse([
                'success' => false, 
                'error' => 'Données de commande manquantes'
            ]);
        }

        // === RECONSTRUCTION DU PANIER ===
        $lines = [];              // Lignes de commande détaillées
        $recalcSubtotal = 0.0;    // Recalcul du sous-total (sécurité)

        // Parcourir chaque article du panier
        foreach ($itemRefs as $ref) {
            // Récupérer la quantité
            $qty = (float) ($ref['quantity'] ?? 0);
            if ($qty <= 0) continue;  // Ignorer si quantité invalide

            // Récupérer l'entité selon le type
            $entity = $ref['type'] === 'product'
                ? $this->productRepository->find((int)$ref['id'])
                : $this->deviceRepository->find((int)$ref['id']);
            
            // Ignorer si l'entité n'existe plus
            if (!$entity) continue;

            // Récupérer le prix actuel (peut avoir changé depuis l'ajout au panier)
            $price = (float) $entity->getPrice();
            
            // Ajouter au sous-total
            $recalcSubtotal += $price * $qty;

            // Ajouter la ligne de commande
            $lines[] = [
                'type'     => $ref['type'],
                'entity'   => $entity,
                'quantity' => $qty,
                'price'    => $price,
            ];
        }

        // === RECALCUL DES TOTAUX ===
        $subtotalAfterDiscount = $recalcSubtotal - $discount;
        $shipping = $subtotalAfterDiscount >= 49 ? 0.0 : 5.99;
        $finalTotal = round($subtotalAfterDiscount + $shipping, 2);  // Arrondir à 2 décimales

        try {
            // === CRÉATION DE LA COMMANDE ===
            $order = new Order();
            
            // Associer l'utilisateur si connecté
            if ($this->getUser()) {
                $order->setUser($this->getUser());
            }

            // Définir toutes les informations de livraison
            $order->setFirstName((string) $orderData['firstName']);
            $order->setLastName((string) $orderData['lastName']);
            $order->setEmail((string) $orderData['email']);
            $order->setPhone((string) $orderData['phone']);
            $order->setStreet((string) $orderData['street']);
            $order->setCity((string) $orderData['city']);
            $order->setPostalCode((string) $orderData['postalCode']);

            // === ENREGISTREMENT DU CODE PROMO ===
            $promoCodeEntity = null;
            if ($promoCodeId) {
                // Récupérer l'entité du code promo
                $promoCodeEntity = $this->promoCodeRepository->find($promoCodeId);
                if ($promoCodeEntity) {
                    // Associer le code promo à la commande
                    $order->setPromoCode($promoCodeEntity);
                    $order->setDiscountAmount($discount);  // Enregistrer le montant de réduction
                }
            }

            // Définir les informations de paiement
            $order->setTotalPrice($finalTotal);           // Prix total
            $order->setPaymentMethod('paypal');           // Méthode: PayPal
            $order->setPaymentId($paypalOrderId);         // ID de transaction PayPal
            $order->setStatus('paid');                    // Statut: payé
            $order->setIsCompleted(false);                // Pas encore livré

            // Sauvegarder la commande en base de données
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // === ENREGISTREMENT DE L'UTILISATION DU CODE PROMO ===
            // Créer un enregistrement dans PromoCodeUsage pour tracker l'utilisation
            if ($promoCodeEntity && $discount > 0) {
                $promoUsage = new \App\Entity\PromoCodeUsage();
                $promoUsage->setPromoCode($promoCodeEntity);           // Code utilisé
                $promoUsage->setUser($this->getUser());                // Utilisateur
                $promoUsage->setOrderRef($order);                      // Commande associée
                $promoUsage->setDiscountApplied($discount);            // Montant de réduction
                
                $this->entityManager->persist($promoUsage);
                $this->entityManager->flush();
            }

            // === CRÉATION DES LIGNES DE COMMANDE ===
            foreach ($lines as $line) {
                $op = new OrderProducts();
                $op->setOrder($order);                                 // Associer à la commande
                $op->setQte($line['quantity']);                        // Quantité
                $op->setPrice($line['price']);                         // Prix unitaire
                $op->setTotalPrice($line['price'] * $line['quantity']); // Prix total ligne

                // Associer le produit OU l'appareil (un seul des deux)
                if ($line['type'] === 'product') {
                    $op->setProduct($line['entity']);
                } else {
                    $op->setDevice($line['entity']);
                }
                
                $this->entityManager->persist($op);
            }
            $this->entityManager->flush();

            // === SAUVEGARDE DES INFORMATIONS UTILISATEUR ===
            // Si l'utilisateur est connecté et a coché "Enregistrer mes informations"
            if ($this->getUser() && $session->get('save_user_info', false)) {
                $this->saveUserInformation($this->getUser(), $orderData);
            }

            // === ENVOI DE L'EMAIL DE CONFIRMATION ===
            $this->sendOrderConfirmationEmail($order, $recalcSubtotal, $discount, $shipping);

            // === NETTOYAGE DE LA SESSION ===
            // Supprimer toutes les données de panier et commande
            foreach (['cart','order_data','cart_subtotal','cart_items','cart_item_refs','save_user_info','promo_code','cart_discount','cart_promo_code_id'] as $k) {
                $session->remove($k);
            }

            // Retourner le succès avec URL de redirection
            return new JsonResponse([
                'success'      => true,
                'redirect_url' => $this->generateUrl('app_order_confirmation', ['id' => $order->getId()])
            ]);
            
        } catch (\Throwable $e) {
            // En cas d'erreur, retourner l'erreur en JSON
            return new JsonResponse([
                'success' => false,
                'error'   => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Traitement spécifique du paiement par carte bancaire
     * Logique identique à PayPal mais avec un ID de transaction généré
     * 
     * @param Request $request Requête HTTP
     * @param SessionInterface $session Service de session
     * @return JsonResponse Résultat du traitement
     */
    private function processCardPayment(Request $request, SessionInterface $session): JsonResponse
    {
        // Récupération des données de session (identique à PayPal)
        $itemRefs = $session->get('cart_item_refs', []);
        $orderData = $session->get('order_data', []);
        $discount = $session->get('cart_discount', 0);
        $promoCodeId = $session->get('cart_promo_code_id');

        // Validation des données
        if (empty($itemRefs) || empty($orderData)) {
            return new JsonResponse([
                'success' => false, 
                'error' => 'Données de commande manquantes'
            ]);
        }

        // Reconstruction du panier (même logique que PayPal)
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

        // Calculs des totaux
        $subtotalAfterDiscount = $recalcSubtotal - $discount;
        $shipping = $subtotalAfterDiscount >= 49 ? 0.0 : 5.99;
        $finalTotal = round($subtotalAfterDiscount + $shipping, 2);

        try {
            // Création de la commande
            $order = new Order();
            if ($this->getUser()) {
                $order->setUser($this->getUser());
            }

            // Informations de livraison
            $order->setFirstName((string) $orderData['firstName']);
            $order->setLastName((string) $orderData['lastName']);
            $order->setEmail((string) $orderData['email']);
            $order->setPhone((string) $orderData['phone']);
            $order->setStreet((string) $orderData['street']);
            $order->setCity((string) $orderData['city']);
            $order->setPostalCode((string) $orderData['postalCode']);

            // Enregistrement du code promo
            $promoCodeEntity = null;
            if ($promoCodeId) {
                $promoCodeEntity = $this->promoCodeRepository->find($promoCodeId);
                if ($promoCodeEntity) {
                    $order->setPromoCode($promoCodeEntity);
                    $order->setDiscountAmount($discount);
                }
            }

            // Informations de paiement spécifiques à la carte
            $order->setTotalPrice($finalTotal);
            $order->setPaymentMethod('card');                 // Méthode: carte bancaire
            $order->setPaymentId('CARD_' . uniqid());         // ID unique généré (simulation)
            $order->setStatus('paid');
            $order->setIsCompleted(false);

            // Sauvegarde de la commande
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // Enregistrement de l'utilisation du code promo
            if ($promoCodeEntity && $discount > 0) {
                $promoUsage = new \App\Entity\PromoCodeUsage();
                $promoUsage->setPromoCode($promoCodeEntity);
                $promoUsage->setUser($this->getUser());
                $promoUsage->setOrderRef($order);
                $promoUsage->setDiscountApplied($discount);
                
                $this->entityManager->persist($promoUsage);
                $this->entityManager->flush();
            }

            // Création des lignes de commande
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

            // Sauvegarde des informations utilisateur si demandé
            if ($this->getUser() && $session->get('save_user_info', false)) {
                $this->saveUserInformation($this->getUser(), $orderData);
            }

            // Envoi de l'email de confirmation
            $this->sendOrderConfirmationEmail($order, $recalcSubtotal, $discount, $shipping);

            // Nettoyage de la session
            foreach (['cart','order_data','cart_subtotal','cart_items','cart_item_refs','save_user_info','promo_code','cart_discount','cart_promo_code_id'] as $k) {
                $session->remove($k);
            }

            // Retour du succès
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

    /**
     * Envoi de l'email de confirmation de commande
     * Méthode privée appelée après validation du paiement
     * 
     * @param Order $order Commande créée
     * @param float $subtotal Sous-total avant réduction
     * @param float $discount Montant de la réduction
     * @param float $shipping Frais de port
     * @return void
     */
    private function sendOrderConfirmationEmail(Order $order, float $subtotal, float $discount, float $shipping): void
    {
        try {
            // Rafraîchir l'entité depuis la base de données
            // pour s'assurer d'avoir toutes les relations chargées
            $this->entityManager->refresh($order);
            
            // Tableau pour stocker les détails de chaque article commandé
            $orderItems = [];
            
            // Parcourir toutes les lignes de commande
            foreach ($order->getOrderProducts() as $orderProduct) {
                $item = null;  // L'entité Product ou Device
                $type = '';    // Type d'article
                
                // Déterminer s'il s'agit d'un produit ou d'un appareil
                if ($product = $orderProduct->getProduct()) {
                    $item = $product;
                    $type = 'product';
                } elseif ($device = $orderProduct->getDevice()) {
                    $item = $device;
                    $type = 'device';
                }

                // Si un article a été trouvé
                if ($item) {
                    // Ajouter les informations de l'article au tableau
                    $orderItems[] = [
                        'name' => $item->getTitle(),                    // Nom de l'article
                        'description' => $item->getDescription(),       // Description
                        'quantity' => $orderProduct->getQte(),          // Quantité commandée
                        'price' => $orderProduct->getPrice(),           // Prix unitaire
                        'total' => $orderProduct->getTotalPrice(),      // Prix total ligne
                        'image' => $this->findMainImage(                // Image principale
                            $type === 'product' ? 'products' : 'devices', 
                            $item->getId()
                        ),
                        'type' => $type                                 // Type: product/device
                    ];
                }
            }

            // Création de l'email avec template Twig
            $email = (new TemplatedEmail())
                ->from('noreply@glowbeautyskin.com')                           // Expéditeur
                ->to($order->getEmail())                                       // Destinataire
                ->subject('Confirmation de votre commande #' . $order->getOrderNumber()) // Sujet
                ->htmlTemplate('email/order_confirmation.html.twig')           // Template Twig
                ->context([                                                    // Variables du template
                    'order' => $order,                          // Commande complète
                    'orderItems' => $orderItems,                // Articles détaillés
                    'subtotal' => $subtotal,                    // Sous-total
                    'discount' => $discount,                    // Réduction appliquée
                    'shipping' => $shipping,                    // Frais de port
                    'total' => $order->getTotalPrice(),         // Total final
                    'user' => $order->getUser() ?: (object)['lastName' => $order->getLastName()], // Utilisateur ou objet générique
                    'promoCode' => $order->getPromoCode()       // Code promo utilisé
                ]);

            // Envoi de l'email
            $this->mailer->send($email);
            
        } catch (\Exception $e) {
            // En cas d'erreur d'envoi, logger l'erreur
            // L'erreur n'est pas remontée pour ne pas bloquer la commande
            error_log('Erreur email: ' . $e->getMessage());
        }
    }

    /**
     * Page de confirmation de commande
     * Affichée après un paiement réussi
     * Accessible uniquement par le client ou un admin
     * 
     * @param Order $order Commande (injection automatique via l'ID de route)
     * @return Response Rendu de la page de confirmation
     */
    #[Route('/confirmation/{id}', name: 'app_order_confirmation')]
    public function confirmation(Order $order): Response
    {
        // Contrôle d'accès: vérifier que l'utilisateur peut voir cette commande
        // Un utilisateur connecté non-admin ne peut voir que ses propres commandes
        if ($this->getUser() && !$this->isGranted('ROLE_ADMIN') && $order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Rendu de la page de confirmation
        return $this->render('order/confirmation.html.twig', [
            'order' => $order
        ]);
    }

    /**
     * Liste de toutes les commandes (Interface admin)
     * Accessible uniquement aux administrateurs
     * Affiche toutes les commandes avec pagination
     * 
     * @param OrderRepository $orderRepository Repository des commandes
     * @param Request $request Requête HTTP
     * @param PaginatorInterface $paginator Service de pagination
     * @return Response Rendu de la page de liste des commandes
     */
    #[Route('/admin/orders', name: 'app_admin_orders')]
    #[IsGranted('ROLE_ADMIN')]  // Seuls les admins peuvent accéder
    public function getAllOrders(OrderRepository $orderRepository, Request $request, PaginatorInterface $paginator): Response
    {   
        // Récupérer toutes les commandes, triées par date décroissante
        $data = $orderRepository->findBy([], ['orderDate' => 'DESC']);
        
        // Paginer les résultats (10 commandes par page)
        $order= $paginator->paginate(
            $data, 
            $request->query->getInt('page', 1),  // Numéro de page
            10                                    // Nombre d'éléments par page
        );

        // Rendu de la page de liste
        return $this->render('order/orders.html.twig', [
            'orders' => $order  // Objet paginé
        ]);
    }

    /**
     * Détails d'une commande (Interface admin)
     * Affiche tous les détails d'une commande spécifique
     * Accessible aux admins et au client propriétaire de la commande
     * 
     * @param Order $order Commande à afficher
     * @return Response Rendu de la page de détails
     */
    #[Route('/admin/orders/{id}', name: 'app_admin_order_details')]
    #[IsGranted('ROLE_ADMIN')]  // Accessible aux admins
    public function orderDetails(Order $order): Response
    {
        // Double vérification de sécurité
        // Si l'utilisateur n'est pas admin et que ce n'est pas sa commande
        if ($this->getUser() && $order->getUser() !== $this->getUser()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException();
            }
        }

        // Tableaux pour séparer produits et appareils
        $productsData = [];
        $devicesData  = [];
        
        // Sous-totaux séparés
        $subtotalProducts = 0.0;
        $subtotalDevices  = 0.0;

        // Parcourir toutes les lignes de commande
        foreach ($order->getOrderProducts() as $op) {
            // Si c'est un produit
            if ($product = $op->getProduct()) {
                $productsData[] = [
                    'title'       => $product->getTitle(),           // Nom
                    'description' => $product->getDescription(),     // Description
                    'quantity'    => $op->getQte(),                  // Quantité
                    'price'       => $op->getPrice(),                // Prix unitaire
                    'total'       => $op->getTotalPrice(),           // Total ligne
                    'image'       => $this->findMainImage('products', $product->getId()), // Image
                ];
                $subtotalProducts += (float) $op->getTotalPrice();
                
            // Si c'est un appareil
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

        // Rendu de la page de détails
        return $this->render('order/order_details.html.twig', [
            'order'            => $order,              // Commande complète
            'productsData'     => $productsData,       // Produits commandés
            'devicesData'      => $devicesData,        // Appareils commandés
            'subtotalProducts' => $subtotalProducts,   // Sous-total produits
            'subtotalDevices'  => $subtotalDevices,    // Sous-total appareils
        ]);
    }

    /**
     * Méthode utilitaire pour trouver l'image principale d'un produit/appareil
     * Recherche l'image numéro 1 dans le dossier d'uploads
     * 
     * @param string $folder Nom du dossier ('products' ou 'devices')
     * @param int $id ID de l'entité
     * @return string|null Chemin de l'image ou null si non trouvée
     */
    private function findMainImage(string $folder, int $id): ?string
    {
        // Pattern de recherche: /uploads/{folder}/*-{id}-1-*.*
        $pattern = $_SERVER['DOCUMENT_ROOT'] . "/uploads/{$folder}/*-{$id}-1-*.*";
        
        // Rechercher les fichiers correspondants
        $files = glob($pattern);
        
        // Retourner le chemin de la première image trouvée, ou null
        return !empty($files) ? "/uploads/{$folder}/" . basename($files[0]) : null;
    }

    /**
     * Mise à jour du statut d'une commande (Admin)
     * Permet de changer le statut entre 'paid' et 'cancelled'
     * 
     * @param Order $order Commande à modifier
     * @param Request $request Requête contenant le nouveau statut
     * @return Response Redirection vers la liste des commandes
     */
    #[Route('/admin/orders/{id}/status', name: 'app_admin_order_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateOrderStatus(Order $order, Request $request): Response 
    {
        // Récupérer le nouveau statut depuis le formulaire
        $newStatus = $request->get('status');
        
        // Vérifier que le statut est valide
        if (!in_array($newStatus, ['paid', 'cancelled'])) {
            flash()->error('Statut invalide.');
            return $this->redirectToRoute('app_admin_orders');
        }

        // Mettre à jour le statut
        $order->setStatus($newStatus);
        
        // Si le statut passe à "paid", marquer comme non livré
        if ($newStatus === 'paid') $order->setIsCompleted(false);
        
        // Sauvegarder les modifications
        $this->entityManager->flush();

        // Message de confirmation
        flash()->success('Statut mis à jour avec succès.');
        
        return $this->redirectToRoute('app_admin_orders');
    }

    /**
     * Marquer une commande comme livrée (Admin)
     * Change le statut isCompleted à true
     * Uniquement pour les commandes déjà payées
     * 
     * @param Order $order Commande à marquer comme livrée
     * @param EntityManagerInterface $em Gestionnaire d'entités
     * @return Response Redirection vers la liste des commandes
     */
    #[Route('/admin/orders/{id}/delivered/update', name: 'app_admin_order_delivered_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markAsDelivered(Order $order, EntityManagerInterface $em): Response
    {
        // Vérifier que la commande est bien payée
        // On ne peut livrer qu'une commande payée
        if ($order->getStatus() !== 'paid') {
            flash()->error('Seules les commandes payées peuvent être livrées.');
            return $this->redirectToRoute('app_admin_orders');
        }

        // Marquer la commande comme complète/livrée
        $order->setIsCompleted(true);
        
        // Sauvegarder
        $em->flush();

        // Message de confirmation
        flash()->success('Commande marquée comme livrée.');
        
        return $this->redirectToRoute('app_admin_orders');
    }

    /**
     * Suppression définitive d'une commande (Admin)
     * Supprime la commande et toutes ses lignes associées
     * 
     * @param Order $order Commande à supprimer
     * @return Response Redirection vers la liste des commandes
     */
    #[Route('/admin/orders/{id}/delete', name: 'app_admin_order_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteOrder(Order $order): Response
    {
        // Suppression de la commande
        // Les lignes de commande (OrderProducts) seront supprimées automatiquement
        // grâce à la configuration cascade dans l'entité
        $this->entityManager->remove($order);
        $this->entityManager->flush();

        // Message de confirmation
        flash()->success('Commande supprimée avec succès.');
        
        return $this->redirectToRoute('app_admin_orders');
    }

    /**
     * Sauvegarde des informations utilisateur pour les futures commandes
     * Met à jour le numéro de téléphone et ajoute une nouvelle adresse si nécessaire
     * Méthode privée appelée après validation du paiement
     * 
     * @param User $user Utilisateur à mettre à jour
     * @param array $orderData Données de la commande (adresse, téléphone)
     * @return void
     */
    private function saveUserInformation(User $user, array $orderData): void
    {
        try {
            // === MISE À JOUR DU TÉLÉPHONE ===
            $currentPhone = $user->getPhone();         // Téléphone actuel
            $newPhone = $orderData['phone'];           // Nouveau téléphone
            
            // Si le téléphone est vide ou différent, le mettre à jour
            if (empty($currentPhone) || $currentPhone !== $newPhone) {
                $user->setPhone($newPhone);
            }

            // === GESTION DE L'ADRESSE ===
            $addresses = $user->getAddresses();        // Collection des adresses de l'utilisateur
            $addressExists = false;                    // Flag pour savoir si l'adresse existe déjà
            
            // Parcourir toutes les adresses existantes
            foreach ($addresses as $address) {
                // Vérifier si cette adresse existe déjà (comparaison stricte)
                if ($address->getStreet() === $orderData['street'] && 
                    $address->getCity() === $orderData['city'] && 
                    $address->getPostalCode() === $orderData['postalCode']) {
                    $addressExists = true;
                    break;  // Arrêter la recherche
                }
            }

            // Si l'adresse n'existe pas encore, l'ajouter
            if (!$addressExists) {
                // Créer une nouvelle entité Address
                $newAddress = new \App\Entity\Address();
                $newAddress->setStreet($orderData['street']);
                $newAddress->setCity($orderData['city']);
                $newAddress->setPostalCode($orderData['postalCode']);
                $newAddress->setUser($user);  // Associer à l'utilisateur
                
                // Persister la nouvelle adresse
                $this->entityManager->persist($newAddress);
                
                // Ajouter l'adresse à la collection de l'utilisateur
                $user->addAddress($newAddress);
            }

            // Persister l'utilisateur (mise à jour du téléphone)
            $this->entityManager->persist($user);
            
            // Sauvegarder toutes les modifications en base de données
            $this->entityManager->flush();
            
        } catch (\Exception $e) {
            // En cas d'erreur, logger mais ne pas bloquer le processus
            // L'échec de sauvegarde des infos ne doit pas empêcher la commande
            error_log('Erreur sauvegarde: ' . $e->getMessage());
        }
    }
}