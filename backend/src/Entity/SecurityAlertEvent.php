<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'security_alert_event')]
#[ORM\Index(name: 'idx_security_alert_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_security_alert_type_created', columns: ['alert_type', 'created_at'])]
class SecurityAlertEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'alert_type', type: 'string', length: 64)]
    private string $alertType;

    #[ORM\Column(type: 'string', length: 16)]
    private string $severity = 'warning';

    #[ORM\Column(name: 'source_key', type: 'string', length: 190)]
    private string $sourceKey;

    #[ORM\Column(name: 'window_minutes', type: 'integer')]
    private int $windowMinutes = 15;

    #[ORM\Column(name: 'event_count', type: 'integer')]
    private int $eventCount = 0;

    #[ORM\Column(name: 'ip_address', type: 'string', length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'identifier', type: 'string', length: 190, nullable: true)]
    private ?string $identifier = null;

    #[ORM\Column(type: 'string', length: 190)]
    private string $path;

    #[ORM\Column(name: 'status_code', type: 'integer', nullable: true)]
    private ?int $statusCode = null;

    #[ORM\Column(type: 'json')]
    private array $context = [];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function setAlertType(string $alertType): self
    {
        $this->alertType = $alertType;
        return $this;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function setSourceKey(string $sourceKey): self
    {
        $this->sourceKey = $sourceKey;
        return $this;
    }

    public function setWindowMinutes(int $windowMinutes): self
    {
        $this->windowMinutes = $windowMinutes;
        return $this;
    }

    public function setEventCount(int $eventCount): self
    {
        $this->eventCount = $eventCount;
        return $this;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function setIdentifier(?string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function setStatusCode(?int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }
}

