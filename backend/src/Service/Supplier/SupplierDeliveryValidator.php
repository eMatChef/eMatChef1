<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\SupplierCatalogItem;
use App\Entity\SupplierDelivery;
use App\Entity\SupplierDeliveryLine;

/**
 * Validierung für Supplier-Übergaben vor Submit.
 */
class SupplierDeliveryValidator
{
    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validateForSubmit(SupplierDelivery $delivery): array
    {
        $errors = [];
        $warnings = [];

        if ($delivery->getLines()->count() === 0) {
            $errors[] = 'Mindestens eine Zeile ist erforderlich';
        }

        $allSerials = [];

        foreach ($delivery->getLines() as $index => $line) {
            if (!$line instanceof SupplierDeliveryLine) {
                continue;
            }
            $lineNo = $index + 1;
            $catalog = $line->getCatalogItem();

            if ($line->getQty() < 1) {
                $errors[] = "Zeile {$lineNo}: Menge muss mindestens 1 sein";
            }

            if ($catalog->getTrackingType() === SupplierCatalogItem::TRACKING_SERIALIZED) {
                $serials = $line->getSerialNumbers();
                if (\count($serials) === 0) {
                    $errors[] = "Zeile {$lineNo} ({$catalog->getName()}): Seriennummern fehlen";
                } elseif (\count($serials) !== $line->getQty()) {
                    $errors[] = "Zeile {$lineNo} ({$catalog->getName()}): Anzahl Seriennummern ("
                        . \count($serials) . ') muss der Menge (' . $line->getQty() . ') entsprechen';
                }

                $seenInLine = [];
                foreach ($serials as $serial) {
                    if (isset($seenInLine[$serial])) {
                        $warnings[] = "Zeile {$lineNo}: Seriennummer «{$serial}» ist doppelt";
                    }
                    $seenInLine[$serial] = true;

                    if (isset($allSerials[$serial])) {
                        $warnings[] = "Seriennummer «{$serial}» kommt in mehreren Zeilen vor";
                    }
                    $allSerials[$serial] = true;
                }
            } elseif (\count($line->getSerialNumbers()) > 0) {
                $warnings[] = "Zeile {$lineNo} ({$catalog->getName()}): Seriennummern werden bei bulk-Artikeln ignoriert";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
