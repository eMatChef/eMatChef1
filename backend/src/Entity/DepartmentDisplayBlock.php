<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Eigener Baustein (Widget oder Widget-Gruppe) für Infoscreen-Layouts eines Departments bzw. Grossanlasses.
 * Der Baustein ist eine Vorlage: Beim Einfügen entsteht immer eine unabhängige Kopie im Layout, spätere
 * Änderungen oder das Löschen des Bausteins berühren bestehende Infoscreens nicht.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_display_block')]
#[ORM\Index(name: 'idx_display_block_dept', columns: ['department_id'])]
class DepartmentDisplayBlock
{
    public const KIND_WIDGET = 'widget';
    public const KIND_GROUP = 'group';
    public const KIND_ROTATION = 'rotation';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'string', length: 16)]
    private string $kind;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $content;

    #[ORM\Column(name: 'default_w', type: 'integer')]
    private int $defaultW;

    #[ORM\Column(name: 'default_h', type: 'integer')]
    private int $defaultH;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    /**
     * @param array<string, mixed> $content
     */
    public function __construct(string $id, string $departmentId, string $name, string $kind, array $content, int $defaultW, int $defaultH, ?string $createdByUserId)
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->name = $name;
        $this->kind = $kind;
        $this->content = $content;
        $this->defaultW = $defaultW;
        $this->defaultH = $defaultH;
        $this->createdByUserId = $createdByUserId;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTime();
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /** @return array<string, mixed> */
    public function getContent(): array
    {
        return $this->content;
    }

    public function getDefaultW(): int
    {
        return $this->defaultW;
    }

    public function getDefaultH(): int
    {
        return $this->defaultH;
    }

    public function getCreatedByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
}
