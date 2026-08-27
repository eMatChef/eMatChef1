<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'print_layout')]
class PrintLayout
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_ORGANISATION = 'organisation';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'media_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $mediaId;

    #[ORM\ManyToOne(targetEntity: PrintMedia::class)]
    #[ORM\JoinColumn(name: 'media_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private PrintMedia $media;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $departmentId = null;

    #[ORM\Column(name: 'organisation_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $organisationId = null;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $fields = [];

    #[ORM\Column(name: 'template_filename', type: 'string', length: 180, nullable: true)]
    private ?string $templateFilename = null;

    #[ORM\Column(name: 'template_sha256', type: 'string', length: 64, nullable: true)]
    private ?string $templateSha256 = null;

    #[ORM\Column(name: 'include_template_on_print', type: 'boolean', options: ['default' => false])]
    private bool $includeTemplateOnPrint = false;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PUBLISHED;

    #[ORM\Column(type: 'string', length: 20)]
    private string $scope = self::SCOPE_ORGANISATION;

    #[ORM\Column(name: 'global_requested', type: 'boolean', options: ['default' => false])]
    private bool $globalRequested = false;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'reviewed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $reviewedByUserId = null;

    #[ORM\Column(name: 'reviewed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $reviewedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getMediaId(): string { return $this->mediaId; }
    public function getMedia(): PrintMedia { return $this->media; }
    public function setMedia(PrintMedia $media): self
    {
        $this->media = $media;
        $this->mediaId = $media->getId();
        return $this;
    }

    public function getDepartmentId(): ?string { return $this->departmentId; }
    public function setDepartmentId(?string $departmentId): self { $this->departmentId = $departmentId; return $this; }

    public function getOrganisationId(): ?string { return $this->organisationId; }
    public function setOrganisationId(?string $organisationId): self { $this->organisationId = $organisationId; return $this; }

    /** @return list<array<string, mixed>> */
    public function getFields(): array { return $this->fields; }

    /** @param list<array<string, mixed>> $fields */
    public function setFields(array $fields): self { $this->fields = $fields; return $this; }

    public function getTemplateFilename(): ?string { return $this->templateFilename; }
    public function setTemplateFilename(?string $templateFilename): self { $this->templateFilename = $templateFilename; return $this; }

    public function getTemplateSha256(): ?string { return $this->templateSha256; }
    public function setTemplateSha256(?string $templateSha256): self { $this->templateSha256 = $templateSha256; return $this; }

    public function includeTemplateOnPrint(): bool { return $this->includeTemplateOnPrint; }
    public function setIncludeTemplateOnPrint(bool $include): self { $this->includeTemplateOnPrint = $include; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getScope(): string { return $this->scope; }
    public function setScope(string $scope): self { $this->scope = $scope; return $this; }

    public function isGlobalRequested(): bool { return $this->globalRequested; }
    public function setGlobalRequested(bool $globalRequested): self { $this->globalRequested = $globalRequested; return $this; }

    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $createdByUserId): self { $this->createdByUserId = $createdByUserId; return $this; }

    public function getReviewedByUserId(): ?string { return $this->reviewedByUserId; }
    public function setReviewedByUserId(?string $reviewedByUserId): self { $this->reviewedByUserId = $reviewedByUserId; return $this; }

    public function getReviewedAt(): ?\DateTime { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTime $reviewedAt): self { $this->reviewedAt = $reviewedAt; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
