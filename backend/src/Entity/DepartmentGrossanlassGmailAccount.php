<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_gmail_account')]
class DepartmentGrossanlassGmailAccount
{
    #[ORM\Id]
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\OneToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email = '';

    #[ORM\Column(name: 'refresh_token_enc', type: 'text')]
    private string $refreshTokenEnc = '';

    #[ORM\Column(name: 'access_token_enc', type: 'text', nullable: true)]
    private ?string $accessTokenEnc = null;

    #[ORM\Column(name: 'access_expires_at', type: 'datetime', nullable: true)]
    private ?\DateTime $accessExpiresAt = null;

    /** @var array<string, string> */
    #[ORM\Column(name: 'label_map', type: 'json')]
    private array $labelMap = [];

    #[ORM\Column(name: 'connected_at', type: 'datetime')]
    private \DateTime $connectedAt;

    #[ORM\Column(name: 'connected_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $connectedByUserId = null;

    public function __construct()
    {
        $this->connectedAt = new \DateTime();
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getRefreshTokenEnc(): string
    {
        return $this->refreshTokenEnc;
    }

    public function setRefreshTokenEnc(string $refreshTokenEnc): self
    {
        $this->refreshTokenEnc = $refreshTokenEnc;

        return $this;
    }

    public function getAccessTokenEnc(): ?string
    {
        return $this->accessTokenEnc;
    }

    public function setAccessTokenEnc(?string $accessTokenEnc): self
    {
        $this->accessTokenEnc = $accessTokenEnc;

        return $this;
    }

    public function getAccessExpiresAt(): ?\DateTime
    {
        return $this->accessExpiresAt;
    }

    public function setAccessExpiresAt(?\DateTime $accessExpiresAt): self
    {
        $this->accessExpiresAt = $accessExpiresAt;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getLabelMap(): array
    {
        return $this->labelMap;
    }

    /**
     * @param array<string, string> $labelMap
     */
    public function setLabelMap(array $labelMap): self
    {
        $this->labelMap = $labelMap;

        return $this;
    }

    public function getConnectedAt(): \DateTime
    {
        return $this->connectedAt;
    }

    public function setConnectedAt(\DateTime $connectedAt): self
    {
        $this->connectedAt = $connectedAt;

        return $this;
    }

    public function getConnectedByUserId(): ?string
    {
        return $this->connectedByUserId;
    }

    public function setConnectedByUserId(?string $connectedByUserId): self
    {
        $this->connectedByUserId = $connectedByUserId;

        return $this;
    }
}
