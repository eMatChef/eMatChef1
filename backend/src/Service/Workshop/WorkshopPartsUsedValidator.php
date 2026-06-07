<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\MaterialItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Validiert workshop_ticket.parts_used (Stückliste Nicht-Zelt).
 *
 * Schema pro Zeile:
 * - id: string (client-seitige Zeilen-ID)
 * - material_item_id: string (12 Zeichen)
 * - material_name: string|null (Anzeige, optional)
 * - quantity: number (> 0)
 * - source: stock|purchase|rest
 * - available_qty: number|null (Vorrat MW bei source=rest)
 * - quantity_unit: string|null (z. B. Stk, m)
 * - status: planned|ordered|received|consumed
 * - unit_cost: string|null (Referenz-EK/Stk.)
 */
class WorkshopPartsUsedValidator
{
    public const SOURCE_STOCK = 'stock';
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_REST = 'rest';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CONSUMED = 'consumed';

    private const ALL_SOURCES = [
        self::SOURCE_STOCK,
        self::SOURCE_PURCHASE,
        self::SOURCE_REST,
    ];

    private const ALL_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_ORDERED,
        self::STATUS_RECEIVED,
        self::STATUS_CONSUMED,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkshopSparePartsCategoryBootstrapService $sparePartsCategoryBootstrap,
    ) {
    }

    /**
     * @param mixed $partsUsed
     *
     * @return list<string> leer = ok
     */
    public function validate(mixed $partsUsed, string $departmentId): array
    {
        if ($partsUsed === null) {
            return [];
        }

        if (!\is_array($partsUsed)) {
            return ['parts_used muss ein Array sein'];
        }

        $sparePartsCategoryId = $this->resolveSparePartsCategoryId($departmentId);
        $errors = [];
        $seenMaterialIds = [];

        foreach ($partsUsed as $index => $line) {
            $prefix = 'parts_used['.$index.']';

            if (!\is_array($line)) {
                $errors[] = $prefix.' muss ein Objekt sein';
                continue;
            }

            $materialId = trim((string) ($line['material_item_id'] ?? ''));
            if ($materialId === '' || !preg_match('/^[a-z0-9]{12}$/', $materialId)) {
                $errors[] = $prefix.'.material_item_id ist ungültig';
                continue;
            }

            if (isset($seenMaterialIds[$materialId])) {
                $errors[] = $prefix.': Material darf nur einmal vorkommen';
                continue;
            }
            $seenMaterialIds[$materialId] = true;

            $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
            if (!$material instanceof MaterialItem) {
                $errors[] = $prefix.': Material nicht gefunden';
                continue;
            }

            if ($material->getDepartmentId() !== $departmentId) {
                $errors[] = $prefix.': Material gehört nicht zu diesem Department';
                continue;
            }

            $source = trim((string) ($line['source'] ?? self::SOURCE_STOCK));
            if (!\in_array($source, self::ALL_SOURCES, true)) {
                $errors[] = $prefix.'.source ist ungültig';
                continue;
            }

            if ($sparePartsCategoryId !== '' && $source !== self::SOURCE_STOCK) {
                $categoryId = $material->getCategoryId();
                if ($categoryId !== $sparePartsCategoryId) {
                    $errors[] = $prefix.': Material ist nicht in der Ersatzteile-Kategorie';
                }
            }

            $quantity = $line['quantity'] ?? null;
            if (!is_numeric($quantity) || (float) $quantity <= 0) {
                $errors[] = $prefix.'.quantity muss grösser als 0 sein';
            }

            $status = trim((string) ($line['status'] ?? ''));
            if (!\in_array($status, self::ALL_STATUSES, true)) {
                $errors[] = $prefix.'.status ist ungültig';
            }

            if (\array_key_exists('unit_cost', $line) && $line['unit_cost'] !== null && $line['unit_cost'] !== '') {
                if (!is_numeric($line['unit_cost']) || (float) $line['unit_cost'] < 0) {
                    $errors[] = $prefix.'.unit_cost ist ungültig';
                }
            }

            if ($source === self::SOURCE_REST) {
                if (\array_key_exists('available_qty', $line) && $line['available_qty'] !== null && $line['available_qty'] !== '') {
                    if (!is_numeric($line['available_qty']) || (float) $line['available_qty'] < 0) {
                        $errors[] = $prefix.'.available_qty ist ungültig';
                    } elseif (is_numeric($quantity) && (float) $line['available_qty'] < (float) $quantity) {
                        $errors[] = $prefix.': Verbrauch darf den Vorrat nicht übersteigen';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param mixed $partsUsed
     *
     * @return list<array<string, mixed>>|null
     */
    public function normalize(mixed $partsUsed): ?array
    {
        if ($partsUsed === null) {
            return null;
        }

        if (!\is_array($partsUsed)) {
            return null;
        }

        $normalized = [];

        foreach ($partsUsed as $line) {
            if (!\is_array($line)) {
                continue;
            }

            $materialId = trim((string) ($line['material_item_id'] ?? ''));
            if ($materialId === '') {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $source = trim((string) ($line['source'] ?? self::SOURCE_STOCK));
            if (!\in_array($source, self::ALL_SOURCES, true)) {
                $source = self::SOURCE_STOCK;
            }

            $status = trim((string) ($line['status'] ?? self::STATUS_PLANNED));
            if (!\in_array($status, self::ALL_STATUSES, true)) {
                $status = self::STATUS_PLANNED;
            }

            $unitCost = $line['unit_cost'] ?? null;
            $unitCostNormalized = null;
            if ($unitCost !== null && $unitCost !== '' && is_numeric($unitCost)) {
                $unitCostNormalized = number_format((float) $unitCost, 2, '.', '');
            }

            $entry = [
                'id' => trim((string) ($line['id'] ?? '')) ?: bin2hex(random_bytes(8)),
                'material_item_id' => $materialId,
                'material_name' => trim((string) ($line['material_name'] ?? '')) ?: null,
                'quantity' => $quantity,
                'source' => $source,
                'status' => $status,
                'unit_cost' => $unitCostNormalized,
            ];

            foreach ([
                'supplier_id',
                'purchase_location',
                'document_date',
                'ordered_at',
                'received_at',
                'material_batch_id',
                'receipt_url',
            ] as $optionalKey) {
                if (\array_key_exists($optionalKey, $line)) {
                    $val = $line[$optionalKey];
                    $entry[$optionalKey] = \is_string($val) ? (trim($val) ?: null) : $val;
                }
            }

            if (isset($line['purchase_total']) && is_numeric($line['purchase_total'])) {
                $entry['purchase_total'] = number_format((float) $line['purchase_total'], 2, '.', '');
            }

            if (isset($line['surplus_qty']) && is_numeric($line['surplus_qty'])) {
                $sq = (float) $line['surplus_qty'];
                if ($sq >= 0) {
                    $entry['surplus_qty'] = $sq;
                }
            }

            if (isset($line['available_qty']) && is_numeric($line['available_qty'])) {
                $aq = (float) $line['available_qty'];
                if ($aq >= 0) {
                    $entry['available_qty'] = $aq;
                }
            }

            $quantityUnit = trim((string) ($line['quantity_unit'] ?? ''));
            if ($quantityUnit !== '') {
                $entry['quantity_unit'] = $quantityUnit;
            }

            $normalized[] = $entry;
        }

        return $normalized === [] ? [] : $normalized;
    }

    private function resolveSparePartsCategoryId(string $departmentId): string
    {
        return $this->sparePartsCategoryBootstrap->ensure($departmentId);
    }
}
