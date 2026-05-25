<?php

namespace App\Service;

use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;

/**
 * Mengen-Pipeline: ordered → packed → transport_to → at_event → transport_back → returned → stored.
 * Profile steuern, ob Zwischenschritte in einem Move übersprungen werden.
 */
class PackPipelineService
{
    public const STAGE_PACKED = 'packed';
    public const STAGE_TRANSPORT_TO = 'transport_to';
    public const STAGE_AT_EVENT = 'at_event';
    public const STAGE_TRANSPORT_BACK = 'transport_back';
    public const STAGE_RETURNED = 'returned';
    public const STAGE_STORED = 'stored';

    public const PROFILE_LOGISTICS = 'logistics';
    public const PROFILE_EXTERNAL = 'external';
    public const PROFILE_QUICK = 'quick';

    public function profileForActivityType(string $activityType): string
    {
        if ($activityType === 'activity' || $activityType === 'external') {
            return self::PROFILE_QUICK;
        }

        return self::PROFILE_LOGISTICS;
    }

    public function normalizeStage(string $stage): string
    {
        return $stage;
    }

    /** @return list<string> */
    public static function allForwardStages(): array
    {
        return [
            self::STAGE_PACKED,
            self::STAGE_TRANSPORT_TO,
            self::STAGE_AT_EVENT,
            self::STAGE_TRANSPORT_BACK,
            self::STAGE_RETURNED,
            self::STAGE_STORED,
        ];
    }

    /**
     * @param int $consumableConsumedQty Summe Verbrauchsmeldungen (TYPE_CONSUMPTION) für dieses Material
     */
    public function maxForwardQty(
        ActivityPackItem $item,
        string $stage,
        string $profile,
        int $consumableConsumedQty = 0,
    ): int {
        $stage = $this->normalizeStage($stage);

        return match ($stage) {
            self::STAGE_PACKED => max(0, $item->getQuantityOrdered() - $item->getQuantityPacked()),
            self::STAGE_TRANSPORT_TO => $this->maxTransportTo($item, $profile),
            self::STAGE_AT_EVENT => $this->maxAtEvent($item, $profile),
            self::STAGE_TRANSPORT_BACK => max(0, $item->getQuantityIssued() - $item->getQuantityTransportBack()),
            self::STAGE_RETURNED => $this->maxReturned($item, $profile),
            self::STAGE_STORED => $this->maxStoredForItem($item, $consumableConsumedQty),
            default => 0,
        };
    }

    public function applyForward(ActivityPackItem $item, string $stage, int $qty, string $profile): void
    {
        $stage = $this->normalizeStage($stage);
        if ($qty < 1) {
            return;
        }

        match ($stage) {
            self::STAGE_PACKED => $item->setQuantityPacked($item->getQuantityPacked() + $qty),
            self::STAGE_TRANSPORT_TO => $item->setQuantityTransportTo($item->getQuantityTransportTo() + $qty),
            self::STAGE_AT_EVENT => $this->applyForwardAtEvent($item, $qty, $profile),
            self::STAGE_TRANSPORT_BACK => $item->setQuantityTransportBack($item->getQuantityTransportBack() + $qty),
            self::STAGE_RETURNED => $this->applyForwardReturned($item, $qty, $profile),
            self::STAGE_STORED => $this->applyForwardStored($item, $qty, $profile),
            default => null,
        };
    }

    public function maxBackwardQty(ActivityPackItem $item, string $stage, string $profile): int
    {
        $stage = $this->normalizeStage($stage);
        $skipTransport = $this->skipsTransportStages($profile);

        return match ($stage) {
            self::STAGE_PACKED => $skipTransport
                ? max(0, $item->getQuantityPacked() - $item->getQuantityIssued())
                : max(0, $item->getQuantityPacked() - $item->getQuantityTransportTo()),
            self::STAGE_TRANSPORT_TO => $skipTransport
                ? 0
                : max(0, $item->getQuantityTransportTo() - $item->getQuantityIssued()),
            self::STAGE_AT_EVENT => $skipTransport
                ? max(0, $item->getQuantityIssued() - $item->getQuantityReturned())
                : max(0, $item->getQuantityIssued() - $item->getQuantityTransportBack()),
            self::STAGE_TRANSPORT_BACK => $skipTransport
                ? 0
                : max(0, $item->getQuantityTransportBack() - $item->getQuantityReturned()),
            self::STAGE_RETURNED => max(0, $item->getQuantityReturned() - $item->getQuantityStored()),
            self::STAGE_STORED => max(0, $item->getQuantityStored()),
            default => 0,
        };
    }

