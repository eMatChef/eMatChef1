<?php

declare(strict_types=1);

namespace App\Service\Demo\Reset;

use App\Entity\Department;
use App\Service\Clock\BusinessClock;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioInterface;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Demo\Scenario\SeedResult;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sicherer Reset des Szenarios «Grossanlass Event» (nur dieses Department, nur über den Runner, ohne Löschung).
 *
 * Was passiert (alles oder nichts, eine Transaktion):
 *  1. Verwaltete Seed-Werte (Stammdaten, Ressorts, Bauprojekte, Wünsche, Orte, Karte, Adressen, Budget-Limits, Regale, Fahrzeuge,
 *     Fahrerkarten, Beschaffungspositionen und Kosten) werden auf den Katalogstand zurückgesetzt; von Hand geänderte Werte werden
 *     im Plan aufgeführt.
 *  2. Gelöschte Seed-Datensätze werden mit demselben Seed-Schlüssel und demselben QR-Code wieder angelegt.
 *  3. Die BusinessClock geht auf den Ausgangspunkt des Szenarios zurück.
 *
 * Was NICHT passiert: Es wird nichts gelöscht. Prozessdaten, die nach dem Seeden entstanden sind (Wareneingänge, Packen, Fahrten,
 * zusätzliche Anfragen oder Bestellungen), tragen keinen Eigentumsnachweis im Seed-Ledger; ob sie sicher entfernt werden dürfen, ist
 * nicht eindeutig feststellbar. Der Plan zählt sie je Tabelle als «bleiben unverändert». Ein Reset mit Löschung braucht einen
 * erweiterten Eigentumsnachweis (siehe docs/demo/scenarios/GROSSANLASS-EVENT-ENTSCHEIDUNGEN.md §3).
 *
 * Benutzer, Rollen, Mitgliedschaften, MFA, externe Identitäten, andere Departments und das bisherige Event werden nie angefasst
 * (der Runner prüft zusätzlich, dass Department-Zeile und gemeinsame Demo-Benutzer unverändert bleiben).
 */
class DemoEventResetter
{
    /** Process tables whose rows beyond the seed ledger stay untouched (counted for the plan). */
    private const PROCESS_TABLES = [
        'department_grossanlass_charge_movement' => 'Wareneingänge und Bewegungen',
        'department_grossanlass_einsatz' => 'Einsätze',
        'department_grossanlass_pack' => 'Packaufträge',
        'department_grossanlass_tour' => 'Transporttouren',
        'department_grossanlass_commitment' => 'Chargen / Zusagen',
        'department_grossanlass_inquiry' => 'Anfragen',
        'activity_grossanlass_procurement_line' => 'Beschaffungspositionen',
        'department_grossanlass_cost' => 'Kostenpositionen',
        'department_grossanlass_place' => 'Orte',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BusinessClock $clock,
    ) {
    }

    public function apply(SeedContext $context, DemoScenarioInterface $scenario): SeedResult
    {
        $department = $context->requireDepartment();
        if ($scenario->key() !== DemoScenarioKey::GROSSANLASS_EVENT || $department->getDemoScenarioKey() !== DemoScenarioKey::GROSSANLASS_EVENT
            || !$department->isDemoMode() || !$department->isGrossanlass()) {
            throw new DemoScenarioException('Reset ist nur für das Demo-Department «Grossanlass Event» zulässig.');
        }
        $id = (string) $department->getId();
        $connection = $this->entityManager->getConnection();

        $before = $this->processCounts($connection, $id);
        $sync = $scenario->sync($context->withForcedManaged());
        $department = $this->entityManager->find(Department::class, $id) ?? throw new DemoScenarioException('Department nach dem Reset nicht auffindbar; zurückgerollt.');
        $this->clock->reset($department);
        $this->entityManager->flush();

        $violations = $scenario->verify($context->withDepartment($department));
        if ($violations !== []) {
            throw new DemoScenarioException('Verify nach dem Reset meldet Verstösse (zurückgerollt): ' . implode('; ', $violations));
        }
        $after = $this->processCounts($connection, $id);

        $plan = [
            'scenario' => $scenario->key(),
            'department' => ['id' => $id, 'name' => $department->getName()],
            'delete' => [],
            'restore' => [
                'managed' => array_values(array_map(
                    static fn (string $note): string => substr($note, \strlen(DemoSetupResetter::RESTORED_PREFIX)),
                    array_filter($sync->notes, static fn (string $n): bool => str_starts_with($n, DemoSetupResetter::RESTORED_PREFIX)),
                )),
                'recreated' => $sync->created,
                'clock' => $this->clock->originFor($department)->format('Y-m-d H:i:s'),
            ],
            'unchanged_process_rows' => ['before' => $before, 'after' => $after],
            'blocked' => [],
        ];
        sort($plan['restore']['managed']);

        return SeedResult::okWithData('Demo «Grossanlass Event» zurückgesetzt (ohne Löschung).', $plan, $sync->notes);
    }

    /** @return array<string, int> */
    private function processCounts(Connection $connection, string $departmentId): array
    {
        $counts = [];
        foreach (array_keys(self::PROCESS_TABLES) as $table) {
            $counts[$table] = (int) $connection->fetchOne(sprintf('SELECT count(*) FROM %s WHERE department_id = ?', $table), [$departmentId]);
        }

        return $counts;
    }
}
