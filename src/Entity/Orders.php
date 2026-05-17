<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrdersRepository;
use App\State\OrderCheckoutProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;





#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')",
            normalizationContext: ['groups' => ['order:read']],
        ),
        new Get(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF') or (is_granted('ROLE_CLIENT') and object.getClient() == user)",
            normalizationContext: ['groups' => ['order:read']],
        ),
        new Post(
            security: "is_granted('ROLE_CLIENT')",
            normalizationContext: ['groups' => ['order:read']],
            denormalizationContext: ['groups' => ['order:write']],
            processor: OrderCheckoutProcessor::class,
        ),
        new Put(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')",
            normalizationContext: ['groups' => ['order:read']],
            denormalizationContext: ['groups' => ['order:status']],
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
        ),
    ]
)]
#[ORM\Entity(repositoryClass: OrdersRepository::class)]
class Orders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Groups(['order:read'])]
    private ?string $orderNumber = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:read'])]
    private ?string $customerName = null;

    #[ORM\Column(length: 50)]
    #[Groups(['order:read', 'order:status'])]
    private ?string $status = null;

    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?float $total = null;

    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $deliveryAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Users $client = null;

    /**
     * Legacy M2M kept so existing Twig views and admin data are not broken.
     * New orders placed via API use lines instead.
     */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'order_product')]
    private Collection $products;

    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['order:read', 'order:write'])]
    private Collection $lines;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getClient(): ?Users
    {
        return $this->client;
    }

    public function setClient(?Users $client): static
    {
        $this->client = $client;

        return $this;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /** @return Collection<int, OrderLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(OrderLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setOrder($this);
        }

        return $this;
    }

    public function removeLine(OrderLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getOrder() === $this) {
                $line->setOrder(null);
            }
        }

        return $this;
    }
}
