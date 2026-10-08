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
        private DemoScenarioInterface $scenario,
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

        return new self($scenario, $department, $ledger, $dryRun);
    }

    public function scenarioKey(): string
    {
        return $this->scenario->key();
    }

    public function department(): ?Department
    {
        return $this->department;
    }

    public function requireDepartment(): Department
    {
        return $this->department ?? throw new DemoScenarioException(sprintf('Szenario «%s» hat noch kein Department.', $this->scenario->key()));
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function findRecord(string $seedKey): ?DemoSeedRecord
    {
        return $this->ledger->find($this->scenario->key(), $seedKey);
    }

    public function record(string $seedKey, object $entity, string $entityId): DemoSeedRecord
    {
        if ($this->dryRun) {
            throw new DemoScenarioException('Dry-Run: es wird nichts geschrieben.');
        }

        return $this->ledger->record($this->scenario->key(), $seedKey, $entity, $entityId, $this->requireDepartment());
    }

    public function recordCount(): int
    {
        return \count($this->ledger->forScenario($this->scenario->key()));
    }

    /** @return list<DemoSeedRecord> Ledger-Einträge dieses Szenarios, die auf ein anderes (oder kein) Department zeigen */
    public function foreignLedgerRecords(): array
    {
        $own = $this->department?->getId();

        return array_values(array_filter(
            $this->ledger->forScenario($this->scenario->key()),
            static fn (DemoSeedRecord $r): bool => $r->getDepartment() !== null && $r->getDepartment()->getId() !== $own,
        ));
    }
}
