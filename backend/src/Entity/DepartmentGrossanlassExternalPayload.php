<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Originalantwort einer externen Quelle (Rohtext, unverändert) mit Zeitstempel. Identische Antworten
 * (gleicher SHA-256) werden nicht doppelt abgelegt, sondern nur als erneut gesehen gezählt.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_ext_payload')]
class DepartmentGrossanlassExternalPayload
{
    public const ORIGIN_FETCH = 'fetch';
    public const ORIGIN_IMPORT = 'import';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'source_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $sourceId;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(type: 'string', length: 8)]
    private string $origin;

    #[ORM\Column(name: 'http_status', type: 'integer', nullable: true)]
    private ?int $httpStatus = null;

    #[ORM\Column(name: 'body_hash', type: 'string', length: 64, columnDefinition: 'CHAR(64) NOT NULL')]
    private string $bodyHash;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(name: 'byte_size', type: 'integer')]
    private int $byteSize;

    #[ORM\Column(name: 'fetched_at', type: 'datetime')]
    private \DateTime $fetchedAt;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime')]
    private \DateTime $lastSeenAt;

    #[ORM\Column(name: 'seen_count', type: 'integer', options: ['default' => 1])]
    private int $seenCount = 1;

    #[ORM\Column(name: 'fetched_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $fetchedByUserId = null;

    public function __construct(string $id, string $sourceId, string $departmentId, string $origin, string $body, ?int $httpStatus, ?string $userId)
    {
        $this->id = $id;
        $this->sourceId = $sourceId;
        $this->departmentId = $departmentId;
        $this->origin = $origin;
        $this->body = $body;
        $this->bodyHash = hash('sha256', $body);
        $this->byteSize = strlen($body);
        $this->httpStatus = $httpStatus;
        $this->fetchedByUserId = $userId;
        $this->fetchedAt = new \DateTime();
        $this->lastSeenAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getSourceId(): string { return $this->sourceId; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getOrigin(): string { return $this->origin; }
    public function getHttpStatus(): ?int { return $this->httpStatus; }
    public function getBodyHash(): string { return $this->bodyHash; }
    public function getBody(): string { return $this->body; }
    public function getByteSize(): int { return $this->byteSize; }
    public function getFetchedAt(): \DateTime { return $this->fetchedAt; }
    public function getLastSeenAt(): \DateTime { return $this->lastSeenAt; }
    public function getSeenCount(): int { return $this->seenCount; }

    public function seenAgain(): self
    {
        ++$this->seenCount;
        $this->lastSeenAt = new \DateTime();

        return $this;
    }
}
