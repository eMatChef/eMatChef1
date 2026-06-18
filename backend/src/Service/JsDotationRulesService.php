<?php

namespace App\Service;

use App\Entity\MaterialItem;

/**
 * J+S-Dotationsregeln (Leihmaterial Lagersport/Trekking) — Vorschlagsmengen + Validierung.
 *
 * Zuordnung über Materialname (Patterns), da Katalog global (dept_js00000) ist.
 */
class JsDotationRulesService
{
    /** Bestand ab diesem Wert gilt als «unbegrenzt» (J+S-Katalog-Platzhalter). */
    public const UNLIMITED_STOCK_THRESHOLD = 99999;

    /** @var array<string, string> J+S-Formularzeile (PDF) pro Regel — Katalog kann mehrere Lager-Varianten haben. */
    private const PDF_LINE_BY_KEY = [
        'bindestrick' => 'Bindestrick',
        'wolldecke' => 'Wolldecke',
        'kessel_15' => 'Kessel 15 l',
        'kesselaufsatz' => 'Kesselaufsatz nieder',
        'handbeil' => 'Handbeil',
        'kochkessel_12' => 'Kochkessel 12 l',
        'kompass_recta' => 'Kompass Recta',
        'kompass_silva' => 'Kompass Silva',
        'manipulierseil' => 'Manipulierseil 10–15 m',
        'pickel' => 'Pickel',
        'beinstulpe' => 'Beinstulpe refl.',
        'rettungsweste' => 'Schwimmwesten',
        'schneeschaufel' => 'Schneeschaufel',
        'sonnenbrille' => 'Sonnenbrille SUVASOL',
        'spaten' => 'Spaten',
        'speisetraeger' => 'Speiseträger 20 l',
        'badminton_schlaeger' => 'Badminton-Set',
        'beachvolleyball' => 'Volleyball-Set',
        'netz_kombi' => 'Badminton/Volleyball kombiniert',
        'ballset' => 'Ballset',
        'zelttasche' => 'Zelttasche zu Zelttuch',
        'zelttuch' => 'Zelttuch',
        'ausschusszelttuch' => 'Ausschusszelttuch',
    ];

