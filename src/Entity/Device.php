<?php

namespace App\Entity;

use App\Repository\DeviceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceRepository::class)]
class Device
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(type: 'text')]
    private ?string $ingredients = null;

    #[ORM\Column(type: 'text')]
    private ?string $usageAdvice = null;

    #[ORM\Column]
    private ?int $stock = null;

    #[ORM\ManyToOne(inversedBy: 'devices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'devices')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Brand $brand = null;

    /**
     * @var Collection<int, SkinType>
     */
    #[ORM\ManyToMany(targetEntity: SkinType::class, inversedBy: 'devices')]
    private Collection $skin_type;

    /**
     * @var Collection<int, AddDeviceHistory>
     */
    #[ORM\OneToMany(targetEntity: AddDeviceHistory::class, mappedBy: 'device')]
    private Collection $addDeviceHistories;

    /**
     * @var Collection<int, OrderProducts>
     */
    #[ORM\OneToMany(targetEntity: OrderProducts::class, mappedBy: 'device')]
    private Collection $orderProducts;

    public function __construct()
    {
        $this->skin_type = new ArrayCollection();
        $this->addDeviceHistories = new ArrayCollection();
        $this->orderProducts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getIngredients(): ?string
    {
        return $this->ingredients;
    }

    public function setIngredients(?string $ingredients): static
    {
        $this->ingredients = $ingredients ?? '';  // Convertit NULL en chaîne vide
        return $this;
    }


    public function getUsageAdvice(): ?string
    {
        return $this->usageAdvice;
    }

    public function setUsageAdvice(?string $usageAdvice): static
    {
        $this->usageAdvice = $usageAdvice ?? '';  // Convertit NULL en chaîne vide
        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

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

    /**
     * @return Collection<int, SkinType>
     */
    public function getSkinType(): Collection
    {
        return $this->skin_type;
    }

    public function addSkinType(SkinType $skinType): static
    {
        if (!$this->skin_type->contains($skinType)) {
            $this->skin_type->add($skinType);
        }

        return $this;
    }

    public function removeSkinType(SkinType $skinType): static
    {
        $this->skin_type->removeElement($skinType);

        return $this;
    }

    /**
     * @return Collection<int, AddDeviceHistory>
     */
    public function getAddDeviceHistories(): Collection
    {
        return $this->addDeviceHistories;
    }

    public function addAddDeviceHistory(AddDeviceHistory $addDeviceHistory): static
    {
        if (!$this->addDeviceHistories->contains($addDeviceHistory)) {
            $this->addDeviceHistories->add($addDeviceHistory);
            $addDeviceHistory->setDevice($this);
        }

        return $this;
    }

    public function removeAddDeviceHistory(AddDeviceHistory $addDeviceHistory): static
    {
        if ($this->addDeviceHistories->removeElement($addDeviceHistory)) {
            // set the owning side to null (unless already changed)
            if ($addDeviceHistory->getDevice() === $this) {
                $addDeviceHistory->setDevice(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderProducts>
     */
    public function getOrderProducts(): Collection
    {
        return $this->orderProducts;
    }

    public function addOrderProduct(OrderProducts $orderProduct): static
    {
        if (!$this->orderProducts->contains($orderProduct)) {
            $this->orderProducts->add($orderProduct);
            $orderProduct->setDevice($this);
        }

        return $this;
    }

    public function removeOrderProduct(OrderProducts $orderProduct): static
    {
        if ($this->orderProducts->removeElement($orderProduct)) {
            // set the owning side to null (unless already changed)
            if ($orderProduct->getDevice() === $this) {
                $orderProduct->setDevice(null);
            }
        }

        return $this;
    }
}
