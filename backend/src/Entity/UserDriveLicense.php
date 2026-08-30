<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_drive_license')]
class UserDriveLicense
{
    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var list<string> */
    #[ORM\Column(name: 'drive_classes', type: 'json')]
    private array $driveClasses = [];

    #[ORM\Column(name: 'valid_until', type: 'date', nullable: true)]
    private ?\DateTime $validUntil = null;

    #[ORM\Column(name: 'document_filename', type: 'string', length: 255, options: ['default' => ''])]
    private string $documentFilename = '';

    #[ORM\Column(name: 'document_original_name', type: 'string', length: 255, options: ['default' => ''])]
    private string $documentOriginalName = '';

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = $user->getId();

        return $this;
    }

    /** @return list<string> */
    public function getDriveClasses(): array
    {
        return $this->driveClasses;
    }

    /** @param list<string> $driveClasses */
    public function setDriveClasses(array $driveClasses): self
    {
        $this->driveClasses = $driveClasses;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getValidUntil(): ?\DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTime $validUntil): self
    {
        $this->validUntil = $validUntil;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDocumentFilename(): string
    {
        return $this->documentFilename;
    }

    public function setDocumentFilename(string $documentFilename): self
    {
        $this->documentFilename = $documentFilename;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDocumentOriginalName(): string
    {
        return $this->documentOriginalName;
    }

    public function setDocumentOriginalName(string $documentOriginalName): self
    {
        $this->documentOriginalName = $documentOriginalName;

        return $this;
    }
}
