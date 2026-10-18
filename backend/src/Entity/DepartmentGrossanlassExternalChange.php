<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Append-only: alter und neuer Zustand eines externen Datensatzes bei Anlage, Änderung, Stornierung oder Entfernung. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_ext_change')]
class DepartmentGrossanlassExternalChange
{
    public const TYPE_CREATED = 'created';
    public const TYPE_UPDATED = 'updated';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_REMOVED = 'removed';
    public const TYPE_RESTORED = 'restored';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'record_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $recordId;

    #[ORM\Column(name: 'run_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $runId;

    #[ORM\Column(name: 'change_type', type: 'string', length: 12)]
    private string $changeType;

    /** @var ?array<string, mixed> */
    #[ORM\Column(name: 'old_data', type: 'json', nullable: true)]
    private ?array $oldData;

    /** @var ?array<string, mixed> */
    #[ORM\Column(name: 'new_data', type: 'json', nullable: true)]
    private ?array $newData;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    /**
     * @param ?array<string, mixed> $old
     * @param ?array<string, mixed> $new
     */
    public function __construct(string $id, string $recordId, ?string $runId, string $type, ?array $old, ?array $new)
    {
        $this->id = $id;
        $this->recordId = $recordId;
        $this->runId = $runId;
        $this->changeType = $type;
        $this->oldData = $old;
        $this->newData = $new;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getRecordId(): string { return $this->recordId; }
    public function getRunId(): ?string { return $this->runId; }
    public function getChangeType(): string { return $this->changeType; }
    /** @return ?array<string, mixed> */
    public function getOldData(): ?array { return $this->oldData; }
    /** @return ?array<string, mixed> */
    public function getNewData(): ?array { return $this->newData; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
