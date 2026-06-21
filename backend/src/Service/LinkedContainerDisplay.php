<?php

namespace App\Service;

use App\Entity\MaterialBatch;

/**
 * Anzeigename für die Referenz-Sack/Kiste einer physischen Kombo (linked_container_batch).
 * Bevorzugt Stammdaten-Name (z. B. «Zeltsack Spatz»), nicht Seriennummer.
 */
final class LinkedContainerDisplay
{
    public static function labelFromBatch(?MaterialBatch $batch): ?string
    {
        if ($batch === null) {
            return null;
        }

        return self::labelFromParts(
            $batch->getMaterialItem()->getName(),
            $batch->getLabel(),
            $batch->getSerialNumber(),
        );
    }

    public static function labelFromParts(?string $materialName, ?string $batchLabel, ?string $serialNumber): ?string
    {
        $name = trim((string) ($materialName ?? ''));
        if ($name !== '') {
            return $name;
        }

        $label = trim((string) ($batchLabel ?? ''));
        if ($label !== '') {
            return $label;
        }

        $serial = trim((string) ($serialNumber ?? ''));
        if ($serial !== '') {
            return $serial;
        }

        return null;
    }
}
