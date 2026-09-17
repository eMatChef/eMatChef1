<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_pack_line')]
#[ORM\Index(name: 'idx_ga_pack_line_pack', columns: ['pack_id'])]
class DepartmentGrossanlassPackLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'pack_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $packId;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassPack::class)]
    #[ORM\JoinColumn(name: 'pack_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DepartmentGrossanlassPack $pack;

    #[ORM\Column(name: 'commitment_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $commitmentId = null;

    #[ORM\Column(name: 'wish_line_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $wishLineId = null;

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    private string $label = '';

    #[ORM\Column(name: 'qty_needed', type: 'integer', options: ['default' => 1])]
    private int $qtyNeeded = 1;

    #[ORM\Column(name: 'qty_packed', type: 'integer', options: ['default' => 0])]
    private int $qtyPacked = 0;

    #[ORM\Column(name: 'valid_from', type: 'datetime', nullable: true)]
    private ?\DateTime $validFrom = null;

    #[ORM\Column(name: 'valid_to', type: 'datetime', nullable: true)]
    private ?\DateTime $validTo = null;

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getPackId(): string { return $this->packId; }
    public function getPack(): DepartmentGrossanlassPack { return $this->pack; }
    public function setPack(DepartmentGrossanlassPack $pack): self
    {
        $this->pack = $pack;
        $this->packId = $pack->getId();
        return $this;
    }

    public function getCommitmentId(): ?string { return $this->commitmentId; }
    public function setCommitmentId(?string $commitmentId): self { $this->commitmentId = $commitmentId ?: null; return $this; }

    public function getWishLineId(): ?string { return $this->wishLineId; }
    public function setWishLineId(?string $wishLineId): self { $this->wishLineId = $wishLineId ?: null; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getQtyNeeded(): int { return $this->qtyNeeded; }
    public function setQtyNeeded(int $qtyNeeded): self { $this->qtyNeeded = max(1, $qtyNeeded); return $this; }

    public function getQtyPacked(): int { return $this->qtyPacked; }
    public function setQtyPacked(int $qtyPacked): self { $this->qtyPacked = max(0, $qtyPacked); return $this; }

    public function getValidFrom(): ?\DateTime { return $this->validFrom; }
    public function setValidFrom(?\DateTime $validFrom): self { $this->validFrom = $validFrom; return $this; }

    public function getValidTo(): ?\DateTime { return $this->validTo; }
    public function setValidTo(?\DateTime $validTo): self { $this->validTo = $validTo; return $this; }

    public function isIncomplete(): bool
    {
        return $this->qtyPacked > 0 && $this->qtyPacked < $this->qtyNeeded;
    }

    public function isEmpty(): bool
    {
        return $this->qtyPacked <= 0;
    }
}
