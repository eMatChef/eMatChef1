<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Das Hinzufügen würde eine manuell festgelegte oder eingefrorene Positionsmenge berühren
 * und braucht eine bewusste Bestätigung (confirm_quantity_change).
 */
final class GrossanlassQuantityConfirmationRequiredException extends \InvalidArgumentException
{
    public function __construct(
        public readonly int $currentQuantity,
        public readonly int $sourceQuantity,
        public readonly int $addedQuantity,
        public readonly bool $frozen,
    ) {
        parent::__construct('Die Menge der Position wurde manuell festgelegt; die Änderung des Bedarfs muss bestätigt werden');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'error' => $this->getMessage(),
            'code' => 'quantity_confirmation_required',
            'current_quantity' => $this->currentQuantity,
            'source_quantity' => $this->sourceQuantity,
            'added_quantity' => $this->addedQuantity,
            'frozen' => $this->frozen,
            // Eingefrorene Positionen behalten ihre Menge; es gibt keinen Vorschlag.
            'proposed_quantity' => $this->frozen ? null : $this->currentQuantity + $this->addedQuantity,
        ];
    }
}
