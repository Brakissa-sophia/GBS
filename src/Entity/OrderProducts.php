<?php

namespace App\Entity;

use App\Repository\OrderProductsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderProductsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderProducts
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orderProducts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $_order = null;

    // --- Product OU Device (exclusif) ---
    #[ORM\ManyToOne(inversedBy: 'orderProducts')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'orderProducts')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Device $device = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'La quantité doit être supérieure à 0.')]
    private ?float $qte = null;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le prix ne peut pas être négatif.')]
    private ?float $price = null;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le total ne peut pas être négatif.')]
    private ?float $totalPrice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->_order;
    }

    public function setOrder(?Order $_order): static
    {
        $this->_order = $_order;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        if ($product !== null) {
            // exclusivité
            $this->device = null;
        }
        return $this;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): static
    {
        $this->device = $device;
        if ($device !== null) {
            // exclusivité
            $this->product = null;
        }
        return $this;
    }

    public function getQte(): ?float
    {
        return $this->qte;
    }

    public function setQte(float $qte): static
    {
        // au moins 1
        $this->qte = $qte > 0 ? $qte : 1.0;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        // arrondi propre DB
        $this->price = round($price, 2);
        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): static
    {
        $this->totalPrice = round($totalPrice, 2);
        return $this;
    }

    // Helpers
    public function getItemName(): string
    {
        return $this->product?->getTitle() ?? $this->device?->getTitle() ?? '';
    }

    public function getItemType(): string
    {
        return $this->product ? 'product' : 'device';
    }

    // -------------------------------------------------
    // Vérifications automatiques avant INSERT/UPDATE
    // -------------------------------------------------
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function ensureValidity(): void
    {
        $hasProduct = $this->product !== null;
        $hasDevice  = $this->device  !== null;

        // XOR strict : exactement 1 des deux doit être renseigné
        if (!($hasProduct xor $hasDevice)) {
            throw new \LogicException('Chaque ligne doit contenir un produit OU un device, mais pas les deux.');
        }

        if ($this->qte === null || $this->qte <= 0) {
            throw new \LogicException('La quantité doit être supérieure à 0.');
        }

        if ($this->price === null || $this->price < 0) {
            throw new \LogicException('Le prix unitaire doit être >= 0.');
        }

        // Si le total n'a pas été posé, on le calcule
        if ($this->totalPrice === null) {
            $this->totalPrice = round($this->qte * $this->price, 2);
        } else {
            $this->totalPrice = round($this->totalPrice, 2);
        }
    }
}
