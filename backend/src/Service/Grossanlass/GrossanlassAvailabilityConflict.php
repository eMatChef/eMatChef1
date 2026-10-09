<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Buchung oder Ausgabe würde die Charge überbuchen bzw. übersteigt die physisch vorhandene Menge. Die Controller melden HTTP 409.
 */
final class GrossanlassAvailabilityConflict extends \RuntimeException
{
    public const KIND_OVERBOOKED = 'overbooked';
    public const KIND_UNIQUE = 'unique_overlap';
    public const KIND_NOT_ON_HAND = 'not_on_hand';
    public const KIND_BELOW_BOOKED = 'below_booked';

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message,
        private readonly string $kind,
        private readonly array $details,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'error' => $this->getMessage(),
            'code' => 'availability_conflict',
            'conflict' => ['kind' => $this->kind] + $this->details,
        ];
    }
}
