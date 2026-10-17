<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Fachlicher Konflikt der Helferzuteilung (HTTP 409, siehe {@see \App\EventSubscriber\GrossanlassAvailabilityConflictSubscriber}).
 * Kein RuntimeException: viele Controller übersetzen diese als HTTP 403.
 */
final class GrossanlassStaffingConflict extends \DomainException
{
    /** Der Helfer ist im Zeitfenster bereits zugeteilt; nur mit begründeter Ausnahme (GA-HV/GA-SUBHV) zulässig. */
    public const KIND_DOUBLE_BOOKING = 'double_booking';
    /** Keine erfasste Verfügbarkeit deckt das Zeitfenster; nicht überschreibbar. */
    public const KIND_NO_AVAILABILITY = 'no_availability';
    public const KIND_OUTSIDE_NEED_WINDOW = 'outside_need_window';
    public const KIND_NEED_UNDATED = 'need_undated';
    public const KIND_HELPER_ARCHIVED = 'helper_archived';
    /** Bedarf oder Verfügbarkeit hat aktive Zuteilungen und kann so nicht geändert oder gelöscht werden. */
    public const KIND_IN_USE = 'in_use';
    public const KIND_STALE = 'stale';

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message,
        private readonly string $kind,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'error' => $this->getMessage(),
            'code' => 'staffing_conflict',
            'conflict' => ['kind' => $this->kind] + $this->details,
        ];
    }
}
