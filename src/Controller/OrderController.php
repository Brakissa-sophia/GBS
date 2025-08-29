<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\OrderForm;
use App\Repository\ProductRepository;
use App\Repository\DeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly DeviceRepository $deviceRepository
    ) {}

    #[Route(name: 'app_order')]
    public function index(Request $request, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $cartWithData = [];

        foreach ($cart as $itemKey => $quantity) {
            $parts = explode('_', (string) $itemKey);
            if (count($parts) !== 2) {
                continue;
            }

            [$type, $id] = $parts;
            if (!in_array($type, ['product', 'device'], true)) {
                continue;
            }

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

        $total = array_sum(array_map(
            fn ($row) => $row['product']->getPrice() * $row['quantity'],
            $cartWithData
        ));

        // Créer la commande et pré-remplir avec les données utilisateur si connecté
        $order = new Order();

        /** @var User|null $user */
        $user = $this->getUser();

        if ($user !== null) {
            // Pré-remplir les informations utilisateur (casse corrigée)
            $order->setFirstName($user->getFirstName());
            $order->setLastName($user->getLastName());
            $order->setEmail($user->getEmail());
            $order->setPhone($user->getPhone());

            // Optionnel : pré-remplir avec la première adresse de l'utilisateur
            $addresses = $user->getAddresses();
            if (!$addresses->isEmpty()) {
                $firstAddress = $addresses->first(); // Address|false
                if ($firstAddress !== false) {
                    $order->setStreet($firstAddress->getStreet());
                    $order->setCity($firstAddress->getCity());
                    $order->setPostalCode($firstAddress->getPostalCode());
                }
            }
        }

        $form = $this->createForm(OrderForm::class, $order);
        $form->handleRequest($request);

        return $this->render('order/index.html.twig', [
            'form'  => $form->createView(),
            'items' => $cartWithData,
            'total' => $total,
        ]);
    }
}
