<?php

namespace App\Entity;

use App\Entity\Address;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $orderNumber;

    #[ORM\Column]
    private \DateTimeImmutable $orderDate;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private string $firstName;

    #[ORM\Column(length: 255)]
    private string $lastName;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 20)]
    private string $phone;

    // Adresse de livraison stockée directement dans la commande
    #[ORM\Column(length: 255)]
    private string $street;

    #[ORM\Column(length: 100)]
    private string $city;

    #[ORM\Column(length: 10)]
    private string $postalCode;

    // Référence optionnelle vers l'adresse utilisateur (pour historique)
    #[ORM\ManyToOne(targetEntity: Address::class)]
    private ?Address $sourceAddress = null;

    /**
     * @var Collection<int, OrderProducts>
     */
    #[ORM\OneToMany(targetEntity: OrderProducts::class, mappedBy: '_order', orphanRemoval: true)]
    private Collection $orderProducts;

    #[ORM\Column]
    private ?float $totalPrice = null;

    // Paiement
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentId = null;

    #[ORM\Column(length: 50, options: ['default' => 'pending'])]
    private string $status = 'pending';

    public function __construct()
    {
        $this->orderDate    = new \DateTimeImmutable();
        $this->orderProducts = new ArrayCollection();
        $this->generateOrderNumber();
    }

    private function generateOrderNumber(): void
    {
        $this->orderNumber = 'CMD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getId(): ?int { return $this->id; }

    public function getOrderNumber(): string { return $this->orderNumber; }
    public function setOrderNumber(string $orderNumber): static { $this->orderNumber = $orderNumber; return $this; }

    public function getOrderDate(): \DateTimeImmutable { return $this->orderDate; }
    public function setOrderDate(\DateTimeImmutable $orderDate): self { $this->orderDate = $orderDate; return $this; }

    // Alias utilisés par certains templates
    public function getCreatedAt(): \DateTimeImmutable { return $this->orderDate; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->orderDate = $createdAt; return $this; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        // Ne copie que les valeurs non vides pour éviter d’écraser avec null
        if ($user) {
            $v = $user->getFirstName(); if ($v !== null && $v !== '') { $this->firstName = $v; }
            $v = $user->getLastName();  if ($v !== null && $v !== '') { $this->lastName  = $v; }
            $v = $user->getEmail();     if ($v !== null && $v !== '') { $this->email     = $v; }
            $v = $user->getPhone();     if ($v !== null && $v !== '') { $this->phone     = $v; }
        }

        return $this;
    }

    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }

    public function getStreet(): string { return $this->street; }
    public function setStreet(string $street): self { $this->street = $street; return $this; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $city): self { $this->city = $city; return $this; }

    public function getPostalCode(): string { return $this->postalCode; }
    public function setPostalCode(string $postalCode): self { $this->postalCode = $postalCode; return $this; }

    public function getSourceAddress(): ?Address { return $this->sourceAddress; }
    public function setSourceAddress(?Address $sourceAddress): self { $this->sourceAddress = $sourceAddress; return $this; }

    // Copier une adresse utilisateur vers l'adresse de livraison
    public function copyFromAddress(Address $address): self
    {
        $this->street       = $address->getStreet();
        $this->city         = $address->getCity();
        $this->postalCode   = $address->getPostalCode();
        $this->sourceAddress = $address;
        return $this;
    }

    /** @return Collection<int, OrderProducts> */
    public function getOrderProducts(): Collection { return $this->orderProducts; }

    public function addOrderProduct(OrderProducts $orderProduct): static
    {
        if (!$this->orderProducts->contains($orderProduct)) {
            $this->orderProducts->add($orderProduct);
            $orderProduct->setOrder($this);
        }
        return $this;
    }

    public function removeOrderProduct(OrderProducts $orderProduct): static
    {
        if ($this->orderProducts->removeElement($orderProduct)) {
            if ($orderProduct->getOrder() === $this) {
                $orderProduct->setOrder(null);
            }
        }
        return $this;
    }

    public function getTotalPrice(): ?float { return $this->totalPrice; }
    public function setTotalPrice(float $totalPrice): static { $this->totalPrice = $totalPrice; return $this; }

    // Alias pour compatibilité (Twig/contrôleur)
    public function getTotal(): ?float { return $this->totalPrice; }
    public function setTotal(float $total): static { $this->totalPrice = $total; return $this; }

    // Paiement
    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function setPaymentMethod(?string $paymentMethod): self { $this->paymentMethod = $paymentMethod; return $this; }

    public function getPaymentId(): ?string { return $this->paymentId; }
    public function setPaymentId(?string $paymentId): self { $this->paymentId = $paymentId; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isRefunded(): bool { return $this->status === 'refunded'; }
}
