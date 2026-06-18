<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_display_screen')]
class DepartmentDisplayScreen
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'public_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $publicId;

    #[ORM\Column(name: 'access_code_hash', type: 'string', length: 255)]
    private string $accessCodeHash;

    #[ORM\Column(name: 'access_code_hint', type: 'string', length: 2, nullable: true)]
    private ?string $accessCodeHint = null;

    #[ORM\Column(name: 'code_version', type: 'integer')]
    private int $codeVersion = 1;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $revokedAt = null;

    #[ORM\Column(name: 'last_used_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastUsedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'subtitle_text', type: 'string', length: 500, nullable: true)]
    private ?string $subtitleText = null;

    #[ORM\Column(name: 'show_activities', type: 'boolean')]
    private bool $showActivities = true;

    #[ORM\Column(name: 'show_workshop', type: 'boolean')]
    private bool $showWorkshop = true;

    /** @var list<string> */
    #[ORM\Column(name: 'activity_types', type: 'json')]
    private array $activityTypes = ['activity', 'camp', 'event', 'external'];

    /** @var list<string> */
    #[ORM\Column(name: 'activity_statuses', type: 'json')]
    private array $activityStatuses = ['submitted', 'approved', 'packing', 'packed', 'at_event'];

    /** @var list<string> */
    #[ORM\Column(name: 'workshop_statuses', type: 'json')]
    private array $workshopStatuses = ['triage', 'planning', 'in_progress', 'awaiting_quote'];

    #[ORM\Column(name: 'show_statistics', type: 'boolean')]
    private bool $showStatistics = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(string $departmentId): self
    {
        $this->departmentId = $departmentId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function setPublicId(string $publicId): self
    {
        $this->publicId = $publicId;

        return $this;
    }

    public function getAccessCodeHash(): string
    {
        return $this->accessCodeHash;
    }

    public function setAccessCodeHash(string $accessCodeHash): self
    {
        $this->accessCodeHash = $accessCodeHash;

        return $this;
    }

    public function getAccessCodeHint(): ?string
    {
        return $this->accessCodeHint;
    }

    public function setAccessCodeHint(?string $accessCodeHint): self
    {
        $this->accessCodeHint = $accessCodeHint;

        return $this;
    }

    public function getCodeVersion(): int
    {
        return $this->codeVersion;
    }

    public function setCodeVersion(int $codeVersion): self
    {
        $this->codeVersion = $codeVersion;

        return $this;
    }

    public function incrementCodeVersion(): self
    {
        $this->codeVersion++;

        return $this;
    }

    public function getCreatedByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(?string $createdByUserId): self
    {
        $this->createdByUserId = $createdByUserId;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeInterface
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeInterface $revokedAt): self
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function getLastUsedAt(): ?\DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeInterface $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getSubtitleText(): ?string
    {
        return $this->subtitleText;
    }

    public function setSubtitleText(?string $subtitleText): self
    {
        $this->subtitleText = $subtitleText !== null && trim($subtitleText) === '' ? null : $subtitleText;

        return $this;
    }

    public function isShowActivities(): bool
    {
        return $this->showActivities;
    }

    public function setShowActivities(bool $showActivities): self
    {
        $this->showActivities = $showActivities;

        return $this;
    }

    public function isShowWorkshop(): bool
    {
        return $this->showWorkshop;
    }

    public function setShowWorkshop(bool $showWorkshop): self
    {
        $this->showWorkshop = $showWorkshop;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getActivityTypes(): array
    {
        return $this->activityTypes;
    }

    /**
     * @param list<string> $activityTypes
     */
    public function setActivityTypes(array $activityTypes): self
    {
        $this->activityTypes = $activityTypes;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getActivityStatuses(): array
    {
        return $this->activityStatuses;
    }

    /**
     * @param list<string> $activityStatuses
     */
    public function setActivityStatuses(array $activityStatuses): self
    {
        $this->activityStatuses = $activityStatuses;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getWorkshopStatuses(): array
    {
        return $this->workshopStatuses;
    }

    /**
     * @param list<string> $workshopStatuses
     */
    public function setWorkshopStatuses(array $workshopStatuses): self
    {
        $this->workshopStatuses = $workshopStatuses;

        return $this;
    }

    public function isShowStatistics(): bool
    {
        return $this->showStatistics;
    }

    public function setShowStatistics(bool $showStatistics): self
    {
        $this->showStatistics = $showStatistics;

        return $this;
    }
}
