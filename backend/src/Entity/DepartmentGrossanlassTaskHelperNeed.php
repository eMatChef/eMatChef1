<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Helferbedarf einer Aufgabe. Wie beim gruppenweiten Bedarf: Zeile mit leerem Label = Gesamtzahl,
 * weitere Zeilen = optionale Funktionen (Aufteilung, nicht zusätzlich). Personen werden erst später zugewiesen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_task_helper_need')]
class DepartmentGrossanlassTaskHelperNeed
{
    public const TOTAL_LABEL = '';

    #[ORM\Id]
    #[ORM\Column(name: 'task_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $taskId;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 120)]
    private string $label;

    #[ORM\Column(name: 'required_count', type: 'integer')]
    private int $requiredCount = 0;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    public function __construct(string $taskId, string $label, int $requiredCount, int $sortOrder = 0)
    {
        $this->taskId = $taskId;
        $this->label = $label;
        $this->requiredCount = $requiredCount;
        $this->sortOrder = $sortOrder;
    }

    public function getTaskId(): string { return $this->taskId; }
    public function getLabel(): string { return $this->label; }
    public function getRequiredCount(): int { return $this->requiredCount; }
    public function setRequiredCount(int $count): void { $this->requiredCount = $count; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $order): void { $this->sortOrder = $order; }
}
