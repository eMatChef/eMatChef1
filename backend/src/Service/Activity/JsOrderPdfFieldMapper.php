<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\ActivityJsOrder;
use App\Entity\ActivityJsOrderItem;
use App\Service\JsOrderPrefillService;

/**
 * Mappt activity_js_order → AcroForm-Feldnamen des offiziellen J+S-PDFs
 * (bestellformular_lagersport_trekking_d.pdf, Stand 16.06.2021).
 */
class JsOrderPdfFieldMapper
{
    /** @var list<array{field: string, patterns: list<string>, match_all: bool}> */
    private const MATERIAL_ROWS = [
        ['field' => 'StückzahlRow1', 'patterns' => ['bindestrick'], 'match_all' => false],
        ['field' => 'StückzahlRow2', 'patterns' => ['wolldecke'], 'match_all' => false],
        ['field' => 'StückzahlRow3', 'patterns' => ['kessel 15'], 'match_all' => false],
        ['field' => 'StückzahlRow4', 'patterns' => ['kesselaufsatz'], 'match_all' => false],
        ['field' => 'StückzahlRow5', 'patterns' => ['handbeil'], 'match_all' => false],
        ['field' => 'StückzahlRow6', 'patterns' => ['kochkessel 12'], 'match_all' => false],
        ['field' => 'StückzahlRow7', 'patterns' => ['kompass', 'recta'], 'match_all' => true],
        ['field' => 'StückzahlRow7a', 'patterns' => ['kompass silva'], 'match_all' => false],
        ['field' => 'StückzahlRow8', 'patterns' => ['manipulierseil'], 'match_all' => false],
        ['field' => 'StückzahlRow9', 'patterns' => ['pickel'], 'match_all' => false],
        ['field' => 'StückzahlRow10', 'patterns' => ['beinstulpe'], 'match_all' => false],
        ['field' => 'StückzahlRow11', 'patterns' => ['rettungsweste', 'xxs'], 'match_all' => true],
        ['field' => 'StückzahlRow11a', 'patterns' => ['rettungsweste', 'xs'], 'match_all' => true],
        ['field' => 'StückzahlRow11b', 'patterns' => ['rettungsweste', '(50-60'], 'match_all' => true],
        ['field' => 'StückzahlRow11c', 'patterns' => ['rettungsweste', '(60-70'], 'match_all' => true],
        ['field' => 'StückzahlRow11d', 'patterns' => ['rettungsweste', '(70-90'], 'match_all' => true],
        ['field' => 'StückzahlRow11e', 'patterns' => ['rettungsweste', 'xl'], 'match_all' => true],
        ['field' => 'StückzahlRow12', 'patterns' => ['schneeschaufel'], 'match_all' => false],
        ['field' => 'StückzahlRow13', 'patterns' => ['sonnenbrille'], 'match_all' => false],
        ['field' => 'StückzahlRow14', 'patterns' => ['spaten'], 'match_all' => false],
        ['field' => 'StückzahlRow15', 'patterns' => ['speisetraeger', 'speisetr'], 'match_all' => false],
        ['field' => 'StückzahlRow16a', 'patterns' => ['badminton'], 'match_all' => false],
        ['field' => 'StückzahlRow16', 'patterns' => ['beachvolleyball', 'volleyball'], 'match_all' => false],
        ['field' => 'StückzahlRow16b', 'patterns' => ['netz f'], 'match_all' => false],
        ['field' => 'StückzahlRow16c', 'patterns' => ['ballset'], 'match_all' => false],
        ['field' => 'StückzahlRow17', 'patterns' => ['zelttasche'], 'match_all' => false],
        ['field' => 'StückzahlRow19', 'patterns' => ['ausschuss'], 'match_all' => false],
        ['field' => 'StückzahlRow18', 'patterns' => ['zelttuch'], 'match_all' => false],
    ];

