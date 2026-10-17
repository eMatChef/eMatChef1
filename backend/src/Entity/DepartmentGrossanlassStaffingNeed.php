<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Personalbedarf einer Aufgabe (Bauprojekt-Bedarf hängt an der Standardaufgabe «Allgemein»).
 * Mehrere Bedarfe mit unterschiedlichen Zeitfenstern pro Aufgabe. `label` ist ein Funktionswunsch
 * (leer = ohne Funktion). Ohne Zeitfenster (`starts_at`/`ends_at` NULL) ist der Bedarf undatiert und
 * nicht verbindlich zuteilbar. Zuteilungen liegen getrennt in {@see DepartmentGrossanlassStaffingAssignment}.
 * Ersetzt `department_grossanlass_task_helper_need` und `department_grossanlass_group_helper_need`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_staffing_need')]
#[ORM\Index(name: 'idx_ga_staffing_need_task', columns: ['task_id'])]
#[ORM\Index(name: 'idx_ga_staffing_need_dept_window', columns: ['department_id', 'starts_at', 'ends_at'])]
class DepartmentGrossanlassStaffingNeed
{
    public const ORIGIN_MANUAL = 'manual';
    public const ORIGIN_MIGRATED = 'migrated';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(name: 'task_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $taskId;

    #[ORM\Column(type: 'string', length: 120, options: ['default' => ''])]
    private string $label = '';

    #[ORM\Column(name: 'required_count', type: 'integer', options: ['default' => 0])]
    private int $requiredCount = 0;

    #[ORM\Column(name: 'starts_at', type: 'datetime', nullable: true)]
    private ?\DateTime $startsAt = null;

    #[ORM\Column(name: 'ends_at', type: 'datetime', nullable: true)]
    private ?\DateTime $endsAt = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => self::ORIGIN_MANUAL])]
    private string $origin = self::ORIGIN_MANUAL;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct(string $id, string $departmentId, string $taskId)
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->taskId = $taskId;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getTaskId(): string { return $this->taskId; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this->touch(); }
    public function getRequiredCount(): int { return $this->requiredCount; }
    public function setRequiredCount(int $count): self { $this->requiredCount = max(0, $count); return $this->touch(); }
    public function getStartsAt(): ?\DateTime { return $this->startsAt; }
    public function getEndsAt(): ?\DateTime { return $this->endsAt; }
    public function isDated(): bool { return $this->startsAt !== null && $this->endsAt !== null; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $order): self { $this->sortOrder = $order; return $this; }
    public function getOrigin(): string { return $this->origin; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }

    /** Beide Grenzen oder keine (undatiert); ends_at muss nach starts_at liegen. */
    public function setWindow(?\DateTime $startsAt, ?\DateTime $endsAt): self
    {
        if (($startsAt === null) !== ($endsAt === null)) {
            throw new \InvalidArgumentException('Zeitfenster braucht Start und Ende oder keines von beiden');
        }
        if ($startsAt !== null && $endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Ende muss nach dem Start liegen');
        }
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;

        return $this->touch();
    }

    private function touch(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
