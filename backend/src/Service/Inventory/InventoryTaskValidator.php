<?php

declare(strict_types=1);

namespace App\Service\Inventory;

use App\Entity\InventoryTask;

final class InventoryTaskValidator
{
    /**
     * @param array<string, mixed>|null $raw
     *
     * @return array<string, mixed>
     */
    public function normalizeLinesJson(?array $raw): array
    {
        if ($raw === null) {
            return ['lines' => []];
        }

        $lines = $raw['lines'] ?? [];
        if (!\is_array($lines)) {
            throw new \InvalidArgumentException('lines_json.lines muss ein Array sein');
        }

        $normalized = [];
        foreach ($lines as $index => $line) {
            if (!\is_array($line)) {
                throw new \InvalidArgumentException(sprintf('lines_json.lines[%d] muss ein Objekt sein', $index));
            }

            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId === '') {
                $lineId = 'line_' . ($index + 1);
            }

            $materialItemId = trim((string) ($line['material_item_id'] ?? ''));
            $materialName = trim((string) ($line['material_name'] ?? ''));
            $expectedQty = $this->parseQuantity($line['expected_qty'] ?? null, 'expected_qty', $index);
            $countedQty = \array_key_exists('counted_qty', $line)
                ? $this->parseNullableQuantity($line['counted_qty'], 'counted_qty', $index)
                : null;

            $normalized[] = array_filter([
                'id' => $lineId,
                'material_item_id' => $materialItemId !== '' ? $materialItemId : null,
                'material_name' => $materialName !== '' ? $materialName : null,
                'expected_qty' => $expectedQty,
                'counted_qty' => $countedQty,
                'notes' => $this->nullableString($line['notes'] ?? null),
            ], static fn ($value) => $value !== null);
        }

        return ['lines' => $normalized];
    }

    public function validateStatus(string $status): void
    {
        if (!\in_array($status, InventoryTask::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException('Ungültiger status');
        }
    }

    public function validateTitle(string $title): string
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('title ist erforderlich');
        }
        if (mb_strlen($trimmed) > 200) {
            throw new \InvalidArgumentException('title darf maximal 200 Zeichen lang sein');
        }

        return $trimmed;
    }

    private function parseQuantity(mixed $value, string $field, int $index): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('lines_json.lines[%d].%s muss eine Zahl sein', $index, $field));
        }
        $qty = (float) $value;
        if ($qty < 0) {
            throw new \InvalidArgumentException(sprintf('lines_json.lines[%d].%s darf nicht negativ sein', $index, $field));
        }

        return $qty;
    }

    private function parseNullableQuantity(mixed $value, string $field, int $index): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->parseQuantity($value, $field, $index);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
