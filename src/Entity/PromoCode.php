<?php

namespace App\Entity;

use App\Repository\PromoCodeRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[UniqueEntity(fields: ['code'], message: 'Ce code promo existe déjà.')]
class PromoCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    // Type : 'percentage' ou 'fixed'
    #[ORM\Column(length: 20)]
    private string $discountType = 'percentage';

    // Valeur (20 pour 20% ou 10.00 pour 10€)
    #[ORM\Column]
    private ?float $discountValue = null;

    // Date de fin (optionnelle)
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    // Actif ou non
    #[ORM\Column]
    private bool $isActive = true;

    // Ciblage : NULL = tout le site
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    // Ciblage : NULL = toutes les marques
    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Brand $brand = null;

    // Historique des commandes
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'promoCode')]
    private Collection $orders;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // ========== NOUVEAU : Limite d'utilisation par utilisateur ==========
    #[ORM\Column(nullable: true)]
    private ?int $maxUsesPerUser = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));
        return $this;
    }

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): static
    {
        $this->discountType = $discountType;
        return $this;
    }

    public function getDiscountValue(): ?float
    {
        return $this->discountValue;
    }

    public function setDiscountValue(float $discountValue): static
    {
        $this->discountValue = $discountValue;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): static
    {
        $this->brand = $brand;
        return $this;
    }

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    // ========== NOUVEAU : Getter/Setter pour maxUsesPerUser ==========
    
    public function getMaxUsesPerUser(): ?int
    {
        return $this->maxUsesPerUser;
    }

    public function setMaxUsesPerUser(?int $maxUsesPerUser): static
    {
        $this->maxUsesPerUser = $maxUsesPerUser;
        return $this;
    }

    // Vérifier si le code est valide
    public function isValid(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->endDate && $this->endDate < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    // Calculer la réduction
    public function calculateDiscount(float $amount): float
    {
        if ($this->discountType === 'percentage') {
            return round($amount * ($this->discountValue / 100), 2);
        }
        
        return min($this->discountValue, $amount);
    }

    // Afficher la réduction de façon formatée
    public function getFormattedDiscount(): string
    {
        if ($this->discountType === 'percentage') {
            return $this->discountValue . '%';
        }
        return number_format($this->discountValue, 2, ',', ' ') . '€';
    }

    // Vérifier si un produit/device est éligible
    public function isEligible($item): bool
    {
        // Si pas de restriction = tout le site
        if (!$this->category && !$this->brand) {
            return true;
        }

        // Vérifier la catégorie
        if ($this->category && $item->getCategory() !== $this->category) {
            return false;
        }

        // Vérifier la marque
        if ($this->brand && $item->getBrand() !== $this->brand) {
            return false;
        }

        return true;
    }
}