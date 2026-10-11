<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassBudgetArea;
use App\Entity\DepartmentGrossanlassCost;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Budget Material & Logistik: je ein Limit für Material und Logistik, das Gesamtbudget ist ihre Summe. Die Auswertung
 * liest die bestehenden Kostenpositionen (eine Hauptposition je Beschaffungsposition) und ordnet jede Position genau
 * einer Stufe zu, damit Bedarf, Offerte, Zusage, Bestellung und effektive Kosten nie doppelt gezählt werden.
 *
 * Stufe je Position (die am weitesten fortgeschrittene gilt, ihr Betrag ist der bekannteste Betrag):
 *  - bestellt: Bestellung vorhanden (Betrag der Bestellung), noch nicht vollständig eingegangen
 *  - effektiv: Bestellung vorhanden und Ware vollständig im Wareneingang
 *  - zusage:   gewählte Offerte (Betrag der Offerte)
 *  - offerte:  Offerten vorhanden, keine gewählt (günstigste Offerte)
 *  - bedarf:   nur geplanter Betrag der Kostenposition
 * Bereich: Fahrzeuge (Beschaffungsart «fahrzeug») zählen zur Logistik, alles andere zum Material.
 */
class GrossanlassBudgetAreaService
{
    public const STAGES = ['bedarf', 'offerte', 'zusage', 'bestellt', 'effektiv'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassCostService $costs,
        private GrossanlassChargeMovementService $movements,
    ) {}

    /** @return array<string, mixed> */
    public function overview(Department $department, User $user): array
    {
        $this->assertCanRead($department, $user);

        $limits = $this->limits($department);
        $stages = [];
        $counts = [];
        foreach (DepartmentGrossanlassBudgetArea::AREAS as $area) {
            $stages[$area] = array_fill_keys(self::STAGES, 0.0);
            $counts[$area] = 0;
        }
        foreach ($this->positions($department) as $position) {
            $stages[$position['area']][$position['stage']] += $position['amount'];
            ++$counts[$position['area']];
        }

        $areas = [];
        $totalStages = array_fill_keys(self::STAGES, 0.0);
        foreach (DepartmentGrossanlassBudgetArea::AREAS as $area) {
            $areas[$area] = self::summarise($limits[$area], $stages[$area], $counts[$area]);
            foreach (self::STAGES as $stage) {
                $totalStages[$stage] += $stages[$area][$stage];
            }
        }
        $total = self::summarise(array_sum($limits), $totalStages, array_sum($counts));

        return [
            'total' => $total,
            'material' => $areas[DepartmentGrossanlassBudgetArea::AREA_MATERIAL],
            'logistik' => $areas[DepartmentGrossanlassBudgetArea::AREA_LOGISTIK],
            'can_manage' => $this->access->canManageProcurement($user, $department),
        ];
    }

    /**
     * Limits setzen. Das Gesamtbudget ist die Summe; der bestehende Anlass-Rahmen der Kostenübersicht wird darauf
     * nachgeführt, damit es keinen zweiten, abweichenden Gesamtbetrag gibt.
     *
     * @param array<string, mixed> $data material_chf, logistik_chf (Betrag oder null zum Entfernen)
     *
     * @return array<string, mixed>
     */
    public function setLimits(Department $department, User $user, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManageProcurement($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für das Budget');
        }
        foreach ([DepartmentGrossanlassBudgetArea::AREA_MATERIAL => 'material_chf', DepartmentGrossanlassBudgetArea::AREA_LOGISTIK => 'logistik_chf'] as $area => $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $amount = $this->parseAmount($data[$field]);
            $row = $this->entityManager->find(DepartmentGrossanlassBudgetArea::class, ['departmentId' => $department->getId(), 'area' => $area]);
            if ($amount === null) {
                if ($row instanceof DepartmentGrossanlassBudgetArea) {
                    $this->entityManager->remove($row);
                }
                continue;
            }
            if (!$row instanceof DepartmentGrossanlassBudgetArea) {
                $row = new DepartmentGrossanlassBudgetArea($department->getId(), $area);
                $this->entityManager->persist($row);
            }
            $row->setLimitChf($amount);
        }
        $this->entityManager->flush();

        $limits = $this->limits($department);
        $sum = $limits[DepartmentGrossanlassBudgetArea::AREA_MATERIAL] + $limits[DepartmentGrossanlassBudgetArea::AREA_LOGISTIK];
        $this->costs->upsertBudget($department, $user, ['payer_group_id' => null, 'rahmen_chf' => $sum > 0 ? number_format($sum, 2, '.', '') : null]);

        return $this->overview($department, $user);
    }

