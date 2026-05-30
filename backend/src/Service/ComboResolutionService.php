<?php

namespace App\Service;

use App\Entity\MaterialComboComponent;
use App\Entity\MaterialComboOption;
use App\Entity\MaterialComboOptionDelta;
use App\Entity\MaterialItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Löst eine virtuelle Kombo (Weg B) in ihre effektiven Stück-Mengen auf.
 *
 * Endmenge je Teil = Σ Basis-Stückliste + Σ (gewählte Option-Deltas), pro Teil auf ≥ 0 geklemmt.
 *
 * Toggle-Auswahl (Paket 5):
 *  - Optionale Basis-Komponenten (`MaterialComboComponent.is_optional = true`) sind Ja/Nein-Toggles;
 *    ihre Toggle-Id ist die Komponenten-Id (`cc:<id>`), default aus.
 *  - `MaterialComboOption` (display_mode=toggle) ist die ausgebaute Form (Toggle-Id `opt:<id>`),
 *    default = default_selected; ihre Deltas werden addiert/abgezogen (Gruppen-UI erst Paket 6).
 *
 * `self_provided`-Teile zählen NIE in Flaschenhals/Reservierung — nur Hinweis-/Checklisten-Posten.
 */
final class ComboResolutionService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Liefert die verfügbaren Toggle-Ids einer Kombo inkl. Default-Auswahl.
     *
     * @return list<string>
     */
    public function defaultSelectedOptionIds(string $comboId): array
    {
        $selected = [];

        // MaterialComboOption mit default_selected (Toggle UND Gruppen-Optionen, Paket 6).
        $options = $this->entityManager->getRepository(MaterialComboOption::class)
            ->findBy(['materialItemId' => $comboId]);
        foreach ($options as $opt) {
            if ($opt->getDefaultSelected()) {
                $selected[] = 'opt:' . $opt->getId();
            }
        }

        return $selected;
    }

    /**
     * Löst die Kombo in effektive Mengen je Teil auf.
     *
     * @param list<string> $selectedOptionIds aktivierte Toggle-Ids (`cc:<id>` und/oder `opt:<id>`)
     *
     * @return array{
     *   stock: array<string, array{component_material_id: string, name: string, qty_per_combo: int, assignment_mode: string, tracking: ?string}>,
     *   self_provided: array<string, array{component_material_id: string, name: string, qty_per_combo: int}>
     * }
     */
    public function resolve(string $comboId, array $selectedOptionIds): array
    {
        $selected = array_fill_keys($selectedOptionIds, true);

        /** @var array<string, array{component_material_id: string, name: string, qty_per_combo: int, assignment_mode: string, tracking: ?string, source: string}> $eff */
        $eff = [];

        $addQty = function (string $mid, int $delta, string $name, string $assignmentMode, ?string $tracking, string $source) use (&$eff): void {
            if (!isset($eff[$mid])) {
                $eff[$mid] = [
                    'component_material_id' => $mid,
                    'name' => $name,
                    'qty_per_combo' => 0,
                    'assignment_mode' => $assignmentMode,
                    'tracking' => $tracking,
                    'source' => $source,
                ];
            }
            $eff[$mid]['qty_per_combo'] += $delta;
        };

        // Basis-Stückliste: Pflichtteile immer, optionale (Toggle cc:<id>) nur wenn gewählt.
        /** @var MaterialComboComponent[] $components */
        $components = $this->entityManager->getRepository(MaterialComboComponent::class)
            ->createQueryBuilder('cc')
            ->leftJoin('cc.componentMaterial', 'cm')
            ->addSelect('cm')
            ->where('cc.parentMaterialId = :pid')
            ->setParameter('pid', $comboId)
            ->orderBy('cc.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($components as $cc) {
            if ($cc->getIsOptional() && !isset($selected['cc:' . $cc->getId()])) {
                continue;
            }
            $cm = $cc->getComponentMaterial();
            $addQty(
                $cc->getComponentMaterialId(),
                $cc->getQty(),
                $cm->getName(),
                $cc->getAssignmentMode(),
                $cm->getTrackingType(),
                $cc->getComponentSource(),
            );
        }

        // Options-Deltas (ausgebaute Form): nur gewählte Toggle-Optionen anwenden.
        /** @var MaterialComboOption[] $options */
        $options = $this->entityManager->getRepository(MaterialComboOption::class)
            ->findBy(['materialItemId' => $comboId]);
        foreach ($options as $opt) {
            $isToggle = $opt->getDisplayMode() === 'toggle';
            $chosen = isset($selected['opt:' . $opt->getId()]);
            // Im Paket 5 nur Toggles; Gruppen-Optionen (Paket 6) nur, wenn explizit gewählt.
            if (!$chosen) {
                continue;
            }
            if (!$isToggle && $opt->getOptionGroupId() === null) {
                // eigenständige Nicht-Toggle-Option ohne Gruppe: wie Toggle behandeln
                $isToggle = true;
            }
            /** @var MaterialComboOptionDelta[] $deltas */
            $deltas = $this->entityManager->getRepository(MaterialComboOptionDelta::class)
                ->createQueryBuilder('d')
                ->leftJoin('d.componentMaterial', 'cm')
                ->addSelect('cm')
                ->where('d.optionId = :oid')
                ->setParameter('oid', $opt->getId())
                ->getQuery()
                ->getResult();
            foreach ($deltas as $d) {
                $cm = $d->getComponentMaterial();
                $addQty(
                    $d->getComponentMaterialId(),
                    $d->getQtyDelta(),
                    $cm->getName(),
                    $d->getAssignmentMode(),
                    $d->getTracking(),
                    $d->getComponentSource(),
                );
            }
        }

        $stock = [];
        $selfProvided = [];
        foreach ($eff as $mid => $row) {
            // Klemmung ≥ 0 (Abzüge dürfen die Endmenge nicht unter 0 drücken).
            $qty = max(0, $row['qty_per_combo']);
            if ($qty <= 0) {
                continue;
            }
            if ($row['source'] === 'self_provided') {
                $selfProvided[$mid] = [
                    'component_material_id' => $mid,
                    'name' => $row['name'],
                    'qty_per_combo' => $qty,
                ];
            } else {
                $stock[$mid] = [
                    'component_material_id' => $mid,
                    'name' => $row['name'],
                    'qty_per_combo' => $qty,
                    'assignment_mode' => $row['assignment_mode'],
                    'tracking' => $row['tracking'],
                ];
            }
        }

        return ['stock' => $stock, 'self_provided' => $selfProvided];
    }
}