    /**
     * @return array<string, string>
     */
    public function mapOrderToFormFields(ActivityJsOrder $order): array
    {
        $activity = $order->getActivity();
        $form = $order->getFormData() ?? JsOrderPrefillService::emptyFormData();
        $block1 = \is_array($form['block1'] ?? null) ? $form['block1'] : [];
        $block2 = \is_array($form['block2'] ?? null) ? $form['block2'] : [];
        $block3 = \is_array($form['block3'] ?? null) ? $form['block3'] : [];

        $participantCount = $order->getParticipantCount() ?? $activity->getParticipantCount();
        $courseType = (string) ($block2['course_type'] ?? '');

        $fields = [
            'PersonenNr' => $this->str($block1['person_nr'] ?? ''),
            'Name' => $this->str($block1['last_name'] ?? ''),
            'Vorname' => $this->str($block1['first_name'] ?? ''),
            'Adresse' => $this->str($block1['address'] ?? ''),
            'PLZOrt' => $this->joinPostalCity($block1['postal_code'] ?? '', $block1['city'] ?? ''),
            'Tel Nr' => $this->str($block1['phone'] ?? ''),
            'Kt' => $this->str($block1['canton'] ?? ''),
            'EMail' => $this->str($block1['email'] ?? ''),
            'Angebotsnummer' => $this->str($block1['offer_number'] ?? ''),
            'Anzahl Teilnehmende' => $participantCount !== null && $participantCount >= 1 ? (string) $participantCount : '',
            'Lieferdatum' => $this->formatDateDe($block2['delivery_date'] ?? null),
            'Datum der Rücklieferung' => $this->formatDateDe($block2['return_date'] ?? null),
            'JSCoach Name Vorname PersonenNr' => $this->formatCoach($block2),
            'Bezeichnung Lieferort Gebäudename' => $this->str($block3['venue_name'] ?? ''),
            'Name_2' => $this->str($block3['contact_last_name'] ?? ''),
            'Vorname_2' => $this->str($block3['contact_first_name'] ?? ''),
            'Adresse_2' => $this->str($block3['address'] ?? ''),
            'PLZOrt_2' => $this->joinPostalCity($block3['postal_code'] ?? '', $block3['city'] ?? ''),
            'Kt_2' => $this->str($block3['canton'] ?? ''),
            'Tel Nr erreichbar am Tag der Lieferung' => $this->str($block3['delivery_phone'] ?? ''),
            'Tel Nr Lagerleitung erreichbar im Lager' => $this->str($block3['camp_leader_phone'] ?? ''),
            'Datum' => (new \DateTimeImmutable())->format('d.m.Y'),
        ];

        if ($courseType === 'lager') {
            $fields['Lager'] = 'Yes';
        } elseif ($courseType === 'kaderbildung') {
            $fields['Kaderbildung'] = 'Yes';
        }

        if ($order->getDeliveryType() === ActivityJsOrder::DELIVERY_PICKUP_THUN) {
            $fields['Wird im Logistik Center Thun abgeholt'] = 'Yes';
        } else {
            $fields['FrankoDomizil'] = 'Yes';
        }

        foreach ($this->mapMaterialQuantities($order) as $pdfField => $qty) {
            if ($qty > 0) {
                $fields[$pdfField] = (string) $qty;
            }
        }

        return array_filter(
            $fields,
            static fn (string $value): bool => $value !== '',
        );
    }

    /**
     * @return array<string, int>
     */
    private function mapMaterialQuantities(ActivityJsOrder $order): array
    {
        $byField = [];

        foreach ($order->getItems() as $item) {
            if (!$item instanceof ActivityJsOrderItem) {
                continue;
            }
            $qty = $item->getQuantityOrdered();
            if ($qty < 1) {
                continue;
            }
            $name = $this->normalizeName($item->getMaterialItem()->getName());
            $field = $this->resolveMaterialField($name);
            if ($field === null) {
                continue;
            }
            $byField[$field] = ($byField[$field] ?? 0) + $qty;
        }

        return $byField;
    }

    private function resolveMaterialField(string $normalizedName): ?string
    {
        foreach (self::MATERIAL_ROWS as $row) {
            if ($this->nameMatchesPatterns($normalizedName, $row['patterns'], $row['match_all'])) {
                return $row['field'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $patterns
     */
    private function nameMatchesPatterns(string $name, array $patterns, bool $matchAll): bool
    {
        if ($patterns === []) {
            return false;
        }

        $normalizedPatterns = array_map(fn (string $p): string => $this->normalizeName($p), $patterns);

        if ($matchAll) {
            foreach ($normalizedPatterns as $pattern) {
                if (!str_contains($name, $pattern)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($normalizedPatterns as $pattern) {
            if (str_contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $block2 */
    private function formatCoach(array $block2): string
    {
        $first = $this->str($block2['coach_first_name'] ?? '');
        $last = $this->str($block2['coach_last_name'] ?? '');
        $personNr = $this->str($block2['coach_person_nr'] ?? '');
        $name = trim($first . ' ' . $last);
        if ($name === '' && $personNr === '') {
            return '';
        }
        if ($personNr === '') {
            return $name;
        }
        if ($name === '') {
            return $personNr;
        }

        return $name . ', ' . $personNr;
    }

    private function joinPostalCity(mixed $postal, mixed $city): string
    {
        return trim($this->str($postal) . ' ' . $this->str($city));
    }

    private function formatDateDe(mixed $iso): string
    {
        $raw = $this->str($iso);
        if ($raw === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($raw))->format('d.m.Y');
        } catch (\Exception) {
            return $raw;
        }
    }

    private function str(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function normalizeName(string $name): string
    {
        $n = mb_strtolower(trim($name));

        return ' ' . str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $n) . ' ';
    }
}