    /** @var list<array<string, mixed>> */
    private const RULES = [
        [
            'key' => 'bindestrick',
            'patterns' => ['bindestrick'],
            'hint' => '1/TN, max. 50/Kurs, aufrunden auf 5',
            'per_tn' => 1,
            'max' => 50,
            'round_up' => 5,
            'exclusive_group' => 'bindestrick',
        ],
        [
            'key' => 'wolldecke',
            'patterns' => ['wolldecke'],
            'hint' => '2/TN, aufrunden auf 5',
            'per_tn' => 2,
            'round_up' => 5,
        ],
        [
            'key' => 'kessel_15',
            'patterns' => ['kessel 15'],
            'hint' => '1 für 6 TN',
            'per_n' => 6,
        ],
        [
            'key' => 'kesselaufsatz',
            'patterns' => ['kesselaufsatz'],
            'hint' => '1 für 6 TN',
            'per_n' => 6,
        ],
        [
            'key' => 'handbeil',
            'patterns' => ['handbeil'],
            'hint' => '1 für 4 TN',
            'per_n' => 4,
        ],
        [
            'key' => 'kochkessel_12',
            'patterns' => ['kochkessel 12', 'kochkessel 12l'],
            'hint' => '1 für 8 TN (inkl. Deckel)',
            'per_n' => 8,
        ],
        [
            'key' => 'kompass_recta',
            'patterns' => ['kompass bussole recta', 'kompass recta'],
            'hint' => '1 für 2 TN, aufrunden auf 5',
            'per_n' => 2,
            'round_up' => 5,
        ],
        [
            'key' => 'kompass_silva',
            'patterns' => ['kompass silva'],
            'hint' => '1 für 2 TN, aufrunden auf 5',
            'per_n' => 2,
            'round_up' => 5,
        ],
        [
            'key' => 'manipulierseil',
            'patterns' => ['manipulierseil'],
            'hint' => '1 für 2 TN (10–15 m)',
            'per_n' => 2,
        ],
        [
            'key' => 'pickel',
            'patterns' => ['pickel'],
            'hint' => '1 für 4 TN',
            'per_n' => 4,
        ],
        [
            'key' => 'beinstulpe',
            'patterns' => ['beinstulpe'],
            'hint' => '1/TN',
            'per_tn' => 1,
        ],
        [
            'key' => 'rettungsweste',
            'patterns' => ['rettungsweste', 'schwimmweste'],
            'hint_lager' => '1/TN — Grössen manuell wählen',
            'hint_kader' => '1/TN, formular max. 20 — Grössen manuell wählen',
            'per_tn' => 0,
            'group' => 'rettungsweste',
            'group_warn_max' => 20,
        ],
        [
            'key' => 'schneeschaufel',
            'patterns' => ['schneeschaufel'],
            'hint' => '1/TN, nur Winter, max. 15/Kurs',
            'per_tn' => 1,
            'max' => 15,
            'winter_only' => true,
        ],
        [
            'key' => 'sonnenbrille',
            'patterns' => ['sonnenbrille suvasol', 'sonnenbrille'],
            'hint' => '1/TN, max. 15/Kurs',
            'per_tn' => 1,
            'max' => 15,
        ],
        [
            'key' => 'spaten',
            'patterns' => ['spaten'],
            'hint' => '1 für 4 TN',
            'per_n' => 4,
        ],
        [
            'key' => 'speisetraeger',
            'patterns' => ['speisetr', 'speisetraeger'],
            'hint' => '1 für 18 TN (20 l inkl. Schöpfkelle)',
            'per_n' => 18,
        ],
        [
            'key' => 'badminton_schlaeger',
            'patterns' => ['badmintonschl'],
            'hint' => 'Badminton-Set: max. 3/Kurs (Spielsets gesamt max. 3)',
            'per_tn' => 0,
            'max' => 3,
            'group' => 'spielset',
            'group_max' => 3,
        ],
        [
            'key' => 'beachvolleyball',
            'patterns' => ['beachvolleyball'],
            'hint' => 'Volleyball-Set: max. 1/Kurs (Spielsets gesamt max. 3)',
            'per_tn' => 0,
            'max' => 1,
            'group' => 'spielset',
            'group_max' => 3,
        ],
        [
            'key' => 'netz_kombi',
            'patterns' => ['netz f'],
            'hint' => 'Badminton/Volleyball kombiniert: max. 2/Kurs (Spielsets gesamt max. 3)',
            'per_tn' => 0,
            'max' => 2,
            'group' => 'spielset',
            'group_max' => 3,
        ],
        [
            'key' => 'ballset',
            'patterns' => ['ballset'],
            'hint' => 'Ballset: max. 2/Kurs (Spielsets gesamt max. 3)',
            'per_tn' => 0,
            'max' => 2,
            'group' => 'spielset',
            'group_max' => 3,
        ],
        [
            'key' => 'zelttasche',
            'patterns' => ['zelttasche'],
            'hint' => '1/TN, aufrunden auf 5',
            'per_tn' => 1,
            'round_up' => 5,
        ],
        [
            'key' => 'zelttuch',
            'patterns' => ['zelttuch'],
            'hint' => '1/TN, aufrunden auf 10',
            'per_tn' => 1,
            'round_up' => 10,
            'exclude_patterns' => ['ausschuss', 'zelttasche'],
            'exclusive_group' => 'zelttuch',
        ],
        [
            'key' => 'ausschusszelttuch',
            'patterns' => ['ausschusszelttuch', 'ausschuss'],
            'hint' => '1/TN, aufrunden auf 10',
            'per_tn' => 1,
            'round_up' => 10,
        ],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function matchRuleForMaterial(MaterialItem $material): ?array
    {
        $name = $this->normalizeName($material->getName());

        foreach (self::RULES as $rule) {
            if (!$this->nameMatchesRule($name, $rule)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    public function dotationHintForMaterial(MaterialItem $material, ?string $courseType = null): ?string
    {
        $rule = $this->matchRuleForMaterial($material);
        if ($rule === null) {
            return null;
        }

        if (($rule['key'] ?? '') === 'rettungsweste') {
            return $this->isKaderCourseType($courseType)
                ? (string) ($rule['hint_kader'] ?? '')
                : (string) ($rule['hint_lager'] ?? '');
        }

        return (string) ($rule['hint'] ?? '');
    }

    public function pdfFormLineForMaterial(MaterialItem $material): ?string
    {
        $rule = $this->matchRuleForMaterial($material);
        if ($rule === null) {
            return null;
        }

        $key = (string) ($rule['key'] ?? '');
        if ($key === 'rettungsweste') {
            return $this->pdfLineForRettungsweste($material->getName());
        }

        return self::PDF_LINE_BY_KEY[$key] ?? null;
    }

    public function variantGroupForMaterial(MaterialItem $material): ?string
    {
        $rule = $this->matchRuleForMaterial($material);
        if ($rule === null) {
            return null;
        }

        $group = $rule['exclusive_group'] ?? null;

        return $group !== null ? (string) $group : null;
    }

    /**
     * @return array{max: int|null, group: string|null, group_max: int|null, group_warn_max: int|null, round_up: int|null}
     */
    public function limitsForMaterial(MaterialItem $material): array
    {
        $rule = $this->matchRuleForMaterial($material);
        if ($rule === null) {
            return [
                'max' => null,
                'group' => null,
                'group_max' => null,
                'group_warn_max' => null,
                'round_up' => null,
            ];
        }

        return [
            'max' => isset($rule['max']) ? (int) $rule['max'] : null,
            'group' => isset($rule['group']) ? (string) $rule['group'] : null,
            'group_max' => isset($rule['group_max']) ? (int) $rule['group_max'] : null,
            'group_warn_max' => isset($rule['group_warn_max']) ? (int) $rule['group_warn_max'] : null,
            'round_up' => isset($rule['round_up']) ? (int) $rule['round_up'] : null,
        ];
    }

    public function stockAvailableForMaterial(MaterialItem $material): ?int
    {
        $stock = $material->getTotalStock();
        if ($stock >= self::UNLIMITED_STOCK_THRESHOLD) {
            return null;
        }

        return max(0, $stock);
    }

    public function suggestQuantityForMaterial(MaterialItem $material, int $participantCount): ?int
    {
        if ($participantCount < 1) {
            return null;
        }

        $rule = $this->matchRuleForMaterial($material);
        if ($rule === null) {
            return null;
        }

        if (!empty($rule['winter_only']) && !$this->isWinterContext()) {
            return null;
        }

        $perTn = (int) ($rule['per_tn'] ?? 0);
        $perN = (int) ($rule['per_n'] ?? 0);
        if ($perTn > 0) {
            $qty = (int) ceil($participantCount * $perTn);
        } elseif ($perN > 0) {
            $qty = (int) ceil($participantCount / $perN);
        } else {
            $qty = 0;
        }

        $max = $rule['max'] ?? null;
        if ($max !== null) {
            $qty = min($qty, (int) $max);
        }

        $roundUp = $rule['round_up'] ?? null;
        if ($roundUp !== null && (int) $roundUp > 1 && $qty > 0) {
            $qty = (int) (ceil($qty / (int) $roundUp) * (int) $roundUp);
        }

        if (($rule['group'] ?? null) === 'rettungsweste') {
            return null;
        }

        return max(0, $qty);
    }

    /**
     * @param list<array{material_item_id: string, material_name?: string, quantity_ordered: int}> $items
     *
     * @return list<string> Validierungsfehler (leer = ok)
     */
    public function validateOrderItems(int $participantCount, array $items): array
    {
        $errors = [];
        $groupTotals = [];

        foreach ($items as $row) {
            $qty = (int) ($row['quantity_ordered'] ?? 0);
            if ($qty < 0) {
                $errors[] = 'Menge darf nicht negativ sein.';

                continue;
            }

            $name = $this->normalizeName((string) ($row['material_name'] ?? ''));
            $rule = $this->findRuleByName($name);
            if ($rule === null || $qty === 0) {
                continue;
            }

            $max = $rule['max'] ?? null;
            if ($max !== null && $qty > (int) $max) {
                $errors[] = sprintf(
                    '%s: max. %d laut Dotation (bestellt: %d)',
                    $row['material_name'] ?? $rule['key'],
                    (int) $max,
                    $qty,
                );
            }

            $group = $rule['group'] ?? null;
            if ($group !== null) {
                $groupTotals[$group] = ($groupTotals[$group] ?? 0) + $qty;
            }
        }

        foreach (self::RULES as $rule) {
            $group = $rule['group'] ?? null;
            $groupMax = $rule['group_max'] ?? null;
            if ($group === null || $groupMax === null) {
                continue;
            }
            $total = $groupTotals[$group] ?? 0;
            if ($total > (int) $groupMax) {
                $errors[] = sprintf('Gruppe %s: max. %d gesamt (bestellt: %d)', $group, (int) $groupMax, $total);
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Weiche Hinweise — blockieren Speichern/PDF nicht (z. B. Schwimmwesten > 20).
     *
     * @param list<array{material_item_id: string, material_name?: string, quantity_ordered: int}> $items
     *
     * @return list<string>
     */
    public function collectOrderWarnings(int $participantCount, array $items, ?string $courseType = null): array
    {
        $warnings = [];
        $groupTotals = [];

        foreach ($items as $row) {
            $qty = (int) ($row['quantity_ordered'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            $name = $this->normalizeName((string) ($row['material_name'] ?? ''));
            $rule = $this->findRuleByName($name);
            if ($rule === null) {
                continue;
            }

            $group = $rule['group'] ?? null;
            if ($group !== null) {
                $groupTotals[$group] = ($groupTotals[$group] ?? 0) + $qty;
            }
        }

        $westeTotal = $groupTotals['rettungsweste'] ?? 0;
        $variantHits = [];
        foreach ($items as $row) {
            $qty = (int) ($row['quantity_ordered'] ?? 0);
            if ($qty < 1) {
                continue;
            }
            $name = $this->normalizeName((string) ($row['material_name'] ?? ''));
            $rule = $this->findRuleByName($name);
            $variantGroup = $rule['exclusive_group'] ?? null;
            if ($variantGroup === null) {
                continue;
            }
            $variantHits[$variantGroup][] = (string) ($row['material_name'] ?? $variantGroup);
        }
        foreach ($variantHits as $group => $names) {
            $unique = array_values(array_unique($names));
            if (\count($unique) > 1) {
                $pdfLine = self::PDF_LINE_BY_KEY[$group] ?? $group;
                $warnings[] = sprintf(
                    'Mehrere Lager-Varianten für «%s» (%s) — im PDF gibt es nur eine Zeile. Bitte nur eine Variante bestellen.',
                    $pdfLine,
                    implode(', ', $unique),
                );
            }
        }

        if ($westeTotal < 1) {
            return $warnings;
        }

        $warnMax = 20;
        if ($westeTotal > $warnMax) {
            if ($this->isKaderCourseType($courseType)) {
                $warnings[] = sprintf(
                    'Schwimmwesten: %d bestellt — im J+S-Formular für Kaderbildung sind max. %d vorgesehen. J+S könnte Rückfragen stellen.',
                    $westeTotal,
                    $warnMax,
                );
            } else {
                $warnings[] = sprintf(
                    'Schwimmwesten: %d bestellt — mehr als %d kann bei J+S Rückfragen auslösen (Lager: oft unkritisch).',
                    $westeTotal,
                    $warnMax,
                );
            }
        }

        if ($participantCount >= 1 && $westeTotal > $participantCount) {
            $warnings[] = sprintf(
                'Schwimmwesten: %d bestellt bei %d Teilnehmenden — prüfen, ob die Verteilung auf Grössen passt.',
                $westeTotal,
                $participantCount,
            );
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @return list<array{material_item_id: string, quantity_ordered: int, dotation_suggested: int|null}>
     */
    public function buildDotationSuggestions(array $materials, int $participantCount): array
    {
        $suggestions = [];
        $usedExclusive = [];

        foreach ($materials as $material) {
            if (!$material instanceof MaterialItem) {
                continue;
            }

            $rule = $this->matchRuleForMaterial($material);
            if ($rule === null) {
                continue;
            }

            $exclusive = $rule['exclusive_group'] ?? null;
            if ($exclusive !== null) {
                if (isset($usedExclusive[$exclusive])) {
                    continue;
                }
            }

            $qty = $this->suggestQuantityForMaterial($material, $participantCount);
            if ($qty === null || $qty < 1) {
                continue;
            }

            if ($exclusive !== null) {
                $usedExclusive[$exclusive] = true;
            }

            $suggestions[] = [
                'material_item_id' => $material->getId(),
                'quantity_ordered' => $qty,
                'dotation_suggested' => $qty,
            ];
        }

        return $suggestions;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function nameMatchesRule(string $normalizedName, array $rule): bool
    {
        $matched = false;
        foreach ($rule['patterns'] as $pattern) {
            if (str_contains($normalizedName, $this->normalizeName((string) $pattern))) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }

        foreach ($rule['exclude_patterns'] ?? [] as $exclude) {
            if (str_contains($normalizedName, $this->normalizeName((string) $exclude))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRuleByName(string $normalizedName): ?array
    {
        foreach (self::RULES as $rule) {
            if ($this->nameMatchesRule($normalizedName, $rule)) {
                return $rule;
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $n = mb_strtolower(trim($name));

        return str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $n);
    }

    private function isWinterContext(): bool
    {
        $month = (int) date('n');

        return $month >= 11 || $month <= 3;
    }

    private function isKaderCourseType(?string $courseType): bool
    {
        $normalized = mb_strtolower(trim((string) $courseType));

        return \in_array($normalized, ['kaderbildung', 'kader', 'ausbildung', 'ausbildungskurs'], true);
    }

    private function pdfLineForRettungsweste(string $name): string
    {
        $n = mb_strtolower($name);
        if (str_contains($n, 'xxs') || str_contains($n, '30-40')) {
            return 'Schwimmweste XXS';
        }
        if (str_contains($n, ' xs') || str_contains($n, '(40-50')) {
            return 'Schwimmweste XS';
        }
        if (str_contains($n, '(50-60')) {
            return 'Schwimmweste S';
        }
        if (str_contains($n, '(60-70')) {
            return 'Schwimmweste M';
        }
        if (str_contains($n, '(70-90')) {
            return 'Schwimmweste L';
        }
        if (str_contains($n, ' xl') || str_contains($n, '90+')) {
            return 'Schwimmweste XL';
        }

        return 'Schwimmwesten';
    }
}
