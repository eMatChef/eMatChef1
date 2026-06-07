<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Zentrale Inbox-Nachrichten (User-Mail, Aktivitäts-Meldungen für MW/User).
 * Aktivitätsbezogene Einträge werden bei Abschluss/Storno der Aktivität gelöscht.
 */
#[ORM\Entity]
#[ORM\Table(name: 'inbox_message')]
#[ORM\Index(name: 'idx_inbox_dept_recipient_unread', columns: ['department_id', 'recipient_user_id', 'read_at'])]
#[ORM\Index(name: 'idx_inbox_dept_sender', columns: ['department_id', 'sender_user_id', 'category'])]
#[ORM\Index(name: 'idx_inbox_dept_mw_unread', columns: ['department_id', 'recipient_scope', 'read_at'])]
#[ORM\Index(name: 'idx_inbox_activity', columns: ['activity_id'])]
class InboxMessage
{
    public const CATEGORY_USER_MESSAGE = 'user_message';

    public const CATEGORY_ACTIVITY_MW = 'activity_mw';

    public const CATEGORY_ACTIVITY_USER = 'activity_user';

    public const CATEGORY_QR_FOUND = 'qr_found';

    public const CATEGORY_DEPARTMENT_INVITE = 'department_invite';

    public const CATEGORY_ACTIVITY_DEPT_INVITE = 'activity_department_invite';

    public const CATEGORY_ACCOUNTING_FOLLOWUP = 'accounting_followup';

    public const CATEGORY_WORKSHOP_ORDER_REMINDER = 'workshop_order_reminder';

    public const CATEGORY_INVITE_ACCEPTED = 'invite_accepted';

    /** Kategorien, die bei completed/cancelled der Aktivität entfernt werden. */
    public const CATEGORIES_PURGE_ON_ACTIVITY_TERMINAL = [
        self::CATEGORY_ACTIVITY_MW,
        self::CATEGORY_ACTIVITY_USER,
        self::CATEGORY_ACTIVITY_DEPT_INVITE,
    ];

    public const WORKFLOW_PENDING = 'pending';

    public const WORKFLOW_OPEN = 'open';

    public const WORKFLOW_IN_PROGRESS = 'in_progress';

    public const WORKFLOW_DONE = 'done';

    public const WORKFLOW_ACCEPTED = 'accepted';

    public const WORKFLOW_DECLINED = 'declined';

    public const WORKFLOW_RECORDED = 'recorded';

    public const RECIPIENT_USER = 'user';

    public const RECIPIENT_DEPARTMENT_MW = 'department_mw';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 32)]
    private string $category;

    #[ORM\Column(type: 'string', length: 64)]
    private string $type;

    #[ORM\Column(name: 'recipient_scope', type: 'string', length: 20)]
    private string $recipientScope;

    #[ORM\Column(name: 'recipient_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $recipientUserId = null;

    #[ORM\Column(name: 'sender_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $senderUserId = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $activityId = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    /** Denormalisierte Anzeige-Felder (Namen, Avatare, Aktivitäts-Metadaten). */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(name: 'workflow_status', type: 'string', length: 20, nullable: true)]
    private ?string $workflowStatus = null;

    #[ORM\Column(name: 'source_ref_id', type: 'string', length: 32, nullable: true)]
    private ?string $sourceRefId = null;

    #[ORM\Column(name: 'read_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(name: 'read_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $readByUserId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

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

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getDepartmentId(): string
    {
        return $this->department->getId();
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getRecipientScope(): string
    {
        return $this->recipientScope;
    }

    public function setRecipientScope(string $recipientScope): self
    {
        $this->recipientScope = $recipientScope;

        return $this;
    }

    public function getRecipientUserId(): ?string
    {
        return $this->recipientUserId;
    }

    public function setRecipientUserId(?string $recipientUserId): self
    {
        $this->recipientUserId = $recipientUserId;

        return $this;
    }

    public function getSenderUserId(): ?string
    {
        return $this->senderUserId;
    }

    public function setSenderUserId(?string $senderUserId): self
    {
        $this->senderUserId = $senderUserId;

        return $this;
    }

    public function getActivityId(): ?string
    {
        return $this->activityId;
    }

    public function setActivityId(?string $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): self
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
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

    public function getWorkflowStatus(): ?string
    {
        return $this->workflowStatus;
    }

    public function setWorkflowStatus(?string $workflowStatus): self
    {
        $this->workflowStatus = $workflowStatus;

        return $this;
    }

    public function getSourceRefId(): ?string
    {
        return $this->sourceRefId;
    }

    public function setSourceRefId(?string $sourceRefId): self
    {
        $this->sourceRefId = $sourceRefId;

        return $this;
    }

    public function getReadByUserId(): ?string
    {
        return $this->readByUserId;
    }

    public function setReadByUserId(?string $readByUserId): self
    {
        $this->readByUserId = $readByUserId;

        return $this;
    }
}