    public function applyBackward(ActivityPackItem $item, string $stage, int $qty): void
    {
        $stage = $this->normalizeStage($stage);
        if ($qty < 1) {
            return;
        }

        match ($stage) {
            self::STAGE_PACKED => $item->setQuantityPacked($item->getQuantityPacked() - $qty),
            self::STAGE_TRANSPORT_TO => $item->setQuantityTransportTo($item->getQuantityTransportTo() - $qty),
            self::STAGE_AT_EVENT => $item->setQuantityIssued($item->getQuantityIssued() - $qty),
            self::STAGE_TRANSPORT_BACK => $item->setQuantityTransportBack($item->getQuantityTransportBack() - $qty),
            self::STAGE_RETURNED => $item->setQuantityReturned($item->getQuantityReturned() - $qty),
            self::STAGE_STORED => $item->setQuantityStored($item->getQuantityStored() - $qty),
            default => null,
        };
    }

    public function applyForwardContainer(ActivityPackContainerItem $item, string $stage, int $qty, string $profile): void
    {
        $stage = $this->normalizeStage($stage);
        if ($qty < 1) {
            return;
        }

        match ($stage) {
            self::STAGE_PACKED => $item->setQuantityPacked($item->getQuantityPacked() + $qty),
            self::STAGE_TRANSPORT_TO => $item->setQuantityTransportTo($item->getQuantityTransportTo() + $qty),
            self::STAGE_AT_EVENT => $this->applyForwardAtEventContainer($item, $qty, $profile),
            self::STAGE_TRANSPORT_BACK => $item->setQuantityTransportBack($item->getQuantityTransportBack() + $qty),
            self::STAGE_RETURNED => $this->applyForwardReturnedContainer($item, $qty, $profile),
            self::STAGE_STORED => $this->applyForwardStoredContainer($item, $qty, $profile),
            default => null,
        };
    }

    public function maxForwardContainerQty(ActivityPackContainerItem $item, string $stage, string $profile): int
    {
        $stage = $this->normalizeStage($stage);

        return match ($stage) {
            self::STAGE_PACKED => max(0, $item->getQuantityPacked()),
            self::STAGE_TRANSPORT_TO => max(0, $item->getQuantityPacked() - $item->getQuantityTransportTo()),
            self::STAGE_AT_EVENT => max(0, $item->getQuantityTransportTo() - $item->getQuantityIssued()),
            self::STAGE_TRANSPORT_BACK => max(0, $item->getQuantityIssued() - $item->getQuantityTransportBack()),
            self::STAGE_RETURNED => $this->maxReturnedContainer($item, $profile),
            self::STAGE_STORED => $this->maxStored(
                $item->getQuantityPacked(),
                $item->getQuantityIssued(),
                $item->getQuantityReturned(),
                $item->getQuantityStored(),
            ),
            default => 0,
        };
    }

    /**
     * Einlagern: retournierte Restmenge plus gepackte, nie ans Event bewegte Stücke
     * (werden beim Einlagern automatisch als retourniert gebucht).
     */
    private function maxStored(int $packed, int $issued, int $returned, int $stored): int
    {
        $returnedPending = max(0, $returned - $stored);
        $extraReturned = max(0, $returned - $issued);
        $neverIssuedOutstanding = max(0, $packed - $issued - $extraReturned);

        return $returnedPending + $neverIssuedOutstanding;
    }

