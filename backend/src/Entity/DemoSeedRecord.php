<?php

namespace App\Entity;

use App\Util\IdGenerator;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ownership-Ledger der Demo-Seeds: welcher Datensatz gehört zu welchem Szenario und Seed-Schlüssel.
 * Wird nur von Seeds geschrieben; die Anwendung liest ihn nicht. Zuordnung nie über Namen, E-Mails oder Freitext.
 */
#[ORM\Entity]
#[ORM\Table(name: 'demo_seed_record')]
#[ORM\UniqueConstraint(name: 'uniq_demo_seed_record_key', columns: ['scenario_key', 'seed_key'])]
#[ORM\Index(name: 'idx_demo_seed_record_department', columns: ['department_id'])]
class DemoSeedRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(name: 'scenario_key', type: 'string', length: 40)]
    private string $scenarioKey;

    #[ORM\Column(name: 'seed_key', type: 'string', length: 190)]
    private string $seedKey;

    #[ORM\Column(name: 'entity_class', type: 'string', length: 190)]
    private string $entityClass;

    #[ORM\Column(name: 'entity_id', type: 'string', length: 64)]
    private string $entityId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Department $department;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(string $scenarioKey, string $seedKey, string $entityClass, string $entityId, ?Department $department)
    {
        $this->id = IdGenerator::generate();
        $this->scenarioKey = $scenarioKey;
        $this->seedKey = $seedKey;
        $this->entityClass = $entityClass;
        $this->entityId = $entityId;
        $this->department = $department;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getScenarioKey(): string
    {
        return $this->scenarioKey;
    }

    public function getSeedKey(): string
    {
        return $this->seedKey;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }
}
