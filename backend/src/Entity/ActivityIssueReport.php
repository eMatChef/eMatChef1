<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityIssueReport - Meldungen während der Ausleihe
 * 
 * Typen:
 * - repair: Material ist kaputt, braucht Reparatur
 * - loss: Material fehlt / verloren
 * - consumption: Verbrauchsmaterial aufgebraucht (Fackeln, Gas, etc.)
 * - damage: Schaden dokumentieren
 * - not_taken: Bewusst nicht mit Ausgabe / ins Pack aufgenommen (Dokumentation, kein Lager-Verlust wie bei Verlust)
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_issue_report')]
#[ORM\Index(name: 'idx_issue_report_activity', columns: ['activity_id'])]
#[ORM\Index(name: 'idx_issue_report_type', columns: ['type'])]
class ActivityIssueReport
{
    public const TYPE_REPAIR = 'repair';
    public const TYPE_LOSS = 'loss';
    public const TYPE_CONSUMPTION = 'consumption';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_NOT_TAKEN = 'not_taken';

    public const ALL_TYPES = [
        self::TYPE_REPAIR,
        self::TYPE_LOSS,
        self::TYPE_CONSUMPTION,
        self::TYPE_DAMAGE,
        self::TYPE_NOT_TAKEN,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $materialItemId = null;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialItem $materialItem = null;

    /** Typ: repair, loss, consumption, damage, not_taken */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'damage'])]
    private string $type = 'damage';

    /** Betroffene Menge */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    /** Beschreibung */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Gemeldet von */
    #[ORM\Column(name: 'reported_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $reportedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reported_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reportedByUser = null;

    /** Wann gemeldet */
    #[ORM\Column(name: 'reported_at', type: 'datetime')]
    private \DateTime $reportedAt;

    /** Foto-URL (optional, legacy — Dual-read mit photos[0]) */
    #[ORM\Column(name: 'photo_url', type: 'string', length: 500, nullable: true)]
    private ?string $photoUrl = null;

    /** Fotos (JSON-Array, einheitliches MediaPhoto-Shape) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photos = null;

    /** Erledigt? */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $resolved = false;

    #[ORM\Column(name: 'resolved_at', type: 'datetime', nullable: true)]
    private ?\DateTime $resolvedAt = null;

    #[ORM\Column(name: 'resolved_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $resolvedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'resolved_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedByUser = null;

    /** Notizen */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->reportedAt = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    // === Getters & Setters ===

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getActivityId(): string { return $this->activityId; }
    public function setActivityId(string $activityId): self { $this->activityId = $activityId; return $this; }

    public function getActivity(): Activity { return $this->activity; }
    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity->getId();
        return $this;
    }

    public function getMaterialItemId(): ?string { return $this->materialItemId; }
    public function setMaterialItemId(?string $materialItemId): self { $this->materialItemId = $materialItemId; return $this; }

    public function getMaterialItem(): ?MaterialItem { return $this->materialItem; }
    public function setMaterialItem(?MaterialItem $materialItem): self
    {
        $this->materialItem = $materialItem;
        $this->materialItemId = $materialItem?->getId();
        return $this;
    }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getReportedByUserId(): ?string { return $this->reportedByUserId; }
    public function setReportedByUserId(?string $reportedByUserId): self { $this->reportedByUserId = $reportedByUserId; return $this; }

    public function getReportedByUser(): ?User { return $this->reportedByUser; }
    public function setReportedByUser(?User $reportedByUser): self
    {
        $this->reportedByUser = $reportedByUser;
        $this->reportedByUserId = $reportedByUser?->getId();
        return $this;
    }

    public function getReportedAt(): \DateTime { return $this->reportedAt; }
    public function setReportedAt(\DateTime $reportedAt): self { $this->reportedAt = $reportedAt; return $this; }

    public function getPhotoUrl(): ?string { return $this->photoUrl; }
    public function setPhotoUrl(?string $photoUrl): self { $this->photoUrl = $photoUrl; return $this; }

    /** @return list<array<string, mixed>>|null */
    public function getPhotos(): ?array { return $this->photos; }

    /** @param list<array<string, mixed>>|null $photos */
    public function setPhotos(?array $photos): self { $this->photos = $photos; return $this; }

    /** Erstes Foto oder legacy photo_url (Dual-read). */
    public function getPrimaryPhotoUrl(): ?string
    {
        $photos = $this->photos ?? [];
        if ($photos !== []) {
            $first = $photos[0];
            if (\is_array($first) && !empty($first['url'])) {
                return (string) $first['url'];
            }
            if (\is_string($first) && $first !== '') {
                return $first;
            }
        }

        $legacy = $this->photoUrl;
        if ($legacy !== null && trim($legacy) !== '') {
            return trim($legacy);
        }

        return null;
    }

    /** photo_url aus photos[0] ableiten (Backward-Compat für Supplier-View). */
    public function syncPrimaryPhotoUrl(): self
    {
        $this->photoUrl = $this->getPrimaryPhotoUrl();

        return $this;
    }

    public function isResolved(): bool { return $this->resolved; }
    public function setResolved(bool $resolved): self { $this->resolved = $resolved; return $this; }

    public function getResolvedAt(): ?\DateTime { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTime $resolvedAt): self { $this->resolvedAt = $resolvedAt; return $this; }

    public function getResolvedByUserId(): ?string { return $this->resolvedByUserId; }
    public function setResolvedByUserId(?string $resolvedByUserId): self { $this->resolvedByUserId = $resolvedByUserId; return $this; }

    public function getResolvedByUser(): ?User { return $this->resolvedByUser; }
    public function setResolvedByUser(?User $resolvedByUser): self
    {
        $this->resolvedByUser = $resolvedByUser;
        $this->resolvedByUserId = $resolvedByUser?->getId();
        return $this;
    }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    // === Helper ===

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_REPAIR => 'Reparatur',
            self::TYPE_LOSS => 'Verlust',
            self::TYPE_CONSUMPTION => 'Verbrauch',
            self::TYPE_DAMAGE => 'Beschädigung',
            self::TYPE_NOT_TAKEN => 'Nicht mitgegeben',
            default => $this->type,
        };
    }
}