    /**
     * Verbrauchsmaterial: oft kein formaler Retour-Schritt; offene Einlager-Menge =
     * gebucht (ordered) minus gemeldeter Verbrauch minus bereits eingelagert.
     */
    private function maxStoredForItem(ActivityPackItem $item, int $consumableConsumedQty): int
    {
        $base = $this->maxStored(
            $item->getQuantityPacked(),
            $item->getQuantityIssued(),
            $item->getQuantityReturned(),
            $item->getQuantityStored(),
        );

        if ($consumableConsumedQty <= 0) {
            return $base;
        }

        $material = $item->getMaterialItem();
        if ($material === null || !$material->getIsConsumable()) {
            return $base;
        }

        $consumableCap = max(0, $item->getQuantityOrdered() - $consumableConsumedQty - $item->getQuantityStored());

        return max($base, $consumableCap);
    }

    private function applyForwardStored(ActivityPackItem $item, int $qty, string $profile): void
    {
        $returnedPending = max(0, $item->getQuantityReturned() - $item->getQuantityStored());
        $remainder = max(0, $qty - min($qty, $returnedPending));

        $item->setQuantityStored($item->getQuantityStored() + $qty);

        if ($remainder > 0) {
            $item->setQuantityReturned($item->getQuantityReturned() + $remainder);
        }
    }

    private function applyForwardStoredContainer(ActivityPackContainerItem $item, int $qty, string $profile): void
    {
        $returnedPending = max(0, $item->getQuantityReturned() - $item->getQuantityStored());
        $remainder = max(0, $qty - min($qty, $returnedPending));

        $item->setQuantityStored($item->getQuantityStored() + $qty);

        if ($remainder > 0) {
            $item->setQuantityReturned($item->getQuantityReturned() + $remainder);
        }
    }

    private function maxTransportTo(ActivityPackItem $item, string $profile): int
    {
        if ($profile === self::PROFILE_QUICK || $profile === self::PROFILE_EXTERNAL) {
            return 0;
        }

        return max(0, $item->getQuantityPacked() - $item->getQuantityTransportTo());
    }

    private function maxAtEvent(ActivityPackItem $item, string $profile): int
    {
        if ($profile === self::PROFILE_QUICK) {
            return max(0, $item->getQuantityOrdered() - $item->getQuantityIssued());
        }
        if ($profile === self::PROFILE_EXTERNAL) {
            return max(0, $item->getQuantityPacked() - $item->getQuantityIssued());
        }

        return max(0, $item->getQuantityTransportTo() - $item->getQuantityIssued());
    }

    private function applyForwardAtEvent(ActivityPackItem $item, int $qty, string $profile): void
    {
        $item->setQuantityIssued($item->getQuantityIssued() + $qty);

        if ($profile === self::PROFILE_QUICK) {
            $item->setQuantityPacked(max($item->getQuantityPacked(), $item->getQuantityIssued()));
        }
    }

    private function applyForwardReturned(ActivityPackItem $item, int $qty, string $profile): void
    {
        $item->setQuantityReturned($item->getQuantityReturned() + $qty);
    }

    private function applyForwardAtEventContainer(ActivityPackContainerItem $item, int $qty, string $profile): void
    {
        $item->setQuantityIssued($item->getQuantityIssued() + $qty);
    }

    private function applyForwardReturnedContainer(ActivityPackContainerItem $item, int $qty, string $profile): void
    {
        $item->setQuantityReturned($item->getQuantityReturned() + $qty);
    }

    private function skipsTransportStages(string $profile): bool
    {
        return $profile === self::PROFILE_QUICK || $profile === self::PROFILE_EXTERNAL;
    }

    private function maxReturned(ActivityPackItem $item, string $profile): int
    {
        if ($profile === self::PROFILE_QUICK || $profile === self::PROFILE_EXTERNAL) {
            return max(0, $item->getQuantityIssued() - $item->getQuantityReturned());
        }

        return max(0, $item->getQuantityTransportBack() - $item->getQuantityReturned());
    }

    private function maxReturnedContainer(ActivityPackContainerItem $item, string $profile): int
    {
        if ($profile === self::PROFILE_QUICK || $profile === self::PROFILE_EXTERNAL) {
            return max(0, $item->getQuantityIssued() - $item->getQuantityReturned());
        }

        return max(0, $item->getQuantityTransportBack() - $item->getQuantityReturned());
    }
}
