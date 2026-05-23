<?php

namespace App\Service;

use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;

/**
 * Mengen-Pipeline: ordered → packed → transport_to → at_event (issued) → transport_back → returned → stored.
 * Profile steuern, ob Zwischenschritte in einem Move übersprungen werden.
 */
class PackPipelineService
{
    public const STAGE_PACKED = 'packed';
    public const STAGE_TRANSPORT_TO = 'transport_to';
    public const STAGE_AT_EVENT = 'at_event';
    /** @deprecated Alias für at_event */
    public const STAGE_ISSUED = 'issued';
    public const STAGE_TRANSPORT_BACK = 'transport_back';
    public const STAGE_RETURNED = 'returned';
    public const STAGE_STORED = 'stored';

    public const PROFILE_LOGISTICS = 'logistics';
    public const PROFILE_EXTERNAL = 'external';
    public const PROFILE_QUICK = 'quick';

    public function profileForActivityType(string $activityType): string
    {
        if ($activityType === 'activity') {
            return self::PROFILE_QUICK;
        }
        if ($activityType === 'external') {
            return self::PROFILE_EXTERNAL;
        }

        return self::PROFILE_LOGISTICS;
    }

    public function normalizeStage(string $stage): string
    {
        return $stage === self::STAGE_ISSUED ? self::STAGE_AT_EVENT : $stage;
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

    public function maxForwardQty(ActivityPackItem $item, string $stage, string $profile): int
    {
        $stage = $this->normalizeStage($stage);

        return match ($stage) {
            self::STAGE_PACKED => max(0, $item->getQuantityOrdered() - $item->getQuantityPacked()),
            self::STAGE_TRANSPORT_TO => $this->maxTransportTo($item, $profile),
            self::STAGE_AT_EVENT => $this->maxAtEvent($item, $profile),
            self::STAGE_TRANSPORT_BACK => max(0, $item->getQuantityIssued() - $item->getQuantityTransportBack()),
            self::STAGE_RETURNED => $this->maxReturned($item, $profile),
            self::STAGE_STORED => max(0, $item->getQuantityReturned() - $item->getQuantityStored()),
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
            self::STAGE_STORED => $item->setQuantityStored($item->getQuantityStored() + $qty),
            default => null,
        };
    }

    public function maxBackwardQty(ActivityPackItem $item, string $stage): int
    {
        $stage = $this->normalizeStage($stage);

        return match ($stage) {
            self::STAGE_PACKED => max(0, $item->getQuantityPacked() - $item->getQuantityTransportTo()),
            self::STAGE_TRANSPORT_TO => max(0, $item->getQuantityTransportTo() - $item->getQuantityIssued()),
            self::STAGE_AT_EVENT => max(0, $item->getQuantityIssued() - $item->getQuantityTransportBack()),
            self::STAGE_TRANSPORT_BACK => max(0, $item->getQuantityTransportBack() - $item->getQuantityReturned()),
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
            self::STAGE_STORED => $item->setQuantityStored($item->getQuantityStored() + $qty),
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
            self::STAGE_STORED => max(0, $item->getQuantityReturned() - $item->getQuantityStored()),
            default => 0,
        };
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
            $item->setQuantityTransportTo(max($item->getQuantityTransportTo(), $item->getQuantityIssued()));
        } elseif ($profile === self::PROFILE_EXTERNAL) {
            $item->setQuantityTransportTo(max($item->getQuantityTransportTo(), $item->getQuantityIssued()));
        }
    }

    private function applyForwardReturned(ActivityPackItem $item, int $qty, string $profile): void
    {
        $item->setQuantityReturned($item->getQuantityReturned() + $qty);

        if ($profile === self::PROFILE_QUICK || $profile === self::PROFILE_EXTERNAL) {
            $item->setQuantityTransportBack(max($item->getQuantityTransportBack(), $item->getQuantityReturned()));
        }
    }

    private function applyForwardAtEventContainer(ActivityPackContainerItem $item, int $qty, string $profile): void
    {
        $item->setQuantityIssued($item->getQuantityIssued() + $qty);

        if ($profile === self::PROFILE_QUICK || $profile === self::PROFILE_EXTERNAL) {
            $item->setQuantityTransportTo(max($item->getQuantityTransportTo(), $item->getQuantityIssued()));
        }
    }

    private function applyForwardReturnedContainer(ActivityPackContainerItem $item, int $qty, string $profile): void
    {
        $item->setQuantityReturned($item->getQuantityReturned() + $qty);

        if ($profile === self::PROFILE_QUICK || $profile === self::PROFILE_EXTERNAL) {
            $item->setQuantityTransportBack(max($item->getQuantityTransportBack(), $item->getQuantityReturned()));
        }
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
