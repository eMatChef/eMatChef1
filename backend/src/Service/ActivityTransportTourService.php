<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityTransportTour;
use App\Entity\ActivityTransportTourItem;
use App\Entity\DepartmentVehicle;
use App\Entity\MaterialItem;
use Doctrine\ORM\EntityManagerInterface;

class ActivityTransportTourService
{
    private const TOUR_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function suggestTourLabel(Activity $activity, string $direction, DepartmentVehicle $vehicle): string
    {
        $existing = $this->entityManager->getRepository(ActivityTransportTour::class)->findBy(
            ['activityId' => $activity->getId(), 'direction' => $direction],
            ['sortOrder' => 'ASC', 'createdAt' => 'ASC'],
        );

        $letterIndex = \count($existing);
        $letter = self::TOUR_LETTERS[$letterIndex] ?? ('T' . ($letterIndex + 1));
        $base = 'Tour ' . $letter;

        $sameVehicleCount = 0;
        foreach ($existing as $tour) {
            if ($tour->getVehicleId() === $vehicle->getId()) {
                ++$sameVehicleCount;
            }
        }

        if ($sameVehicleCount > 0) {
            return sprintf('%s (%s — %d. Fahrt)', $base, $vehicle->getName(), $sameVehicleCount + 1);
        }

        return $base;
    }

    /**
     * @param ActivityTransportTourItem[] $items
     *
     * @return array{
     *   estimated_weight_kg: float,
     *   estimated_volume_m3: float|null,
     *   max_payload_kg: float|null,
     *   max_volume_m3: float|null,
     *   fit: 'ok'|'heavy'|'unknown'
     * }
     */
    public function computeLoadSummary(ActivityTransportTour $tour, array $items): array
    {
        $vehicle = $tour->getVehicle();
        $weight = 0.0;
        $volume = 0.0;
        $hasVolume = false;

        foreach ($items as $item) {
            $qty = max(1, (int) ($item->getQuantity() ?? 1));
            if ($item->getPackContainerId()) {
                $weight += $this->estimateContainerWeightKg($item->getPackContainerId()) * $qty;
                continue;
            }
            if ($item->getPackItemId()) {
                $pi = $this->entityManager->getRepository(ActivityPackItem::class)->find($item->getPackItemId());
                if ($pi?->getMaterialItem()) {
                    $weight += $this->parseWeightKg($pi->getMaterialItem()) * $qty;
                }
            }
        }

        $maxPayload = $this->parseDecimal($vehicle->getMaxPayloadKg());
        $maxVolume = $this->parseDecimal($vehicle->getMaxVolumeM3());

        $fit = 'unknown';
        if ($maxPayload !== null && $maxPayload > 0) {
            $fit = $weight > $maxPayload ? 'heavy' : 'ok';
        }

        return [
            'estimated_weight_kg' => round($weight, 2),
            'estimated_volume_m3' => $hasVolume ? round($volume, 3) : null,
            'max_payload_kg' => $maxPayload,
            'max_volume_m3' => $maxVolume,
            'fit' => $fit,
        ];
    }

    private function estimateContainerWeightKg(string $containerId): float
    {
        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)->findBy([
            'packContainerId' => $containerId,
        ]);
        $sum = 0.0;
        foreach ($items as $ci) {
            $mi = $ci->getMaterialItem();
            if (!$mi) {
                continue;
            }
            $qty = max(0, (int) ($ci->getQuantityPacked() ?? 0));
            if ($qty < 1) {
                $qty = 1;
            }
            $sum += $this->parseWeightKg($mi) * $qty;
        }

        return $sum > 0 ? $sum : 15.0;
    }

    private function parseWeightKg(?MaterialItem $material): float
    {
        if ($material === null) {
            return 0.0;
        }
        $raw = trim((string) ($material->getWeight() ?? ''));
        if ($raw === '') {
            return 1.0;
        }
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $raw) ?? '');
        $n = (float) $normalized;

        return $n > 0 ? $n : 1.0;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $n = (float) str_replace(',', '.', $value);

        return $n > 0 ? $n : null;
    }

    public function journeyDirectionFromStep(string $step): ?string
    {
        return match ($step) {
            'transport_out' => ActivityTransportTour::DIRECTION_OUTBOUND,
            'transport_back' => ActivityTransportTour::DIRECTION_INBOUND,
            default => null,
        };
    }
}
