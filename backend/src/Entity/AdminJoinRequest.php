<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_join_request')]
class AdminJoinRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, options: ['fixed' => true])]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'requested_department_name', type: 'string', length: 255)]
    private string $requestedDepartmentName;

    #[ORM\Column(name: 'requested_affiliation', type: 'string', length: 255, nullable: true)]
    private ?string $requestedAffiliation = null;

    #[ORM\Column(name: 'requested_organisation_id', type: 'string', length: 12, nullable: true, options: ['fixed' => true])]
    private ?string $requestedOrganisationId = null;

    #[ORM\Column(name: 'requested_parent_department_name', type: 'string', length: 255, nullable: true)]
    private ?string $requestedParentDepartmentName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(name: 'reviewed_by', type: 'string', length: 12, nullable: true, options: ['fixed' => true])]
    private ?string $reviewedById = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewed_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(name: 'assigned_department_id', type: 'string', length: 12, nullable: true, options: ['fixed' => true])]
    private ?string $assignedDepartmentId = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'assigned_department_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Department $assignedDepartment = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = $user->getId();
        return $this;
    }

    public function getRequestedDepartmentName(): string
    {
        return $this->requestedDepartmentName;
    }

    public function setRequestedDepartmentName(string $requestedDepartmentName): self
    {
        $this->requestedDepartmentName = $requestedDepartmentName;
        return $this;
    }

    public function getRequestedAffiliation(): ?string
    {
        return $this->requestedAffiliation;
    }

    public function setRequestedAffiliation(?string $requestedAffiliation): self
    {
        $this->requestedAffiliation = $requestedAffiliation;
        return $this;
    }

    public function getRequestedOrganisationId(): ?string
    {
        return $this->requestedOrganisationId;
    }

    public function setRequestedOrganisationId(?string $requestedOrganisationId): self
    {
        $this->requestedOrganisationId = $requestedOrganisationId;
        return $this;
    }

    public function getRequestedParentDepartmentName(): ?string
    {
        return $this->requestedParentDepartmentName;
    }

    public function setRequestedParentDepartmentName(?string $requestedParentDepartmentName): self
    {
        $this->requestedParentDepartmentName = $requestedParentDepartmentName;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function getReviewedById(): ?string
    {
        return $this->reviewedById;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;
        $this->reviewedById = $reviewedBy?->getId();
        return $this;
    }

    public function getAssignedDepartmentId(): ?string
    {
        return $this->assignedDepartmentId;
    }

    public function getAssignedDepartment(): ?Department
    {
        return $this->assignedDepartment;
    }

    public function setAssignedDepartment(?Department $assignedDepartment): self
    {
        $this->assignedDepartment = $assignedDepartment;
        $this->assignedDepartmentId = $assignedDepartment?->getId();
        return $this;
    }
}
