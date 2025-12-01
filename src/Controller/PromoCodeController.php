<?php

// Déclaration du namespace du contrôleur
namespace App\Controller;

// Importation de l'entité code promo
use App\Entity\PromoCode;

// Importation du formulaire
use App\Form\PromoCodeType;

// Importation des repositories
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use App\Repository\PromoCodeRepository;
use App\Repository\PromoCodeUsageRepository;

// Importation de Doctrine
use Doctrine\ORM\EntityManagerInterface;

// Importation des classes Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur de gestion des codes promotionnels
 * Gère deux aspects:
 * 1. API publique: validation et application des codes promo dans le panier
 * 2. Interface admin: CRUD complet des codes promo
 * 
 * Fonctionnalités:
 * - Validation des codes avec vérifications multiples (validité, éligibilité, utilisation)
 * - Calcul des réductions (pourcentage ou montant fixe)
 * - Gestion des restrictions (produits, catégories, marques, types de peau)
 * - Limitation d'utilisation par utilisateur
 * - Administration complète (création, modification, activation/désactivation, suppression)
 */
class PromoCodeController extends AbstractController
{
    /**
     * Constructeur avec injection de dépendances
     * Toutes les dépendances sont injectées comme propriétés readonly
     */
    public function __construct(
        private readonly PromoCodeRepository $promoCodeRepository,
        private readonly PromoCodeUsageRepository $promoCodeUsageRepository,
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    // ========== ROUTES API (pour les clients) ==========

    /**
     * Validation et application d'un code promo
     * Endpoint API appelé en AJAX depuis le panier
     * Effectue de multiples vérifications avant d'appliquer le code
     * 
     * Vérifications effectuées:
     * 1. Code non vide
     * 2. Panier non vide
     * 3. Code existe
     * 4. Code valide (dates, actif, utilisations max)
     * 5. Utilisateur n'a pas déjà utilisé ce code (si connecté)
     * 6. Au moins un article du panier est éligible
     */
    #[Route('/api/promo/validate', name: 'api_promo_validate', methods: ['POST'])]
    public function validatePromoCode(Request $request, SessionInterface $session): JsonResponse
    {
        // Récupérer et décoder les données JSON de la requête
        $data = json_decode($request->getContent(), true);
        
        // Extraire et nettoyer le code promo
        $code = trim($data['code'] ?? '');

        // === VÉRIFICATION 1: CODE NON VIDE ===
        if (empty($code)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez saisir un code promo.'
            ], 400);  // 400 = Bad Request
        }

        // Récupérer le panier depuis la session
        $cart = $session->get('cart', []);
        
