<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * GroupMembership - Verknüpft User mit Gruppen
 * 
 * Ein User kann Mitglied mehrerer Gruppen sein.
 * Jede Mitgliedschaft hat eine Rolle (mw, dc, l1, l2, l3, u).
 * isPrimary markiert die Hauptgruppe eines Users.
 */
#[ORM\Entity]
#[ORM\Table(name: 'group_membership')]
#[ORM\Index(name: 'idx_gm_group', columns: ['group_id'])]
#[ORM\Index(name: 'idx_gm_user', columns: ['user_id'])]
class GroupMembership
{
    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\Id]
    #[ORM\Column(name: 'group_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $groupId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    // Rolle innerhalb der Gruppe: leader (Gruppenchef) oder member (Mitglied)
    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: [
        'leader', 'member'
    ], message: 'Ungültige Gruppenrolle')]
    private string $role = 'member';

    // Hauptgruppe des Users
    #[ORM\Column(name: 'is_primary', type: 'boolean', options: ['default' => false])]
    private bool $isPrimary = false;

    // Timestamps
    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // === Getters & Setters ===

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): self
    {
        $this->groupId = $groupId;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = $user->getId();
        return $this;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): self
    {
        $this->group = $group;
        $this->groupId = $group->getId();
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getIsPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;
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

    // === Helper ===

    /**
     * Gibt die Rollen-Bezeichnung zurück
     */
    public function getRoleLabel(): string
    {
        return match($this->role) {
            'leader' => 'Gruppenchef',
            'member' => 'Mitglied',
            default => $this->role,
        };
    }

    /**
     * Prüft ob die Rolle eine Leiterrolle ist
     */
    public function isLeader(): bool
    {
        return $this->role === 'leader';
    }
}
