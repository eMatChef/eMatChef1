<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Synchronisationslauf einer externen Quelle (Historie: Zeitpunkt, Zähler, Fehler). Enthält keine Zugangsdaten. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_ext_run')]
class DepartmentGrossanlassExternalRun
{
    public const OUTCOME_OK = 'ok';
    public const OUTCOME_PARTIAL = 'partial';
    public const OUTCOME_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'source_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $sourceId;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(name: 'payload_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $payloadId = null;

    #[ORM\Column(name: 'started_at', type: 'datetime')]
    private \DateTime $startedAt;

    #[ORM\Column(name: 'finished_at', type: 'datetime', nullable: true)]
    private ?\DateTime $finishedAt = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $outcome = self::OUTCOME_FAILED;

    #[ORM\Column(name: 'created_count', type: 'integer', options: ['default' => 0])]
    private int $created = 0;

    #[ORM\Column(name: 'updated_count', type: 'integer', options: ['default' => 0])]
    private int $updated = 0;

    #[ORM\Column(name: 'unchanged_count', type: 'integer', options: ['default' => 0])]
    private int $unchanged = 0;

    #[ORM\Column(name: 'removed_count', type: 'integer', options: ['default' => 0])]
    private int $removed = 0;

    #[ORM\Column(name: 'conflict_count', type: 'integer', options: ['default' => 0])]
    private int $conflicts = 0;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $errors = [];

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $userId = null;

    public function __construct(string $id, string $sourceId, string $departmentId, ?string $userId)
    {
        $this->id = $id;
        $this->sourceId = $sourceId;
        $this->departmentId = $departmentId;
        $this->userId = $userId;
        $this->startedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getSourceId(): string { return $this->sourceId; }
    public function setPayloadId(?string $v): self { $this->payloadId = $v; return $this; }
    public function getPayloadId(): ?string { return $this->payloadId; }
    public function getStartedAt(): \DateTime { return $this->startedAt; }
    public function getFinishedAt(): ?\DateTime { return $this->finishedAt; }
    public function getOutcome(): string { return $this->outcome; }
    public function getUserId(): ?string { return $this->userId; }
    public function getErrors(): array { return $this->errors; }

    /** @return array{created: int, updated: int, unchanged: int, removed: int, conflicts: int} */
    public function counts(): array
    {
        return ['created' => $this->created, 'updated' => $this->updated, 'unchanged' => $this->unchanged, 'removed' => $this->removed, 'conflicts' => $this->conflicts];
    }

    /**
     * @param array{created: int, updated: int, unchanged: int, removed: int, conflicts: int} $counts
     * @param list<array<string, mixed>> $errors
     */
    public function finish(string $outcome, array $counts, array $errors): self
    {
        $this->outcome = $outcome;
        $this->created = $counts['created'];
        $this->updated = $counts['updated'];
        $this->unchanged = $counts['unchanged'];
        $this->removed = $counts['removed'];
        $this->conflicts = $counts['conflicts'];
        $this->errors = array_slice($errors, 0, 200);
        $this->finishedAt = new \DateTime();

        return $this;
    }
}
