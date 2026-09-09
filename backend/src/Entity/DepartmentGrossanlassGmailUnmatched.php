<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_gmail_unmatched')]
#[ORM\UniqueConstraint(name: 'uniq_ga_gmail_unmatched_msg', columns: ['department_id', 'gmail_message_id'])]
#[ORM\Index(name: 'idx_ga_gmail_unmatched_dept', columns: ['department_id'])]
class DepartmentGrossanlassGmailUnmatched
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'gmail_message_id', type: 'string', length: 128)]
    private string $gmailMessageId;

    #[ORM\Column(name: 'gmail_thread_id', type: 'string', length: 128)]
    private string $gmailThreadId = '';

    #[ORM\Column(name: 'from_email', type: 'string', length: 180)]
    private string $fromEmail = '';

    #[ORM\Column(name: 'from_name', type: 'string', length: 255)]
    private string $fromName = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $subject = '';

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(name: 'received_at', type: 'datetime')]
    private \DateTime $receivedAt;

    #[ORM\Column(name: 'discarded_at', type: 'datetime', nullable: true)]
    private ?\DateTime $discardedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $now = new \DateTime();
        $this->receivedAt = $now;
        $this->createdAt = $now;
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

    public function getGmailMessageId(): string
    {
        return $this->gmailMessageId;
    }

    public function setGmailMessageId(string $gmailMessageId): self
    {
        $this->gmailMessageId = $gmailMessageId;

        return $this;
    }

    public function getGmailThreadId(): string
    {
        return $this->gmailThreadId;
    }

    public function setGmailThreadId(string $gmailThreadId): self
    {
        $this->gmailThreadId = $gmailThreadId;

        return $this;
    }

    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(string $fromEmail): self
    {
        $this->fromEmail = $fromEmail;

        return $this;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function setFromName(string $fromName): self
    {
        $this->fromName = $fromName;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getReceivedAt(): \DateTime
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTime $receivedAt): self
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getDiscardedAt(): ?\DateTime
    {
        return $this->discardedAt;
    }

    public function setDiscardedAt(?\DateTime $discardedAt): self
    {
        $this->discardedAt = $discardedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
