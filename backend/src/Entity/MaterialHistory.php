<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialHistory - Speichert Snapshots bei jeder Änderung eines Materials
 * 
 * Jeder Eintrag enthält:
 * - Den vollständigen Zustand (snapshot) zum Zeitpunkt der Speicherung
 * - Die konkreten Änderungen (changes) als Diff zum vorherigen Zustand
 * - Wer die Änderung durchgeführt hat
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_history')]
#[ORM\Index(name: 'idx_material_history_material', columns: ['material_item_id'])]
#[ORM\Index(name: 'idx_material_history_created', columns: ['created_at'])]
class MaterialHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $userId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** @var string Der Typ der Aktion: 'created', 'updated', 'deleted' */
    #[ORM\Column(type: 'string', length: 20)]
    private string $action = 'updated';

    /** @var array Vollständiger Zustand zum Zeitpunkt der Speicherung */
    #[ORM\Column(type: 'json')]
    private array $snapshot = [];

    /** @var array Geänderte Felder mit old/new Werten */
    #[ORM\Column(type: 'json')]
    private array $changes = [];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters & Setters

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getMaterialItemId(): string
    {
        return $this->materialItemId;
    }

    public function setMaterialItemId(string $materialItemId): self
    {
        $this->materialItemId = $materialItemId;
        return $this;
    }

    public function getMaterialItem(): MaterialItem
    {
        return $this->materialItem;
    }

    public function setMaterialItem(MaterialItem $materialItem): self
    {
        $this->materialItem = $materialItem;
        $this->materialItemId = $materialItem->getId();
        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        $this->userId = $user?->getId();
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function setSnapshot(array $snapshot): self
    {
        $this->snapshot = $snapshot;
        return $this;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function setChanges(array $changes): self
    {
        $this->changes = $changes;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
