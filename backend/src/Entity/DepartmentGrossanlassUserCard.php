<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_user_card')]
#[ORM\UniqueConstraint(name: 'uniq_ga_user_card_code', columns: ['public_code'])]
class DepartmentGrossanlassUserCard
{
    #[ORM\Id]
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'may_drive', type: 'boolean', options: ['default' => false])]
    private bool $mayDrive = false;

    /** @var list<string> */
    #[ORM\Column(name: 'drive_classes', type: 'json')]
    private array $driveClasses = [];

    #[ORM\Column(name: 'drive_proof_kind', type: 'string', length: 16, options: ['default' => 'none'])]
    private string $driveProofKind = 'none';

    #[ORM\Column(name: 'drive_verified', type: 'boolean', options: ['default' => false])]
    private bool $driveVerified = false;

    #[ORM\Column(name: 'drive_verified_at', type: 'datetime', nullable: true)]
    private ?\DateTime $driveVerifiedAt = null;

    #[ORM\Column(name: 'drive_verified_by_id', type: 'string', length: 12, nullable: true, options: ['fixed' => true])]
    private ?string $driveVerifiedById = null;

    #[ORM\Column(name: 'drive_document_filename', type: 'string', length: 255, options: ['default' => ''])]
    private string $driveDocumentFilename = '';

    #[ORM\Column(name: 'drive_document_original_name', type: 'string', length: 255, options: ['default' => ''])]
    private string $driveDocumentOriginalName = '';

    #[ORM\Column(name: 'card_printed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $cardPrintedAt = null;

    #[ORM\Column(name: 'public_code', type: 'string', length: 32)]
    private string $publicCode = '';

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function getUserId(): string
    {
        return $this->userId;
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

    public function getMayDrive(): bool
    {
        return $this->mayDrive;
    }

    public function setMayDrive(bool $mayDrive): self
    {
        $this->mayDrive = $mayDrive;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getDriveClasses(): array
    {
        return $this->driveClasses;
    }

    /**
     * @param list<string> $driveClasses
     */
    public function setDriveClasses(array $driveClasses): self
    {
        $this->driveClasses = $driveClasses;

        return $this;
    }

    public function getDriveProofKind(): string
    {
        return $this->driveProofKind;
    }

    public function setDriveProofKind(string $driveProofKind): self
    {
        $this->driveProofKind = $driveProofKind;

        return $this;
    }

    public function isDriveVerified(): bool
    {
        return $this->driveVerified;
    }

    public function setDriveVerified(bool $driveVerified): self
    {
        $this->driveVerified = $driveVerified;

        return $this;
    }

    public function getDriveVerifiedAt(): ?\DateTime
    {
        return $this->driveVerifiedAt;
    }

    public function setDriveVerifiedAt(?\DateTime $driveVerifiedAt): self
    {
        $this->driveVerifiedAt = $driveVerifiedAt;

        return $this;
    }

    public function getDriveVerifiedById(): ?string
    {
        return $this->driveVerifiedById;
    }

    public function setDriveVerifiedById(?string $driveVerifiedById): self
    {
        $this->driveVerifiedById = $driveVerifiedById;

        return $this;
    }

    public function getDriveDocumentFilename(): string
    {
        return $this->driveDocumentFilename;
    }

    public function setDriveDocumentFilename(string $driveDocumentFilename): self
    {
        $this->driveDocumentFilename = $driveDocumentFilename;

        return $this;
    }

    public function getDriveDocumentOriginalName(): string
    {
        return $this->driveDocumentOriginalName;
    }

    public function setDriveDocumentOriginalName(string $driveDocumentOriginalName): self
    {
        $this->driveDocumentOriginalName = $driveDocumentOriginalName;

        return $this;
    }

    public function getCardPrintedAt(): ?\DateTime
    {
        return $this->cardPrintedAt;
    }

    public function setCardPrintedAt(?\DateTime $cardPrintedAt): self
    {
        $this->cardPrintedAt = $cardPrintedAt;

        return $this;
    }

    public function getPublicCode(): string
    {
        return $this->publicCode;
    }

    public function setPublicCode(string $publicCode): self
    {
        $this->publicCode = $publicCode;

        return $this;
    }
}
