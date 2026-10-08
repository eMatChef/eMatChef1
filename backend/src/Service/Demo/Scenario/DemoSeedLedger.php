<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use App\Entity\DemoSeedRecord;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ownership-Ledger: (Szenario, Seed-Schlüssel) → Entity. Seed-Schlüssel beginnen mit «<szenario>:», damit
 * Szenarien einander nie überschreiben. Nur anlegen und lesen; Löschen gehört in den Reset (Phase 3).
 */
class DemoSeedLedger
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public static function assertSeedKey(string $scenarioKey, string $seedKey): void
    {
        if (!str_starts_with($seedKey, $scenarioKey . ':') || \strlen($seedKey) <= \strlen($scenarioKey) + 1 || !preg_match('/^[a-z0-9][a-z0-9:_.\-]{0,189}$/', $seedKey)) {
            throw new DemoScenarioException(sprintf('Ungültiger Seed-Schlüssel «%s» für Szenario «%s».', $seedKey, $scenarioKey));
        }
    }

    public function find(string $scenarioKey, string $seedKey): ?DemoSeedRecord
    {
        self::assertSeedKey($scenarioKey, $seedKey);

        return $this->entityManager->getRepository(DemoSeedRecord::class)
            ->findOneBy(['scenarioKey' => $scenarioKey, 'seedKey' => $seedKey]);
    }

    /** Legt den Eintrag an. Existiert er für eine andere Entity, wird abgebrochen (nie still überschreiben). */
    public function record(string $scenarioKey, string $seedKey, object $entity, string $entityId, ?Department $department): DemoSeedRecord
    {
        $existing = $this->find($scenarioKey, $seedKey);
        if ($existing !== null) {
            if ($existing->getEntityClass() !== $entity::class || $existing->getEntityId() !== $entityId) {
                throw new DemoScenarioException(sprintf('Seed-Schlüssel «%s» gehört bereits zu einem anderen Datensatz.', $seedKey));
            }

            return $existing;
        }

        $record = new DemoSeedRecord($scenarioKey, $seedKey, $entity::class, $entityId, $department);
        $this->entityManager->persist($record);

        return $record;
    }

    /** @return list<DemoSeedRecord> */
    public function forScenario(string $scenarioKey): array
    {
        return array_values($this->entityManager->getRepository(DemoSeedRecord::class)->findBy(['scenarioKey' => $scenarioKey]));
    }
}