        // === VÉRIFICATION 2: PANIER NON VIDE ===
        if (empty($cart)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Votre panier est vide.'
            ], 400);
        }

        // Rechercher le code promo dans la base de données
        $promoCode = $this->promoCodeRepository->findByCode($code);

        // === VÉRIFICATION 3: CODE EXISTE ===
        if (!$promoCode) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce code promo n\'existe pas.'
            ], 400);
        }

        // === VÉRIFICATION 4: CODE VALIDE ===
        // isValid() vérifie: isActive, dates de validité, nombre max d'utilisations
        if (!$promoCode->isValid()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce code promo n\'est plus valide ou a expiré.'
            ], 400);
        }

        // === VÉRIFICATION 5: UTILISATEUR N'A PAS DÉJÀ UTILISÉ CE CODE ===
        // Uniquement si l'utilisateur est connecté
        if ($this->getUser()) {
            // Vérifier dans la table PromoCodeUsage
            if ($this->promoCodeUsageRepository->hasUserUsedPromoCode($this->getUser(), $promoCode)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Vous avez déjà utilisé ce code promo.'
                ], 400);
            }
        }

        // === CALCUL DU MONTANT ÉLIGIBLE ===
        // Seuls les articles éligibles au code promo sont pris en compte
        $eligibleAmount = 0;
        
        // Parcourir chaque article du panier
        foreach ($cart as $itemKey => $quantity) {
            // Décoder la clé (format: type_id)
            $parts = explode('_', (string) $itemKey);
            if (count($parts) !== 2) continue;

            [$type, $id] = $parts;
            
            // Récupérer l'entité selon le type
            $item = $type === 'product' 
                ? $this->productRepository->find((int)$id)
                : $this->deviceRepository->find((int)$id);

            // Si l'article existe ET est éligible au code promo
            if ($item && $promoCode->isEligible($item)) {
                // Ajouter au montant éligible
                $eligibleAmount += $item->getPrice() * $quantity;
            }
        }

        // === VÉRIFICATION 6: AU MOINS UN ARTICLE ÉLIGIBLE ===
        if ($eligibleAmount <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce code promo n\'est applicable à aucun article de votre panier.'
            ], 400);
        }

        // Calculer le montant de la réduction
        $discount = $promoCode->calculateDiscount($eligibleAmount);

        // === CALCUL DU SOUS-TOTAL TOTAL ===
        // Calcul sur TOUS les articles (pas seulement les éligibles)
        $subtotal = 0;
        foreach ($cart as $itemKey => $quantity) {
            $parts = explode('_', (string) $itemKey);
            if (count($parts) !== 2) continue;

            [$type, $id] = $parts;
            $item = $type === 'product' 
                ? $this->productRepository->find((int)$id)
                : $this->deviceRepository->find((int)$id);

            if ($item) {
                $subtotal += $item->getPrice() * $quantity;
            }
        }

        // === CALCULS FINAUX ===
        $subtotalAfterDiscount = $subtotal - $discount;           // Sous-total après réduction
        $shipping = $subtotalAfterDiscount >= 49 ? 0 : 5.99;      // Frais de port (gratuits si > 49€)
        $total = $subtotalAfterDiscount + $shipping;              // Total final

        // === SAUVEGARDE EN SESSION ===
        // Stocker le code et la réduction pour utilisation lors du paiement
        $session->set('promo_code', $code);
        $session->set('promo_discount', $discount);

        // Retourner le succès avec tous les détails
        return new JsonResponse([
            'success' => true,
            'message' => sprintf('Code promo "%s" appliqué ! Réduction de %s', $code, $promoCode->getFormattedDiscount()),
            'discount' => $discount,                                             // Montant de la réduction (nombre)
            'discountFormatted' => number_format($discount, 2, ',', ' ') . '€',  // Montant formaté pour affichage
            'subtotal' => $subtotal,                                             // Sous-total
            'shipping' => $shipping,                                             // Frais de port
            'total' => $total,                                                   // Total final
            'promoCode' => $code                                                 // Code appliqué
        ]);
    }

    /**
     * Retrait d'un code promo appliqué
     * Endpoint API pour supprimer le code du panier
     * Recalcule les totaux sans la réduction
     */
    #[Route('/api/promo/remove', name: 'api_promo_remove', methods: ['POST'])]
    public function removePromoCode(SessionInterface $session): JsonResponse
    {
        // Supprimer le code promo et la réduction de la session
        $session->remove('promo_code');
        $session->remove('promo_discount');

        // Récupérer le panier
        $cart = $session->get('cart', []);
        $subtotal = 0;

        // Recalculer le sous-total sans réduction
        foreach ($cart as $itemKey => $quantity) {
            $parts = explode('_', (string) $itemKey);
            if (count($parts) !== 2) continue;

            [$type, $id] = $parts;
            $item = $type === 'product' 
                ? $this->productRepository->find((int)$id)
                : $this->deviceRepository->find((int)$id);

            if ($item) {
                $subtotal += $item->getPrice() * $quantity;
            }
        }

        // Recalculer les frais de port et le total
        $shipping = $subtotal >= 49 ? 0 : 5.99;
        $total = $subtotal + $shipping;

        // Retourner les nouveaux totaux
        return new JsonResponse([
            'success' => true,
            'message' => 'Code promo retiré.',
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total
        ]);
    }

    // ========== INTERFACE ADMIN ==========

    /**
     * Liste de tous les codes promo (Administration)
     * Affiche tous les codes triés par date de création décroissante
     */
    #[Route('/admin/promo-codes', name: 'admin_promo_codes_index')]
    #[IsGranted('ROLE_ADMIN')]  // Accessible uniquement aux administrateurs
    public function index(): Response
    {
        // Récupérer tous les codes promo, triés du plus récent au plus ancien
        $promoCodes = $this->promoCodeRepository->findBy([], ['createdAt' => 'DESC']);

        // Rendu de la page de liste
        return $this->render('promo_code/index.html.twig', [
            'promoCodes' => $promoCodes
        ]);
    }

    /**
     * Création d'un nouveau code promo (Administration)
     * Gère le formulaire de création avec validations
     */
    #[Route('/admin/promo-codes/add', name: 'admin_promo_codes_add')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        // Créer une nouvelle entité PromoCode vide
        $promoCode = new PromoCode();
        
        // Créer le formulaire
        $form = $this->createForm(PromoCodeType::class, $promoCode);
        
        // Traiter la soumission
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // === VÉRIFICATION: CODE UNIQUE ===
            // Vérifier qu'un code avec ce nom n'existe pas déjà
            $existing = $this->promoCodeRepository->findByCode($promoCode->getCode());
            if ($existing) {
                $this->addFlash('error', 'Ce code promo existe déjà.');
                return $this->redirectToRoute('admin_promo_codes_add');
            }

            // === VALIDATION: POURCENTAGE MAX 100% ===
            // Si c'est une réduction en pourcentage, vérifier qu'elle ne dépasse pas 100%
            if ($promoCode->getDiscountType() === 'percentage' && $promoCode->getDiscountValue() > 100) {
                $this->addFlash('error', 'Le pourcentage ne peut pas dépasser 100%.');
                return $this->redirectToRoute('admin_promo_codes_add');
            }

            // Sauvegarder le nouveau code promo
            $this->entityManager->persist($promoCode);
            $this->entityManager->flush();

            // Message de confirmation
            $this->addFlash('success', sprintf('Le code promo "%s" a été créé avec succès.', $promoCode->getCode()));
            
            // Redirection vers la liste
            return $this->redirectToRoute('admin_promo_codes_index');
        }

        // Affichage du formulaire de création
        return $this->render('promo_code/add.html.twig', [
            'form' => $form->createView(),
            'promoCode' => $promoCode,
            'isEdit' => false  // Indicateur pour le template (création vs édition)
        ]);
    }

    /**
     * Modification d'un code promo existant (Administration)
     * Permet de modifier tous les paramètres d'un code
     */
    #[Route('/admin/promo-codes/{id}/edit', name: 'admin_promo_codes_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, PromoCode $promoCode): Response
    {
        // Sauvegarder le code original pour détecter un changement
        $originalCode = $promoCode->getCode();
        
        // Créer le formulaire avec le code existant
        $form = $this->createForm(PromoCodeType::class, $promoCode);
        
        // Traiter la soumission
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // === VÉRIFICATION: SI LE CODE A CHANGÉ, VÉRIFIER L'UNICITÉ ===
            if ($originalCode !== $promoCode->getCode()) {
                // Rechercher si un autre code promo utilise ce nom
                $existing = $this->promoCodeRepository->findByCode($promoCode->getCode());
                
                // Si un code existe ET que ce n'est pas le code actuel
                if ($existing && $existing->getId() !== $promoCode->getId()) {
                    $this->addFlash('error', 'Ce code promo existe déjà.');
                    return $this->redirectToRoute('admin_promo_codes_edit', ['id' => $promoCode->getId()]);
                }
            }

            // === VALIDATION: POURCENTAGE MAX 100% ===
            if ($promoCode->getDiscountType() === 'percentage' && $promoCode->getDiscountValue() > 100) {
                $this->addFlash('error', 'Le pourcentage ne peut pas dépasser 100%.');
                return $this->redirectToRoute('admin_promo_codes_edit', ['id' => $promoCode->getId()]);
            }

            // Sauvegarder les modifications
            $this->entityManager->flush();

            // Message de confirmation
            $this->addFlash('success', sprintf('Le code promo "%s" a été modifié avec succès.', $promoCode->getCode()));
            
            // Redirection vers la liste
            return $this->redirectToRoute('admin_promo_codes_index');
        }

        // Affichage du formulaire de modification
        return $this->render('promo_code/edit.html.twig', [
            'form' => $form->createView(),
            'promoCode' => $promoCode,
            'isEdit' => true  // Indicateur pour le template
        ]);
    }

    /**
     * Activation/Désactivation d'un code promo (Administration)
     * Permet de désactiver temporairement un code sans le supprimer
     */
    #[Route('/admin/promo-codes/{id}/toggle', name: 'admin_promo_codes_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggle(PromoCode $promoCode): Response
    {
        // Inverser le statut actif/inactif
        $promoCode->setIsActive(!$promoCode->isActive());
        
        // Sauvegarder
        $this->entityManager->flush();

        // Message de confirmation adapté au nouveau statut
        $this->addFlash('success', sprintf(
            'Le code "%s" a été %s.',
            $promoCode->getCode(),
            $promoCode->isActive() ? 'activé' : 'désactivé'
        ));

        // Redirection vers la liste
        return $this->redirectToRoute('admin_promo_codes_index');
    }

    /**
     * Suppression définitive d'un code promo (Administration)
     * Supprime le code et toutes ses utilisations associées
     */
    #[Route('/admin/promo-codes/{id}/delete', name: 'admin_promo_codes_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(PromoCode $promoCode): Response
    {
        // Sauvegarder le code pour le message de confirmation
        $code = $promoCode->getCode();
        
        // Supprimer le code promo
        // Les utilisations (PromoCodeUsage) seront supprimées automatiquement
        // grâce à la configuration cascade dans l'entité
        $this->entityManager->remove($promoCode);
        $this->entityManager->flush();

        // Message de confirmation
        $this->addFlash('success', sprintf('Le code promo "%s" a été supprimé.', $code));

        // Redirection vers la liste
        return $this->redirectToRoute('admin_promo_codes_index');
    }
}