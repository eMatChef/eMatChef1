<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_config')]
class DepartmentGrossanlassConfig
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const STRUKTUR_OFFEN = 'offen';
    public const STRUKTUR_VERSCHACHTELT = 'verschachtelt';
    public const STRUKTUR_PARALLEL = 'parallel';

    public const GUEST_ACTIVITY_CAMP = 'camp';
    public const GUEST_ACTIVITY_EVENT = 'event';

    #[ORM\Id]
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\OneToOne(targetEntity: Department::class, inversedBy: 'grossanlassConfig')]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'main_activity_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $mainActivityId = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'main_activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $mainActivity = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'published_at', type: 'datetime', nullable: true)]
    private ?\DateTime $publishedAt = null;

    #[ORM\Column(name: 'published_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $publishedByUserId = null;

    #[ORM\Column(name: 'struktur_modus', type: 'string', length: 20, options: ['default' => self::STRUKTUR_OFFEN])]
    private string $strukturModus = self::STRUKTUR_OFFEN;

    #[ORM\Column(name: 'planned_event_start', type: 'datetime')]
    private \DateTime $plannedEventStart;

    #[ORM\Column(name: 'planned_event_end', type: 'datetime', nullable: true)]
    private ?\DateTime $plannedEventEnd = null;

    #[ORM\Column(name: 'location_text', type: 'string', length: 255, options: ['default' => ''])]
    private string $locationText = '';

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private string $notes = '';

    #[ORM\Column(name: 'guest_activity_type', type: 'string', length: 20, options: ['default' => self::GUEST_ACTIVITY_CAMP])]
    private string $guestActivityType = self::GUEST_ACTIVITY_CAMP;

    #[ORM\Column(name: 'has_guest_departments', type: 'boolean', options: ['default' => false])]
    private bool $hasGuestDepartments = false;

    /** @var list<string> */
    #[ORM\Column(name: 'invite_group_ids', type: 'json', options: ['default' => '[]'])]
    private array $inviteGroupIds = [];

    #[ORM\Column(name: 'venue_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $venueAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'venue_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $venueAddress = null;

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

    public function getMainActivityId(): ?string
    {
        return $this->mainActivityId;
    }

    public function getMainActivity(): ?Activity
    {
        return $this->mainActivity;
    }

    public function setMainActivity(?Activity $activity): self
    {
        $this->mainActivity = $activity;
        $this->mainActivityId = $activity?->getId();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTime
    {
        return $this->publishedAt;
    }

    public function getPublishedByUserId(): ?string
    {
        return $this->publishedByUserId;
    }

    public function getStrukturModus(): string
    {
        return $this->strukturModus;
    }

    public function setStrukturModus(string $strukturModus): self
    {
        $this->strukturModus = $strukturModus;

        return $this;
    }

    public function getPlannedEventStart(): \DateTime
    {
        return $this->plannedEventStart;
    }

    public function setPlannedEventStart(\DateTime $plannedEventStart): self
    {
        $this->plannedEventStart = $plannedEventStart;

        return $this;
    }

    public function getPlannedEventEnd(): ?\DateTime
    {
        return $this->plannedEventEnd;
    }

    public function setPlannedEventEnd(?\DateTime $plannedEventEnd): self
    {
        $this->plannedEventEnd = $plannedEventEnd;

        return $this;
    }

    public function getLocationText(): string
    {
        return $this->locationText;
    }

    public function setLocationText(string $locationText): self
    {
        $this->locationText = mb_substr(trim($locationText), 0, 255);

        return $this;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getGuestActivityType(): string
    {
        return $this->guestActivityType;
    }

    public function setGuestActivityType(string $guestActivityType): self
    {
        $this->guestActivityType = $guestActivityType;

        return $this;
    }

    public function hasGuestDepartments(): bool
    {
        return $this->hasGuestDepartments;
    }

    public function setHasGuestDepartments(bool $hasGuestDepartments): self
    {
        $this->hasGuestDepartments = $hasGuestDepartments;

        return $this;
    }

    /** @return list<string> */
    public function getInviteGroupIds(): array
    {
        return array_values(array_filter($this->inviteGroupIds, static fn ($id) => \is_string($id) && $id !== ''));
    }

    /** @param list<string> $inviteGroupIds */
    public function setInviteGroupIds(array $inviteGroupIds): self
    {
        $ids = [];
        foreach ($inviteGroupIds as $id) {
            if (!\is_string($id)) {
                continue;
            }
            $trim = trim($id);
            if ($trim !== '' && !in_array($trim, $ids, true)) {
                $ids[] = $trim;
            }
        }
        $this->inviteGroupIds = $ids;

        return $this;
    }

    public function getVenueAddressId(): ?string
    {
        return $this->venueAddressId;
    }

    public function setVenueAddressId(?string $venueAddressId): self
    {
        $this->venueAddressId = $venueAddressId;

        return $this;
    }

    public function getVenueAddress(): ?Address
    {
        return $this->venueAddress;
    }

    public function setVenueAddress(?Address $venueAddress): self
    {
        $this->venueAddress = $venueAddress;
        $this->venueAddressId = $venueAddress?->getId();

        return $this;
    }

    public function setPublishedAt(?\DateTime $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function setPublishedByUserId(?string $publishedByUserId): self
    {
        $this->publishedByUserId = $publishedByUserId;

        return $this;
    }

    public function markPublished(string $userId): self
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = new \DateTime();
        $this->publishedByUserId = $userId;

        return $this;
    }
}
