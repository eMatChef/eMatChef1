<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null; // Nullable für automatische ID-Generierung

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'active'])]
    private string $state = 'active';

    #[ORM\Column(name: 'password', type: 'string')]
    private string $password;

    #[ORM\Column(name: 'profile_id', type: 'string', length: 12, options: ['fixed' => true])]
    private string $profileId;

    #[ORM\OneToOne(targetEntity: Profile::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false)]
    private ?Profile $profile = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(name: 'email_verified', type: 'boolean', options: ['default' => false])]
    private bool $emailVerified = false;

    #[ORM\Column(name: 'email_verification_token', type: 'string', length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(name: 'email_verification_expires_at', type: 'datetime', nullable: true)]
    private ?\DateTime $emailVerificationExpiresAt = null;

    #[ORM\Column(name: 'pending_email', type: 'string', length: 180, nullable: true)]
    private ?string $pendingEmail = null;

    #[ORM\Column(name: 'password_reset_code_hash', type: 'string', length: 64, nullable: true)]
    private ?string $passwordResetCodeHash = null;

    #[ORM\Column(name: 'password_reset_expires_at', type: 'datetime', nullable: true)]
    private ?\DateTime $passwordResetExpiresAt = null;

    #[ORM\Column(name: 'password_reset_last_requested_at', type: 'datetime', nullable: true)]
    private ?\DateTime $passwordResetLastRequestedAt = null;

    #[ORM\Column(name: 'password_reset_window_started_at', type: 'datetime', nullable: true)]
    private ?\DateTime $passwordResetWindowStartedAt = null;

    #[ORM\Column(name: 'password_reset_request_count', type: 'smallint', options: ['default' => 0])]
    private int $passwordResetRequestCount = 0;

    #[ORM\Column(name: 'password_reset_attempt_count', type: 'smallint', options: ['default' => 0])]
    private int $passwordResetAttemptCount = 0;

    #[ORM\Column(name: 'password_reset_locked_until', type: 'datetime', nullable: true)]
    private ?\DateTime $passwordResetLockedUntil = null;

    #[ORM\Column(name: 'created_by', type: 'string', length: 12, nullable: true, options: ['fixed' => true])]
    private ?string $createdById = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Membership::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: SupplierMembership::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $supplierMemberships;

    /**
     * Zuletzt in der App gewählte Abteilung (Login-Vorschlag / Session-Wiederherstellung).
     * Wird bei Abteilungswechsel gesetzt; ungültige oder gelöschte Abteilungen → FK SET NULL.
     */
    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'last_used_department_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Department $lastUsedDepartment = null;

    /**
     * Zuletzt gewählte Lieferanten-Firma (Supplier-Kontextwechsel, Paket 2+).
     */
    #[ORM\ManyToOne(targetEntity: SupplierCompany::class)]
    #[ORM\JoinColumn(name: 'last_used_supplier_company_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?SupplierCompany $lastUsedSupplierCompany = null;

    #[ORM\Column(name: 'google_id', type: 'string', length: 64, nullable: true, unique: true)]
    private ?string $googleId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->memberships = new ArrayCollection();
        $this->supplierMemberships = new ArrayCollection();
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

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function setProfileId(string $profileId): self
    {
        $this->profileId = $profileId;
        return $this;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): self
    {
        $this->profile = $profile;
        if ($profile) {
            $this->profileId = $profile->getId();
        }
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

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): self
    {
        $this->emailVerified = $emailVerified;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): self
    {
        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }

    public function getEmailVerificationExpiresAt(): ?\DateTime
    {
        return $this->emailVerificationExpiresAt;
    }

    public function setEmailVerificationExpiresAt(?\DateTime $emailVerificationExpiresAt): self
    {
        $this->emailVerificationExpiresAt = $emailVerificationExpiresAt;
        return $this;
    }

    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function setPendingEmail(?string $pendingEmail): self
    {
        $this->pendingEmail = $pendingEmail;
        return $this;
    }

    public function getPasswordResetCodeHash(): ?string
    {
        return $this->passwordResetCodeHash;
    }

    public function setPasswordResetCodeHash(?string $passwordResetCodeHash): self
    {
        $this->passwordResetCodeHash = $passwordResetCodeHash;
        return $this;
    }

    public function getPasswordResetExpiresAt(): ?\DateTime
    {
        return $this->passwordResetExpiresAt;
    }

    public function setPasswordResetExpiresAt(?\DateTime $passwordResetExpiresAt): self
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;
        return $this;
    }

    public function getPasswordResetLastRequestedAt(): ?\DateTime
    {
        return $this->passwordResetLastRequestedAt;
    }

    public function setPasswordResetLastRequestedAt(?\DateTime $passwordResetLastRequestedAt): self
    {
        $this->passwordResetLastRequestedAt = $passwordResetLastRequestedAt;
        return $this;
    }

    public function getPasswordResetWindowStartedAt(): ?\DateTime
    {
        return $this->passwordResetWindowStartedAt;
    }

    public function setPasswordResetWindowStartedAt(?\DateTime $passwordResetWindowStartedAt): self
    {
        $this->passwordResetWindowStartedAt = $passwordResetWindowStartedAt;
        return $this;
    }

    public function getPasswordResetRequestCount(): int
    {
        return $this->passwordResetRequestCount;
    }

    public function setPasswordResetRequestCount(int $passwordResetRequestCount): self
    {
        $this->passwordResetRequestCount = $passwordResetRequestCount;
        return $this;
    }

    public function getPasswordResetAttemptCount(): int
    {
        return $this->passwordResetAttemptCount;
    }

    public function setPasswordResetAttemptCount(int $passwordResetAttemptCount): self
    {
        $this->passwordResetAttemptCount = $passwordResetAttemptCount;
        return $this;
    }

    public function getPasswordResetLockedUntil(): ?\DateTime
    {
        return $this->passwordResetLockedUntil;
    }

    public function setPasswordResetLockedUntil(?\DateTime $passwordResetLockedUntil): self
    {
        $this->passwordResetLockedUntil = $passwordResetLockedUntil;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->profileId;
    }

    /**
     * System-Superadmin (nur Profil-Rolle) — keine Abteilungs-/Gruppen-Rollenvergabe über die UI.
     */
    public function hasSuperAdminProfile(): bool
    {
        return $this->profile !== null && $this->profile->hasSuperAdminRole();
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        // Globale Rollen kommen aus dem Profil (z.B. ROLE_SUPERADMIN/ORG/SUB).
        if ($this->profile) {
            $roles = array_merge($roles, $this->profile->getRoles());
        }
        
        // Department-Rollen aus Memberships sammeln (nur department-lokale Rollen).
        foreach ($this->memberships as $membership) {
            $departmentRole = $membership->getRole();
            
            // Mapping zu Symfony Roles (Rollen sind jetzt Abkürzungen)
            $symfonyRole = match($departmentRole) {
                'mw' => 'ROLE_MATWART',
                'cmw' => 'ROLE_CO_MATWART',
                'dc' => 'ROLE_DEPCHEF',
                'komm' => 'ROLE_KOMMUNIKATION',
                'spon' => 'ROLE_SPONSORING',
                'l1' => 'ROLE_LEADER1',
                'l2' => 'ROLE_LEADER2',
                'l3' => 'ROLE_LEADER3',
                'u' => 'ROLE_USER',
                default => null,
            };
            
            if ($symfonyRole) {
                $roles[] = $symfonyRole;
            }
        }

        if ($this->hasActiveSupplierMembership()) {
            $roles[] = 'ROLE_SUPPLIER';
        }
        
        return array_unique($roles);
    }

    public function hasActiveSupplierMembership(): bool
    {
        foreach ($this->supplierMemberships as $membership) {
            if ($membership->getSupplierCompany()->getStatus() === SupplierCompany::STATUS_ACTIVE) {
                return true;
            }
        }

        return false;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(?string $createdById): self
    {
        $this->createdById = $createdById;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        if ($createdBy) {
            $this->createdById = $createdBy->getId();
        } else {
            $this->createdById = null;
        }
        return $this;
    }

    /**
     * @return Collection<int, Membership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(Membership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setUser($this);
        }
        return $this;
    }

    public function removeMembership(Membership $membership): self
    {
        if ($this->memberships->removeElement($membership)) {
            // owning side is non-nullable; removal from collection is enough
        }
        return $this;
    }

    /**
     * @return Collection<int, SupplierMembership>
     */
    public function getSupplierMemberships(): Collection
    {
        return $this->supplierMemberships;
    }

    public function addSupplierMembership(SupplierMembership $membership): self
    {
        if (!$this->supplierMemberships->contains($membership)) {
            $this->supplierMemberships->add($membership);
            $membership->setUser($this);
        }
        return $this;
    }

    public function removeSupplierMembership(SupplierMembership $membership): self
    {
        $this->supplierMemberships->removeElement($membership);
        return $this;
    }

    public function getLastUsedDepartment(): ?Department
    {
        return $this->lastUsedDepartment;
    }

    public function setLastUsedDepartment(?Department $lastUsedDepartment): self
    {
        $this->lastUsedDepartment = $lastUsedDepartment;
        return $this;
    }

    public function getLastUsedDepartmentId(): ?string
    {
        if ($this->lastUsedDepartment === null) {
            return null;
        }
        try {
            return $this->lastUsedDepartment->getId();
        } catch (EntityNotFoundException) {
            // FK zeigt auf gelöschtes Department (z. B. nach manuellem DB-Eingriff) — kein 500 in API
            return null;
        }
    }

    public function getLastUsedSupplierCompany(): ?SupplierCompany
    {
        return $this->lastUsedSupplierCompany;
    }

    public function setLastUsedSupplierCompany(?SupplierCompany $lastUsedSupplierCompany): self
    {
        $this->lastUsedSupplierCompany = $lastUsedSupplierCompany;
        return $this;
    }

    public function getLastUsedSupplierCompanyId(): ?string
    {
        if ($this->lastUsedSupplierCompany === null) {
            return null;
        }
        try {
            return $this->lastUsedSupplierCompany->getId();
        } catch (EntityNotFoundException) {
            return null;
        }
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;
        return $this;
    }
}