    /** @return array<string, float> */
    private function limits(Department $department): array
    {
        $limits = [DepartmentGrossanlassBudgetArea::AREA_MATERIAL => 0.0, DepartmentGrossanlassBudgetArea::AREA_LOGISTIK => 0.0];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassBudgetArea::class)->findBy(['departmentId' => $department->getId()]) as $row) {
            if ($row instanceof DepartmentGrossanlassBudgetArea) {
                $limits[$row->getArea()] = round((float) $row->getLimitChf(), 2);
            }
        }

        return $limits;
    }

    /**
     * @return list<array{area: string, stage: string, amount: float}>
     */
    private function positions(Department $department): array
    {
        $costs = $this->entityManager->getRepository(DepartmentGrossanlassCost::class)->findBy(['departmentId' => $department->getId()]);
        $out = [];
        foreach ($costs as $cost) {
            if (!$cost instanceof DepartmentGrossanlassCost || !$cost->isMainKind() || $cost->getStatus() === DepartmentGrossanlassCost::STATUS_CANCELLED) {
                continue;
            }
            $line = $cost->getProcurementLine();
            $area = $line instanceof ActivityGrossanlassProcurementLine && $line->getWishKind() === 'fahrzeug'
                ? DepartmentGrossanlassBudgetArea::AREA_LOGISTIK
                : DepartmentGrossanlassBudgetArea::AREA_MATERIAL;
            [$stage, $amount] = $line instanceof ActivityGrossanlassProcurementLine
                ? $this->stageOf($cost, $line)
                : ['bedarf', round((float) $cost->getSollChf(), 2)];
            $out[] = ['area' => $area, 'stage' => $stage, 'amount' => $amount];
        }

        return $out;
    }

    /** @return array{0: string, 1: float} */
    private function stageOf(DepartmentGrossanlassCost $cost, ActivityGrossanlassProcurementLine $line): array
    {
        $order = $this->entityManager->getRepository(ActivityGrossanlassProcurementOrder::class)->findOneBy(['procurementLineId' => $line->getId()]);
        if ($order instanceof ActivityGrossanlassProcurementOrder) {
            $commitment = $this->movements->buyChargeForLine($line);
            $received = $commitment !== null && $this->movements->receivedQuantity($commitment) >= $commitment->getQuantity();

            return [$received ? 'effektiv' : 'bestellt', round((float) $order->getCostChf(), 2)];
        }
        $quotes = array_filter(
            $this->entityManager->getRepository(ActivityGrossanlassProcurementQuote::class)->findBy(['procurementLineId' => $line->getId()]),
            static fn ($q) => $q instanceof ActivityGrossanlassProcurementQuote,
        );
        foreach ($quotes as $quote) {
            if ($quote->isSelected()) {
                return ['zusage', round((float) $quote->getAmountChf(), 2)];
            }
        }
        if ($quotes !== []) {
            return ['offerte', round(min(array_map(static fn (ActivityGrossanlassProcurementQuote $q) => (float) $q->getAmountChf(), $quotes)), 2)];
        }

        return ['bedarf', round((float) $cost->getSollChf(), 2)];
    }

    /**
     * Kennzahlen eines Bereichs aus Limit und den Beträgen je Stufe. Verpflichtet = Zusagen + Bestellungen + effektive Kosten;
     * erwartet = alle Stufen. Der verfügbare Rest bezieht sich auf das Verpflichtete, die Überschreitung zusätzlich auf die
     * Erwartung.
     *
     * @param array<string, float> $stages Betrag je Stufe (siehe {@see STAGES})
     *
     * @return array<string, mixed>
     */
    public static function summarise(float $limit, array $stages, int $positionCount = 0): array
    {
        $stages = array_map(static fn (float $v): float => round($v, 2), array_replace(array_fill_keys(self::STAGES, 0.0), $stages));
        $committed = round($stages['zusage'] + $stages['bestellt'] + $stages['effektiv'], 2);
        $expected = round(array_sum($stages), 2);
        $limit = round($limit, 2);
        $overExpected = round(max(0.0, $expected - $limit), 2);

        return [
            'planned_chf' => $limit,
            'stages' => $stages,
            'position_count' => $positionCount,
            'expected_chf' => $expected,
            'offers_chf' => $stages['offerte'],
            'commitments_chf' => $stages['zusage'],
            'ordered_chf' => $stages['bestellt'],
            'actual_chf' => $stages['effektiv'],
            'committed_chf' => $committed,
            'remaining_chf' => round($limit - $committed, 2),
            'overrun_committed_chf' => round(max(0.0, $committed - $limit), 2),
            'overrun_expected_chf' => $overExpected,
            'over_budget' => $limit > 0.0 ? $overExpected > 0.0 : $expected > 0.0,
        ];
    }

    private function parseAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $amount = is_numeric($value) ? (float) $value : (float) str_replace(["'", ' '], '', str_replace(',', '.', (string) $value));
        if ($amount < 0) {
            throw new \InvalidArgumentException('Betrag darf nicht negativ sein');
        }

        return number_format($amount, 2, '.', '');
    }

    private function assertCanRead(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManageProcurement($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für das Budget');
        }
    }
}
