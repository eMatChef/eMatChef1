<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Protokoll der Archivierung/Wiederherstellung eines Bauauftrags (wer, wann, warum). Bleibt auch nach der Wiederherstellung erhalten. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_order_archive_log')]
#[ORM\Index(name: 'idx_ga_order_archive_log_group', columns: ['group_id', 'created_at'])]
class DepartmentGrossanlassOrderArchiveLog
{
    public const ARCHIVED = 'archived';
    public const RESTORED = 'restored';

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

    #[ORM\Column(type: 'string', length: 16)]
    private string $type;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $id, Department $department, Group $group, string $type, string $userId, ?string $note)
    {
        $this->id = $id;
        $this->department = $department;
        $this->departmentId = $department->getId();
        $this->group = $group;
        $this->groupId = $group->getId();
        $this->type = $type;
        $this->userId = $userId;
        $this->note = $note;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getGroupId(): string { return $this->groupId; }
    public function getType(): string { return $this->type; }
    public function getUserId(): string { return $this->userId; }
    public function getNote(): ?string { return $this->note; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
