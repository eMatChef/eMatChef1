<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'print_device_model')]
#[ORM\UniqueConstraint(name: 'uniq_print_device_model_key', columns: ['catalog_key'])]
class PrintDeviceModel
{
    public const FAMILY_BROTHER_QL = 'brother_ql';
    public const FAMILY_OFFICE_A4 = 'office_a4';
    public const FAMILY_TSC_DESKTOP = 'tsc_desktop';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_ORGANISATION = 'organisation';

    /** @var list<string> */
    public const FAMILIES = [self::FAMILY_BROTHER_QL, self::FAMILY_OFFICE_A4, self::FAMILY_TSC_DESKTOP];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'catalog_key', type: 'string', length: 64)]
    private string $catalogKey;

    #[ORM\Column(type: 'string', length: 32)]
    private string $family;

    #[ORM\Column(type: 'string', length: 80)]
    private string $brand;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    /** @var list<string> */
    #[ORM\Column(name: 'compatible_media_keys', type: 'json')]
    private array $compatibleMediaKeys = [];

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PUBLISHED;

    #[ORM\Column(type: 'string', length: 20)]
    private string $scope = self::SCOPE_GLOBAL;

    #[ORM\Column(name: 'organisation_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $organisationId = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'reviewed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $reviewedByUserId = null;

    #[ORM\Column(name: 'global_requested', type: 'boolean', options: ['default' => false])]
    private bool $globalRequested = false;

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

    public function getCatalogKey(): string { return $this->catalogKey; }
    public function setCatalogKey(string $catalogKey): self { $this->catalogKey = $catalogKey; return $this; }

    public function getFamily(): string { return $this->family; }
    public function setFamily(string $family): self { $this->family = $family; return $this; }

    public function getBrand(): string { return $this->brand; }
    public function setBrand(string $brand): self { $this->brand = $brand; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return list<string> */
    public function getCompatibleMediaKeys(): array { return $this->compatibleMediaKeys; }

    /** @param list<string> $keys */
    public function setCompatibleMediaKeys(array $keys): self { $this->compatibleMediaKeys = $keys; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getScope(): string { return $this->scope; }
    public function setScope(string $scope): self { $this->scope = $scope; return $this; }

    public function getOrganisationId(): ?string { return $this->organisationId; }
    public function setOrganisationId(?string $organisationId): self { $this->organisationId = $organisationId; return $this; }

    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $createdByUserId): self { $this->createdByUserId = $createdByUserId; return $this; }

    public function getReviewedByUserId(): ?string { return $this->reviewedByUserId; }
    public function setReviewedByUserId(?string $reviewedByUserId): self { $this->reviewedByUserId = $reviewedByUserId; return $this; }

    public function isGlobalRequested(): bool { return $this->globalRequested; }
    public function setGlobalRequested(bool $globalRequested): self { $this->globalRequested = $globalRequested; return $this; }

    public function getReviewedAt(): ?\DateTime { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTime $reviewedAt): self { $this->reviewedAt = $reviewedAt; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
