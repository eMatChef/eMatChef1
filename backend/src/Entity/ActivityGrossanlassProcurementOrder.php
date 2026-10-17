<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_procurement_order')]
class ActivityGrossanlassProcurementOrder
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Grundlage der Bestellung: Offerte, telefonische/mündliche Absprache ohne Offerte. */
    public const AGREEMENT_QUOTE = 'quote';
    public const AGREEMENT_PHONE = 'phone';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'procurement_line_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $procurementLineId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementLine::class)]
    #[ORM\JoinColumn(name: 'procurement_line_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassProcurementLine $procurementLine;

    #[ORM\Column(name: 'ordered_at', type: 'datetime')]
    private \DateTime $orderedAt;

    #[ORM\Column(name: 'cost_chf', type: 'decimal', precision: 12, scale: 2)]
    private string $costChf;

    #[ORM\Column(name: 'order_ref', type: 'string', length: 255, nullable: true)]
    private ?string $orderRef = null;

    #[ORM\Column(name: 'delivery_at', type: 'datetime', nullable: true)]
    private ?\DateTime $deliveryAt = null;

    #[ORM\Column(name: 'quote_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $quoteId = null;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementQuote::class)]
    #[ORM\JoinColumn(name: 'quote_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityGrossanlassProcurementQuote $quote = null;

    /** Lieferant bei Absprache ohne Offerte; mit Offerte gilt deren Lieferant. */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $supplier = null;

    #[ORM\Column(name: 'agreement_kind', type: 'string', length: 16, options: ['default' => self::AGREEMENT_QUOTE])]
    private string $agreementKind = self::AGREEMENT_QUOTE;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::STATUS_CONFIRMED])]
    private string $status = self::STATUS_CONFIRMED;

    /** Rückgabepflicht dieser Bestellung; null = wie die Offerte. */
    #[ORM\Column(name: 'return_needed', type: 'boolean', nullable: true)]
    private ?bool $returnNeeded = null;

    /** `pickup` oder `delivery`; null = wie die Offerte. */
    #[ORM\Column(name: 'inbound_mode', type: 'string', length: 16, nullable: true)]
    private ?string $inboundMode = null;

    #[ORM\Column(name: 'cancelled_at', type: 'datetime', nullable: true)]
    private ?\DateTime $cancelledAt = null;

    #[ORM\Column(name: 'cancel_reason', type: 'text', nullable: true)]
    private ?string $cancelReason = null;

    /** Bestellte Teilmenge; null = ganze Positionsmenge (Bestellungen vor der Teilmengen-Erweiterung). */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->orderedAt = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getProcurementLineId(): string
    {
        return $this->procurementLineId;
    }

    public function getProcurementLine(): ActivityGrossanlassProcurementLine
    {
        return $this->procurementLine;
    }

    public function setProcurementLine(ActivityGrossanlassProcurementLine $line): self
    {
        $this->procurementLine = $line;
        $this->procurementLineId = $line->getId();

        return $this;
    }

    public function getQuoteId(): ?string
    {
        return $this->quoteId;
    }

    public function getQuote(): ?ActivityGrossanlassProcurementQuote
    {
        return $this->quote;
    }

    public function setQuote(?ActivityGrossanlassProcurementQuote $quote): self
    {
        $this->quote = $quote;
        $this->quoteId = $quote?->getId();

        return $this;
    }

    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    public function setSupplier(?string $supplier): self
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getAgreementKind(): string
    {
        return $this->agreementKind;
    }

    public function setAgreementKind(string $agreementKind): self
    {
        $this->agreementKind = $agreementKind;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function cancel(?string $reason): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = new \DateTime();
        $this->cancelReason = $reason;

        return $this;
    }

    public function getReturnNeeded(): ?bool
    {
        return $this->returnNeeded;
    }

    public function setReturnNeeded(?bool $returnNeeded): self
    {
        $this->returnNeeded = $returnNeeded;

        return $this;
    }

    public function getInboundMode(): ?string
    {
        return $this->inboundMode;
    }

    public function setInboundMode(?string $inboundMode): self
    {
        $this->inboundMode = $inboundMode;

        return $this;
    }

    public function getCancelledAt(): ?\DateTime
    {
        return $this->cancelledAt;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getOrderedAt(): \DateTime
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(\DateTime $orderedAt): self
    {
        $this->orderedAt = $orderedAt;

        return $this;
    }

    public function getCostChf(): string
    {
        return $this->costChf;
    }

    public function setCostChf(string $costChf): self
    {
        $this->costChf = $costChf;

        return $this;
    }

    public function getOrderRef(): ?string
    {
        return $this->orderRef;
    }

    public function setOrderRef(?string $orderRef): self
    {
        $this->orderRef = $orderRef;

        return $this;
    }

    public function getDeliveryAt(): ?\DateTime
    {
        return $this->deliveryAt;
    }

    public function setDeliveryAt(?\DateTime $deliveryAt): self
    {
        $this->deliveryAt = $deliveryAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
