<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Führt Szenario-Schritte aus: sync und reset unter Advisory-Lock und in genau einer Transaktion.
 * verify und status schreiben nie. Reset ist nur für Szenarien mit supportsReset() möglich, behält die
 * Department-Zeile (ID und Schlüssel) und wird sonst zurückgerollt.
 */
class DemoScenarioRunner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DemoScenarioIdentity $identity,
        private DemoSeedLedger $ledger,
    ) {
    }

    public function sync(DemoScenarioInterface $scenario, bool $dryRun = false): SeedResult
    {
        return $this->locked($scenario, function () use ($scenario, $dryRun): SeedResult {
            $context = SeedContext::create($scenario, $this->identity->findDepartment($scenario->key()), $this->ledger, $dryRun);

            return $scenario->sync($context);
        });
    }

    /** @return list<string> */
    public function verify(DemoScenarioInterface $scenario): array
    {
        return $scenario->verify(SeedContext::create($scenario, $this->identity->findDepartment($scenario->key()), $this->ledger, true));
    }

    /**
     * @param (callable(SeedResult): void)|null $beforeCommit wird innerhalb der Transaktion nach dem Reset aufgerufen;
     *                                                        eine Exception rollt alles zurück (z. B. Plan-Abgleich mit der Vorschau)
     */
    public function reset(DemoScenarioInterface $scenario, ?callable $beforeCommit = null): SeedResult
    {
        if (!$scenario->supportsReset()) {
            throw new DemoScenarioException(sprintf('Reset für «%s» ist noch nicht verfügbar.', $scenario->key()));
        }
        $department = $this->identity->findDepartment($scenario->key());
        if ($department === null) {
            throw new DemoScenarioException(sprintf('Szenario «%s» hat kein Department; nichts zu tun.', $scenario->key()));
        }
        $departmentId = (string) $department->getId();

        return $this->locked($scenario, function () use ($scenario, $department, $departmentId, $beforeCommit): SeedResult {
            $sharedBefore = $this->sharedUserIds();
            $result = $scenario->reset(SeedContext::create($scenario, $department, $this->ledger, false));

            $key = $this->entityManager->getConnection()->fetchOne('SELECT demo_scenario_key FROM department WHERE id = ?', [$departmentId]);
            if ($key !== $scenario->key()) {
                throw new DemoScenarioException('Reset hat die Department-Zeile oder deren Szenario-Schlüssel verändert; zurückgerollt.');
            }
            // Gemeinsam genutzte Demo-Benutzer gehören keinem Szenario und müssen einen Reset überleben.
            if ($this->sharedUserIds() !== $sharedBefore) {
                throw new DemoScenarioException('Reset hat gemeinsam genutzte Demo-Benutzer verändert; zurückgerollt.');
            }
            if ($beforeCommit !== null) {
                $beforeCommit($result);
            }

            return $result;
        });
    }

    /**
     * Vorschau eines Resets: gleiche Schritte wie {@see reset()}, aber die Transaktion wird immer zurückgerollt
     * (Dry-Run mit demselben Änderungsumfang). Gibt dem Aufrufer das Ergebnis samt Plan zurück.
     */
    public function previewReset(DemoScenarioInterface $scenario): SeedResult
    {
        if (!$scenario->supportsReset()) {
            throw new DemoScenarioException(sprintf('Reset für «%s» ist noch nicht verfügbar.', $scenario->key()));
        }
        $department = $this->identity->findDepartment($scenario->key());
        if ($department === null) {
            throw new DemoScenarioException(sprintf('Szenario «%s» hat kein Department; nichts zu tun.', $scenario->key()));
        }
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            if (!$connection->fetchOne('SELECT pg_try_advisory_xact_lock(hashtext(?))', ['demo-scenario:' . $scenario->key()])) {
                throw new DemoScenarioException(sprintf('Szenario «%s» wird gerade von einem anderen Lauf bearbeitet.', $scenario->key()));
            }

            return $scenario->reset(SeedContext::create($scenario, $department, $this->ledger, false));
        } finally {
            $connection->rollBack();
            $this->entityManager->clear();
        }
    }

    /** @return list<string> IDs der vorhandenen gemeinsamen Demo-Benutzer (nur existierende Datensätze) */
    private function sharedUserIds(): array
    {
        $ids = [];
        foreach ($this->ledger->sharedUserRecords() as $record) {
            if ($this->entityManager->getConnection()->fetchOne('SELECT 1 FROM "user" WHERE id = ?', [$record->getEntityId()])) {
                $ids[] = $record->getEntityId();
            }
        }
        sort($ids);

        return $ids;
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    private function locked(DemoScenarioInterface $scenario, callable $work): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $acquired = $connection->fetchOne('SELECT pg_try_advisory_xact_lock(hashtext(?))', ['demo-scenario:' . $scenario->key()]);
            if (!$acquired) {
                throw new DemoScenarioException(sprintf('Szenario «%s» wird gerade von einem anderen Lauf bearbeitet.', $scenario->key()));
            }
            $result = $work();
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->entityManager->close();
            throw $e;
        }
    }
}
