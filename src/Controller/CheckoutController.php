<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/checkout')] // Préfixe de route : toutes les routes commencent par "/checkout"
class CheckoutController extends AbstractController // Classe non finale (peut être héritée)
{
    public function __construct( // Constructeur avec injection de dépendances
        private readonly ProductRepository $productRepository, // Property promotion : crée automatiquement une propriété privée non modifiable
        private readonly DeviceRepository $deviceRepository // Repository pour les appareils
    ) {} // Constructeur vide car tout est géré par la property promotion

    #[Route(name: 'app_checkout')] // Route sans méthode spécifiée = accepte GET et POST par défaut
    public function index(Request $request, SessionInterface $session): Response // Méthode pour afficher la page de paiement
    {
        $cart = $session->get('cart', []); // Récupère le panier depuis la session
        $cartWithData = []; // Initialise un tableau vide pour les données enrichies

        foreach ($cart as $itemKey => $quantity) { // Parcourt chaque article du panier
            $parts = explode('_', (string) $itemKey); // Découpe la clé en tableau, avec cast explicite en string
            if (count($parts) !== 2) continue; // Ignore si le format n'est pas valide

            [$type, $id] = $parts; // Destructuration : sépare en type et id
            if (!in_array($type, ['product', 'device'], true)) continue; // Vérifie le type avec comparaison stricte (===)

            $id = (int) $id; // Convertit l'id en entier
            $item = $type === 'product' // Opérateur ternaire
                ? $this->productRepository->find($id) // Cherche dans ProductRepository si type = product
                : $this->deviceRepository->find($id); // Sinon cherche dans DeviceRepository

            if ($item) { // Vérifie que l'article existe
                $cartWithData[] = [ // Ajoute au tableau des données enrichies
                    'product'  => $item, // L'objet entité
                    'quantity' => (int) $quantity, // Quantité convertie en entier
                    'type'     => $type, // Type de l'article
                ];
            }
        }

        if (empty($cartWithData)) { // Vérifie si le panier est vide
            $this->addFlash('error', 'Votre panier est vide.'); // Message flash d'erreur
            return $this->redirectToRoute('app_order'); // Redirige vers la route app_order
        }

        $subtotal = array_sum(array_map( // Calcule le sous-total
            fn ($row) => $row['product']->getPrice() * $row['quantity'], // Arrow function : multiplie prix par quantité
            $cartWithData // Applique la fonction sur chaque élément du tableau
        )); // array_sum additionne tous les résultats
        $shippingCost = $subtotal > 49 ? 0.0 : 5.99; // Frais de port gratuits si > 49€, sinon 5,99€
        $finalTotal   = $subtotal + $shippingCost; // Calcule le total final

        $itemRefs = array_map(fn ($row) => [ // Transforme chaque article en référence simplifiée
            'type'     => $row['type'], // Type de l'article
            'id'       => $row['product']->getId(), // Récupère l'ID via la méthode getId()
            'quantity' => (int) $row['quantity'], // Quantité en entier
        ], $cartWithData); // Applique la transformation sur tout le panier

        $session->set('cart_item_refs', $itemRefs); // Sauvegarde les références en session
        $session->set('cart_subtotal', $subtotal); // Sauvegarde le sous-total en session

        $orderData = $session->get('order_data', []); // Récupère les données de commande depuis la session
        if (empty($orderData)) { // Si aucune donnée de commande
            $this->addFlash('error', 'Veuillez d\'abord remplir vos informations de livraison.'); // Message d'erreur
            return $this->redirectToRoute('app_order'); // Redirige vers la page de commande
        }

        if ($request->isMethod('POST')) { // Vérifie si la requête est de type POST
            $paymentMethod = $request->request->get('payment_method'); // Récupère le champ payment_method depuis POST
            if ($paymentMethod === 'card') { // Si paiement par carte
                $this->addFlash('success', 'Paiement par carte traité avec succès !'); // Message de succès
            } elseif ($paymentMethod === 'paypal') { // Sinon si paiement PayPal
                $this->addFlash('info', 'Redirection vers PayPal...'); // Message d'information
            }
        }

        return $this->render('checkout/index.html.twig', [ // Affiche le template
            'items'        => $cartWithData, // Passe les articles au template
            'total'        => $subtotal, // Passe le sous-total
            'orderData'    => $orderData, // Passe les données de commande
            'shippingCost' => $shippingCost, // Passe les frais de port
            'finalTotal'   => $finalTotal, // Passe le total final
        ]);
    }
}