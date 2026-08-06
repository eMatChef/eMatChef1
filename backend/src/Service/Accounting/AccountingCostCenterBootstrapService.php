<?php

namespace App\Service\Accounting;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\AccountingCostCenter;
use App\Entity\AccountingCostCenterRule;
use App\Entity\Department;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Legt Standard-Kostenstellen und Zuordnungsregeln für ein Department an (idempotent).
 *
 * Bestehende Kostenstellen/Regeln werden nicht überschrieben. Fehlende Defaults
 * werden nachgezogen (auch bei älteren Departments mit Teil-Set).
 */
final class AccountingCostCenterBootstrapService
{
    /**
     * Primärname + optionale Aliase (ältere Seeds / Umbenennungen).
     *
     * @var array<string, array{name: string, description: string, sort_order: int, aliases: list<string>}>
     */
    public const COST_CENTERS = [
        'general' => [
            'name' => 'Allgemein',
            'description' => 'Nicht zugeordnete oder übergreifende Kosten',
            'sort_order' => 0,
            'aliases' => ['Allgemeiner Bedarf'],
        ],
        'material' => [
            'name' => 'Material & Einkauf',
            'description' => 'Anschaffungen und Nachkäufe',
            'sort_order' => 10,
            'aliases' => ['Material & Ausstattung'],
        ],
        'repair' => [
            'name' => 'Reparatur & Werkstatt',
            'description' => 'Reparaturen intern und extern',
            'sort_order' => 20,
            'aliases' => [],
        ],
        'rental' => [
            'name' => 'Vermietung / Extern',
            'description' => 'Externe Ausleihe und Kundenrechnungen',
            'sort_order' => 30,
            'aliases' => ['Vermietung'],
        ],
        'consumption' => [
            'name' => 'Verbrauch / Gruppen',
            'description' => 'Verbrauch und Gruppenkosten aus Aktivitäten',
            'sort_order' => 40,
            'aliases' => [],
        ],
    ];

    /**
     * source_kind → Kostenstellen-Key (+ optionale Buchungs-Defaults).
     *
     * @var list<array{source_kind: string, cost_center_key: string, entry_type: ?string, payment_method: ?string}>
     */
    public const RULES = [
        [
            'source_kind' => AccountingAcquisitionFollowUp::SOURCE_BATCH,
            'cost_center_key' => 'material',
            'entry_type' => AccountingBooking::ENTRY_PURCHASE,
            'payment_method' => AccountingBooking::PAYMENT_SUPPLIER,
        ],
        [
            'source_kind' => AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT,
            'cost_center_key' => 'material',
            'entry_type' => AccountingBooking::ENTRY_PURCHASE,
            'payment_method' => AccountingBooking::PAYMENT_SUPPLIER,
        ],
        [
            'source_kind' => AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
            'cost_center_key' => 'repair',
            'entry_type' => AccountingBooking::ENTRY_REPAIR_EXTERNAL,
            'payment_method' => AccountingBooking::PAYMENT_SUPPLIER,
        ],
        [
            'source_kind' => AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL,
            'cost_center_key' => 'rental',
            'entry_type' => AccountingBooking::ENTRY_OTHER,
            'payment_method' => null,
        ],
        [
            'source_kind' => AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
            'cost_center_key' => 'consumption',
            'entry_type' => AccountingBooking::ENTRY_OTHER,
            'payment_method' => AccountingBooking::PAYMENT_CASH_GROUP,
        ],
    ];

    /**
     * @return array{cost_centers_created: int, rules_created: int}
     */
    public function ensureDefaults(EntityManagerInterface $em, Department $department): array
    {
        $costCentersCreated = $this->ensureCostCenters($em, $department);
        // Flush bevor Regeln aufgelöst werden, damit neue Kostenstellen in Queries sichtbar sind.
        if ($costCentersCreated > 0) {
            $em->flush();
        }

        $rulesCreated = $this->ensureRules($em, $department);
        if ($rulesCreated > 0) {
            $em->flush();
        }

        return [
            'cost_centers_created' => $costCentersCreated,
            'rules_created' => $rulesCreated,
        ];
    }

    /**
     * @return int Anzahl neu angelegter Kostenstellen
     */
    public function ensureDefaultCostCenters(EntityManagerInterface $em, Department $department): int
    {
        return $this->ensureDefaults($em, $department)['cost_centers_created'];
    }

