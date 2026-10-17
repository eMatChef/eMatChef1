<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Optionaler Checklistenpunkt einer GA-Aufgabe. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_task_step')]
#[ORM\Index(name: 'idx_ga_task_step_task', columns: ['task_id', 'sort_order'])]
class DepartmentGrossanlassTaskStep
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'task_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $taskId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label = '';

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'is_done', type: 'boolean', options: ['default' => false])]
    private bool $done = false;

    #[ORM\Column(name: 'done_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $doneByUserId = null;

    #[ORM\Column(name: 'done_at', type: 'datetime', nullable: true)]
    private ?\DateTime $doneAt = null;

    public function __construct(string $id, string $taskId)
    {
        $this->id = $id;
        $this->taskId = $taskId;
    }

    public function getId(): string { return $this->id; }
    public function getTaskId(): string { return $this->taskId; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }
    public function isDone(): bool { return $this->done; }
    public function getDoneByUserId(): ?string { return $this->doneByUserId; }
    public function getDoneAt(): ?\DateTime { return $this->doneAt; }

    public function markDone(bool $done, ?string $userId): self
    {
        $this->done = $done;
        $this->doneByUserId = $done ? $userId : null;
        $this->doneAt = $done ? new \DateTime() : null;

        return $this;
    }
}
