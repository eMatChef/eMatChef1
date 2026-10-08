<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Entity\DemoSeedRecord;

/**
 * Arbeitsbereich eines Szenarios. Erzwingt die Isolation: das Department muss genau die Identität
 * des Szenarios tragen und `demo_mode` haben; Ledger-Zugriffe sind auf den Szenario-Schlüssel begrenzt.
 */
final class SeedContext
{
    private function __construct(
        private string $scenarioKey,
        private ?Department $department,
        private DemoSeedLedger $ledger,
        private bool $dryRun,
    ) {
    }

    public static function create(DemoScenarioInterface $scenario, ?Department $department, DemoSeedLedger $ledger, bool $dryRun = false): self
    {
        if ($department !== null) {
            if ($department->getDemoScenarioKey() !== $scenario->key()) {
                throw new DemoScenarioException(sprintf('Department «%s» gehört nicht zum Szenario «%s».', $department->getName(), $scenario->key()));
            }
            if (!$department->isDemoMode()) {
                throw new DemoScenarioException(sprintf('Department «%s» ist kein Demo-Department.', $department->getName()));
            }
        }

        return new self($scenario->key(), $department, $ledger, $dryRun);
    }

    /**
     * Kontext für gemeinsam genutzte Demo-Benutzer. Sie gehören keinem Szenario (und keinem Department), dürfen in
     * mehreren Demo-Departments Mitglied sein und überleben jeden Szenario-Reset.
     */
    public function sharedUsers(): self
    {
        return new self(DemoSeedLedger::SHARED_USERS, null, $this->ledger, $this->dryRun);
    }

    public function scenarioKey(): string
    {
        return $this->scenarioKey;
    }

    public function department(): ?Department
    {
        return $this->department;
    }

    public function requireDepartment(): Department
    {
        return $this->department ?? throw new DemoScenarioException(sprintf('Szenario «%s» hat noch kein Department.', $this->scenarioKey));
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /** Gleicher Kontext nach Anlage des Szenario-Departments (Schlüssel muss bereits gesetzt sein). */
    public function withDepartment(Department $department): self
    {
        if ($department->getDemoScenarioKey() !== $this->scenarioKey || !$department->isDemoMode()) {
            throw new DemoScenarioException(sprintf('Department «%s» gehört nicht zum Szenario «%s».', $department->getName(), $this->scenarioKey));
        }

        return new self($this->scenarioKey, $department, $this->ledger, $this->dryRun);
    }

    /** Ledger-Eintrag ohne Department-Bezug (Organisation, Benutzer: überleben einen Department-Reset). */
    public function recordGlobal(string $seedKey, object $entity, string $entityId, ?string $managedHash = null, ?string $catalogVersion = null): DemoSeedRecord
    {
        if ($this->dryRun) {
            throw new DemoScenarioException('Dry-Run: es wird nichts geschrieben.');
        }

        return $this->ledger->record($this->scenarioKey, $seedKey, $entity, $entityId, null, $managedHash, $catalogVersion);
    }

    /** @return list<DemoSeedRecord> */
    public function records(): array
    {
        return $this->ledger->forScenario($this->scenarioKey);
    }

    public function findRecord(string $seedKey): ?DemoSeedRecord
    {
        return $this->ledger->find($this->scenarioKey, $seedKey);
    }

    public function record(string $seedKey, object $entity, string $entityId, ?string $managedHash = null, ?string $catalogVersion = null): DemoSeedRecord
    {
        if ($this->dryRun) {
            throw new DemoScenarioException('Dry-Run: es wird nichts geschrieben.');
        }

        return $this->ledger->record($this->scenarioKey, $seedKey, $entity, $entityId, $this->requireDepartment(), $managedHash, $catalogVersion);
    }

    public function recordCount(): int
    {
        return \count($this->ledger->forScenario($this->scenarioKey));
    }

    /** @return list<DemoSeedRecord> Ledger-Einträge dieses Szenarios, die auf ein anderes (oder kein) Department zeigen */
    public function foreignLedgerRecords(): array
    {
        $own = $this->department?->getId();

        return array_values(array_filter(
            $this->ledger->forScenario($this->scenarioKey),
            static fn (DemoSeedRecord $r): bool => $r->getDepartment() !== null && $r->getDepartment()->getId() !== $own,
        ));
    }
}
