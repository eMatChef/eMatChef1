<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierDeliveryLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplierDeliveryLineRepository::class)]
#[ORM\Table(name: 'supplier_delivery_line')]
class SupplierDeliveryLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'delivery_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $deliveryId;

    #[ORM\ManyToOne(targetEntity: SupplierDelivery::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'delivery_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierDelivery $delivery;

    #[ORM\Column(name: 'catalog_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $catalogItemId;

    #[ORM\ManyToOne(targetEntity: SupplierCatalogItem::class)]
    #[ORM\JoinColumn(name: 'catalog_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private SupplierCatalogItem $catalogItem;

    #[ORM\Column(type: 'integer')]
    private int $qty = 1;

    #[ORM\Column(name: 'unit_price', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    /** @var list<string> */
    #[ORM\Column(name: 'serial_numbers', type: 'json')]
    private array $serialNumbers = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(name: 'component_serials', type: 'json', nullable: true)]
    private ?array $componentSerials = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function setDeliveryId(string $deliveryId): self
    {
        $this->deliveryId = $deliveryId;

        return $this;
    }

    public function getDelivery(): SupplierDelivery
    {
        return $this->delivery;
    }

    public function setDelivery(SupplierDelivery $delivery): self
    {
        $this->delivery = $delivery;
        $this->deliveryId = $delivery->getId() ?? $this->deliveryId;

        return $this;
    }

    public function getCatalogItemId(): string
    {
        return $this->catalogItemId;
    }

    public function setCatalogItemId(string $catalogItemId): self
    {
        $this->catalogItemId = $catalogItemId;

        return $this;
    }

    public function getCatalogItem(): SupplierCatalogItem
    {
        return $this->catalogItem;
    }

    public function setCatalogItem(SupplierCatalogItem $catalogItem): self
    {
        $this->catalogItem = $catalogItem;
        $this->catalogItemId = $catalogItem->getId() ?? $this->catalogItemId;

        return $this;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): self
    {
        $this->qty = $qty;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /** @return list<string> */
    public function getSerialNumbers(): array
    {
        return $this->serialNumbers;
    }

    /** @param list<string> $serialNumbers */
    public function setSerialNumbers(array $serialNumbers): self
    {
        $this->serialNumbers = array_values(array_filter(
            array_map(static fn ($sn) => trim((string) $sn), $serialNumbers),
            static fn (string $sn) => $sn !== ''
        ));

        return $this;
    }

    /** @return list<array<string, mixed>>|null */
    public function getComponentSerials(): ?array
    {
        return $this->componentSerials;
    }

    /** @param list<array<string, mixed>>|null $componentSerials */
    public function setComponentSerials(?array $componentSerials): self
    {
        $this->componentSerials = $componentSerials;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $catalog = $this->catalogItem;

        return [
            'id' => $this->id,
            'delivery_id' => $this->deliveryId,
            'catalog_item_id' => $this->catalogItemId,
            'catalog_item_name' => $catalog->getName(),
            'catalog_item_sku' => $catalog->getSku(),
            'tracking_type' => $catalog->getTrackingType(),
            'qty' => $this->qty,
            'unit_price' => $this->unitPrice !== null ? (float) $this->unitPrice : null,
            'serial_numbers' => $this->serialNumbers,
            'component_serials' => $this->componentSerials,
            'sort_order' => $this->sortOrder,
        ];
    }
}
