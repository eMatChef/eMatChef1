<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Verlauf der formellen Gesamtabnahme eines Bauauftrags. Der Zustand ist das jeweils letzte Ereignis (`seq` je Bauauftrag):
 * reported → zur Abnahme gemeldet; accepted → abgenommen; returned/withdrawn/reopened → wieder offen.
 * Wer, wann und mit welcher Begründung bleibt unveränderlich gespeichert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_order_event')]
#[ORM\UniqueConstraint(name: 'uniq_ga_order_event_seq', columns: ['group_id', 'seq'])]
class DepartmentGrossanlassOrderEvent
{
    public const REPORTED = 'reported';
    public const ACCEPTED = 'accepted';
    public const RETURNED = 'returned';
    public const WITHDRAWN = 'withdrawn';
    public const REOPENED = 'reopened';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $groupId;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\Column(type: 'integer')]
    private int $seq;

    #[ORM\Column(type: 'string', length: 16)]
    private string $type;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $id, Department $department, Group $group, int $seq, string $type, string $userId, ?string $note)
    {
        $this->id = $id;
        $this->department = $department;
        $this->departmentId = $department->getId();
        $this->group = $group;
        $this->groupId = $group->getId();
        $this->seq = $seq;
        $this->type = $type;
        $this->userId = $userId;
        $this->note = $note;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getGroupId(): string { return $this->groupId; }
    public function getSeq(): int { return $this->seq; }
    public function getType(): string { return $this->type; }
    public function getUserId(): string { return $this->userId; }
    public function getNote(): ?string { return $this->note; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
