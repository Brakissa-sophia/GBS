<?php

namespace App\Controller;

use App\Entity\PromoCode;
use App\Form\PromoCodeType;
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PromoCodeController extends AbstractController
{
    public function __construct(
        private readonly PromoCodeRepository $promoCodeRepository,
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    // ========== ROUTES API (pour les clients) ==========

    #[Route('/api/promo/validate', name: 'api_promo_validate', methods: ['POST'])]
    public function validatePromoCode(Request $request, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $code = trim($data['code'] ?? '');

        if (empty($code)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez saisir un code promo.'
            ], 400);
        }

        $cart = $session->get('cart', []);
        
        if (empty($cart)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Votre panier est vide.'
            ], 400);
        }

        $promoCode = $this->promoCodeRepository->findByCode($code);

        if (!$promoCode) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce code promo n\'existe pas.'
            ], 400);
        }

        if (!$promoCode->isValid()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce code promo n\'est plus valide ou a expiré.'
            ], 400);
        }

        // Calculer le montant éligible
        $eligibleAmount = 0;
        foreach ($cart as $itemKey => $quantity) {
            $parts = explode('_', (string) $itemKey);
            if (count($parts) !== 2) continue;

            [$type, $id] = $parts;
            $item = $type === 'product' 
                ? $this->productRepository->find((int)$id)
                : $this->deviceRepository->find((int)$id);

            if ($item && $promoCode->isEligible($item)) {
                $eligibleAmount += $item->getPrice() * $quantity;
            }
        }

        if ($eligibleAmount <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce code promo n\'est applicable à aucun article de votre panier.'
            ], 400);
        }

        $discount = $promoCode->calculateDiscount($eligibleAmount);

        // Calculer le sous-total total
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

        $subtotalAfterDiscount = $subtotal - $discount;
        $shipping = $subtotalAfterDiscount >= 49 ? 0 : 5.99;
        $total = $subtotalAfterDiscount + $shipping;

        $session->set('promo_code', $code);
        $session->set('promo_discount', $discount);

        return new JsonResponse([
            'success' => true,
            'message' => sprintf('Code promo "%s" appliqué ! Réduction de %s', $code, $promoCode->getFormattedDiscount()),
            'discount' => $discount,
            'discountFormatted' => number_format($discount, 2, ',', ' ') . '€',
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'promoCode' => $code
        ]);
    }

    #[Route('/api/promo/remove', name: 'api_promo_remove', methods: ['POST'])]
    public function removePromoCode(SessionInterface $session): JsonResponse
    {
        $session->remove('promo_code');
        $session->remove('promo_discount');

        $cart = $session->get('cart', []);
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

        $shipping = $subtotal >= 49 ? 0 : 5.99;
        $total = $subtotal + $shipping;

        return new JsonResponse([
            'success' => true,
            'message' => 'Code promo retiré.',
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total
        ]);
    }

    // ========== ROUTES ADMIN ==========

    #[Route('/admin/promo-codes', name: 'admin_promo_codes_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $promoCodes = $this->promoCodeRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('promo_code/index.html.twig', [
            'promoCodes' => $promoCodes
        ]);
    }

    #[Route('/admin/promo-codes/add', name: 'admin_promo_codes_add')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $promoCode = new PromoCode();
        $form = $this->createForm(PromoCodeType::class, $promoCode);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier l'unicité du code
            $existing = $this->promoCodeRepository->findByCode($promoCode->getCode());
            if ($existing) {
                $this->addFlash('error', 'Ce code promo existe déjà.');
                return $this->redirectToRoute('admin_promo_codes_add');
            }

            // Validation personnalisée
            if ($promoCode->getDiscountType() === 'percentage' && $promoCode->getDiscountValue() > 100) {
                $this->addFlash('error', 'Le pourcentage ne peut pas dépasser 100%.');
                return $this->redirectToRoute('admin_promo_codes_add');
            }

            $this->entityManager->persist($promoCode);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le code promo "%s" a été créé avec succès.', $promoCode->getCode()));
            return $this->redirectToRoute('admin_promo_codes_index');
        }

        return $this->render('promo_code/add.html.twig', [
            'form' => $form->createView(),
            'promoCode' => $promoCode,
            'isEdit' => false
        ]);
    }

    #[Route('/admin/promo-codes/{id}/edit', name: 'admin_promo_codes_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, PromoCode $promoCode): Response
    {
        $originalCode = $promoCode->getCode();
        $form = $this->createForm(PromoCodeType::class, $promoCode);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier l'unicité si le code a changé
            if ($originalCode !== $promoCode->getCode()) {
                $existing = $this->promoCodeRepository->findByCode($promoCode->getCode());
                if ($existing && $existing->getId() !== $promoCode->getId()) {
                    $this->addFlash('error', 'Ce code promo existe déjà.');
                    return $this->redirectToRoute('admin_promo_codes_edit', ['id' => $promoCode->getId()]);
                }
            }

            // Validation personnalisée
            if ($promoCode->getDiscountType() === 'percentage' && $promoCode->getDiscountValue() > 100) {
                $this->addFlash('error', 'Le pourcentage ne peut pas dépasser 100%.');
                return $this->redirectToRoute('admin_promo_codes_edit', ['id' => $promoCode->getId()]);
            }

            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le code promo "%s" a été modifié avec succès.', $promoCode->getCode()));
            return $this->redirectToRoute('admin_promo_codes_index');
        }

        return $this->render('promo_code/edit.html.twig', [
            'form' => $form->createView(),
            'promoCode' => $promoCode,
            'isEdit' => true
        ]);
    }

    #[Route('/admin/promo-codes/{id}/toggle', name: 'admin_promo_codes_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggle(PromoCode $promoCode): Response
    {
        $promoCode->setIsActive(!$promoCode->isActive());
        $this->entityManager->flush();

        $this->addFlash('success', sprintf(
            'Le code "%s" a été %s.',
            $promoCode->getCode(),
            $promoCode->isActive() ? 'activé' : 'désactivé'
        ));

        return $this->redirectToRoute('admin_promo_codes_index');
    }

    #[Route('/admin/promo-codes/{id}/delete', name: 'admin_promo_codes_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(PromoCode $promoCode): Response
    {
        $code = $promoCode->getCode();
        $this->entityManager->remove($promoCode);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Le code promo "%s" a été supprimé.', $code));

        return $this->redirectToRoute('admin_promo_codes_index');
    }
}