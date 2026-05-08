<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_join_request_event')]
#[ORM\Index(name: 'idx_ajre_request', columns: ['admin_join_request_id'])]
#[ORM\Index(name: 'idx_ajre_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_ajre_created', columns: ['created_at'])]
class AdminJoinRequestEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(name: 'admin_join_request_id', type: 'string', length: 12, options: ['fixed' => true])]
    private string $adminJoinRequestId;

    #[ORM\ManyToOne(targetEntity: AdminJoinRequest::class)]
    #[ORM\JoinColumn(name: 'admin_join_request_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AdminJoinRequest $adminJoinRequest;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, options: ['fixed' => true])]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 32)]
    private string $action;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getAdminJoinRequestId(): string
    {
        return $this->adminJoinRequestId;
    }

    public function getAdminJoinRequest(): AdminJoinRequest
    {
        return $this->adminJoinRequest;
    }

    public function setAdminJoinRequest(AdminJoinRequest $adminJoinRequest): self
    {
        $this->adminJoinRequest = $adminJoinRequest;
        $this->adminJoinRequestId = $adminJoinRequest->getId();
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
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

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
