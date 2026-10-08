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

    public function reset(DemoScenarioInterface $scenario): SeedResult
    {
        if (!$scenario->supportsReset()) {
            throw new DemoScenarioException(sprintf('Reset für «%s» ist noch nicht verfügbar.', $scenario->key()));
        }
        $department = $this->identity->findDepartment($scenario->key());
        if ($department === null) {
            throw new DemoScenarioException(sprintf('Szenario «%s» hat kein Department; nichts zu tun.', $scenario->key()));
        }
        $departmentId = (string) $department->getId();

        return $this->locked($scenario, function () use ($scenario, $department, $departmentId): SeedResult {
            $result = $scenario->reset(SeedContext::create($scenario, $department, $this->ledger, false));

            $key = $this->entityManager->getConnection()->fetchOne('SELECT demo_scenario_key FROM department WHERE id = ?', [$departmentId]);
            if ($key !== $scenario->key()) {
                throw new DemoScenarioException('Reset hat die Department-Zeile oder deren Szenario-Schlüssel verändert; zurückgerollt.');
            }

            return $result;
        });
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