    /**
     * @return int Anzahl neu angelegter Kostenstellen
     */
    private function ensureCostCenters(EntityManagerInterface $em, Department $department): int
    {
        $existing = $em->createQueryBuilder()
            ->select('c')
            ->from(AccountingCostCenter::class, 'c')
            ->where('c.department = :d')
            ->setParameter('d', $department)
            ->getQuery()
            ->getResult();

        /** @var array<string, AccountingCostCenter> $byNormalizedName */
        $byNormalizedName = [];
        foreach ($existing as $cc) {
            if ($cc instanceof AccountingCostCenter) {
                $byNormalizedName[$this->normalizeName($cc->getName())] = $cc;
            }
        }

        $created = 0;
        foreach (self::COST_CENTERS as $def) {
            if ($this->findCostCenter($byNormalizedName, $def['name'], $def['aliases']) !== null) {
                continue;
            }

            $cc = new AccountingCostCenter();
            $cc->setId(IdGenerator::generate13Unique($em, AccountingCostCenter::class, 'ks'));
            $cc->setDepartment($department);
            $cc->setName($def['name']);
            $cc->setDescription($def['description']);
            $cc->setSortOrder($def['sort_order']);
            $em->persist($cc);
            $byNormalizedName[$this->normalizeName($def['name'])] = $cc;
            $created++;
        }

        return $created;
    }

    /**
     * @return int Anzahl neu angelegter Regeln
     */
    private function ensureRules(EntityManagerInterface $em, Department $department): int
    {
        $centers = $em->createQueryBuilder()
            ->select('c')
            ->from(AccountingCostCenter::class, 'c')
            ->where('c.department = :d')
            ->setParameter('d', $department)
            ->getQuery()
            ->getResult();

        /** @var array<string, AccountingCostCenter> $byNormalizedName */
        $byNormalizedName = [];
        foreach ($centers as $cc) {
            if ($cc instanceof AccountingCostCenter) {
                $byNormalizedName[$this->normalizeName($cc->getName())] = $cc;
            }
        }

        $existingRules = $em->createQueryBuilder()
            ->select('r')
            ->from(AccountingCostCenterRule::class, 'r')
            ->where('r.department = :d')
            ->setParameter('d', $department)
            ->getQuery()
            ->getResult();

        /** @var array<string, AccountingCostCenterRule> $rulesByKind */
        $rulesByKind = [];
        foreach ($existingRules as $rule) {
            if ($rule instanceof AccountingCostCenterRule) {
                $rulesByKind[$rule->getSourceKind()] = $rule;
            }
        }

        $created = 0;
        foreach (self::RULES as $ruleDef) {
            $sourceKind = $ruleDef['source_kind'];
            if (isset($rulesByKind[$sourceKind])) {
                continue;
            }

            $ccKey = $ruleDef['cost_center_key'];
            $ccDef = self::COST_CENTERS[$ccKey] ?? null;
            if ($ccDef === null) {
                continue;
            }

            $costCenter = $this->findCostCenter($byNormalizedName, $ccDef['name'], $ccDef['aliases']);
            if (!$costCenter instanceof AccountingCostCenter) {
                continue;
            }

            $rule = new AccountingCostCenterRule();
            $rule->setId(IdGenerator::generate13Unique($em, AccountingCostCenterRule::class, 'kr'));
            $rule->setDepartment($department);
            $rule->setSourceKind($sourceKind);
            $rule->setCostCenter($costCenter);
            $rule->setDefaultEntryType($ruleDef['entry_type']);
            $rule->setDefaultPaymentMethod($ruleDef['payment_method']);
            $em->persist($rule);
            $rulesByKind[$sourceKind] = $rule;
            $created++;
        }

        return $created;
    }

    /**
     * @param array<string, AccountingCostCenter> $byNormalizedName
     * @param list<string>                        $aliases
     */
    private function findCostCenter(array $byNormalizedName, string $primaryName, array $aliases): ?AccountingCostCenter
    {
        $candidates = [$primaryName, ...$aliases];
        foreach ($candidates as $name) {
            $key = $this->normalizeName($name);
            if (isset($byNormalizedName[$key])) {
                return $byNormalizedName[$key];
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
