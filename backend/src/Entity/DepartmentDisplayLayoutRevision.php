<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Unveränderliche veröffentlichte Layout-Revision eines Infoscreens (inklusive aller Varianten und Seiten).
 * Es gibt bewusst keine Setter: eine Revision wird nur angelegt, nie geändert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_display_layout_revision')]
#[ORM\UniqueConstraint(name: 'uniq_display_layout_revision', columns: ['screen_id', 'number'])]
class DepartmentDisplayLayoutRevision
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'screen_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $screenId;

    #[ORM\Column(type: 'integer')]
    private int $number;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $layout;

    #[ORM\Column(name: 'published_at', type: 'datetime')]
    private \DateTimeInterface $publishedAt;

    #[ORM\Column(name: 'published_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $publishedByUserId;

    /** Nummer der Revision, aus der diese Veröffentlichung wiederhergestellt wurde. */
    #[ORM\Column(name: 'restored_from_number', type: 'integer', nullable: true)]
    private ?int $restoredFromNumber;

    /**
     * @param array<string, mixed> $layout
     */
    public function __construct(string $id, string $screenId, int $number, array $layout, ?string $publishedByUserId, ?int $restoredFromNumber = null)
    {
        $this->id = $id;
        $this->screenId = $screenId;
        $this->number = $number;
        $this->layout = $layout;
        $this->publishedAt = new \DateTime();
        $this->publishedByUserId = $publishedByUserId;
        $this->restoredFromNumber = $restoredFromNumber;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    /** @return array<string, mixed> */
    public function getLayout(): array
    {
        return $this->layout;
    }

    public function getPublishedAt(): \DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function getPublishedByUserId(): ?string
    {
        return $this->publishedByUserId;
    }

    public function getRestoredFromNumber(): ?int
    {
        return $this->restoredFromNumber;
    }
}
