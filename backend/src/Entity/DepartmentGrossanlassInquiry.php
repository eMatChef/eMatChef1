<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_inquiry')]
#[ORM\Index(name: 'idx_ga_inquiry_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_inquiry_status', columns: ['status'])]
class DepartmentGrossanlassInquiry
{
    public const STATUS_ENTWURF = 'entwurf';
    public const STATUS_GESENDET = 'gesendet';
    public const STATUS_ANTWORT = 'antwort';
    public const STATUS_ZUSAGE = 'zusage';
    public const STATUS_ABSAGE = 'absage';
    public const STATUS_VORSCHLAG = 'vorschlag';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ENTWURF,
        self::STATUS_GESENDET,
        self::STATUS_ANTWORT,
        self::STATUS_ZUSAGE,
        self::STATUS_ABSAGE,
        self::STATUS_VORSCHLAG,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $place = '';

    /** @var list<string> */
    #[ORM\Column(name: 'category_ids', type: 'json')]
    private array $categoryIds = [];

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ENTWURF;

    #[ORM\Column(name: 'tip_wish_id', type: 'string', length: 12, nullable: true, unique: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $tipWishId = null;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassWishLine::class)]
    #[ORM\JoinColumn(name: 'tip_wish_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityGrossanlassWishLine $tipWish = null;

    #[ORM\Column(name: 'tip_from', type: 'string', length: 255, nullable: true)]
    private ?string $tipFrom = null;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $thread = [];

    #[ORM\Column(name: 'gmail_draft_id', type: 'string', length: 128, nullable: true)]
    private ?string $gmailDraftId = null;

    #[ORM\Column(name: 'gmail_thread_id', type: 'string', length: 128, nullable: true)]
    private ?string $gmailThreadId = null;

    #[ORM\Column(name: 'gmail_message_id', type: 'string', length: 128, nullable: true)]
    private ?string $gmailMessageId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getPlace(): string
    {
        return $this->place;
    }

    public function setPlace(string $place): self
    {
        $this->place = $place;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    /**
     * @param list<string> $categoryIds
     */
    public function setCategoryIds(array $categoryIds): self
    {
        $this->categoryIds = array_values($categoryIds);

        return $this;
    }

    public function isReadyForMail(): bool
    {
        return $this->email !== '' && $this->categoryIds !== [];
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

    public function getTipWishId(): ?string
    {
        return $this->tipWishId;
    }

    public function getTipWish(): ?ActivityGrossanlassWishLine
    {
        return $this->tipWish;
    }

    public function setTipWish(?ActivityGrossanlassWishLine $tipWish): self
    {
        $this->tipWish = $tipWish;
        $this->tipWishId = $tipWish?->getId();

        return $this;
    }

    public function getTipFrom(): ?string
    {
        return $this->tipFrom;
    }

    public function setTipFrom(?string $tipFrom): self
    {
        $this->tipFrom = $tipFrom;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getThread(): array
    {
        return $this->thread;
    }

    /**
     * @param array{who: string, text: string, at?: string} $entry
     */
    public function appendThread(array $entry): self
    {
        $entry['at'] = $entry['at'] ?? (new \DateTime())->format(\DateTimeInterface::ATOM);
        $this->thread[] = $entry;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getGmailDraftId(): ?string
    {
        return $this->gmailDraftId;
    }

    public function setGmailDraftId(?string $gmailDraftId): self
    {
        $this->gmailDraftId = $gmailDraftId;

        return $this;
    }

    public function getGmailThreadId(): ?string
    {
        return $this->gmailThreadId;
    }

    public function setGmailThreadId(?string $gmailThreadId): self
    {
        $this->gmailThreadId = $gmailThreadId;

        return $this;
    }

    public function getGmailMessageId(): ?string
    {
        return $this->gmailMessageId;
    }

    public function setGmailMessageId(?string $gmailMessageId): self
    {
        $this->gmailMessageId = $gmailMessageId;

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
}
