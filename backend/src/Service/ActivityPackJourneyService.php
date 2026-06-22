<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;

/**
 * Zentraler Journey-Checkpoint (Packliste) — unabhängig vom groben activity.status.
 */
class ActivityPackJourneyService
{
    public const STEP_PACK = 'pack';
    public const STEP_TRANSPORT_OUT = 'transport_out';
    public const STEP_ISSUE = 'issue';
    public const STEP_TRANSPORT_BACK = 'transport_back';
    public const STEP_RETURN = 'return';
    public const STEP_STORE = 'store';

    public function __construct(
        private PackPipelineService $packPipeline,
    ) {}

    public function profileForActivity(Activity $activity): string
    {
        return $this->packPipeline->profileForActivityType($activity->getType() ?? 'activity');
    }

    /** @return list<string> */
    public function journeyStepsForProfile(string $profile): array
    {
        if ($profile === PackPipelineService::PROFILE_LOGISTICS) {
            return [
                self::STEP_PACK,
                self::STEP_TRANSPORT_OUT,
                self::STEP_ISSUE,
                self::STEP_TRANSPORT_BACK,
                self::STEP_RETURN,
                self::STEP_STORE,
            ];
        }

        return [self::STEP_PACK, self::STEP_ISSUE, self::STEP_RETURN, self::STEP_STORE];
    }

    public function defaultStepForStatus(string $status, string $profile, bool $canManageMaterials = false): string
    {
        if ($status === Activity::STATUS_PACKING) {
            return self::STEP_PACK;
        }
        if ($status === Activity::STATUS_PACKED) {
            return $profile === PackPipelineService::PROFILE_LOGISTICS
                ? self::STEP_TRANSPORT_OUT
                : self::STEP_ISSUE;
        }
        if ($status === Activity::STATUS_AT_EVENT) {
            return $profile === PackPipelineService::PROFILE_LOGISTICS
                ? self::STEP_TRANSPORT_BACK
                : self::STEP_RETURN;
        }
        if ($status === Activity::STATUS_RETURNED || $status === Activity::STATUS_COMPLETED) {
            return $canManageMaterials ? self::STEP_STORE : self::STEP_RETURN;
        }

        return self::STEP_PACK;
    }

    public function resolveActiveStep(Activity $activity, bool $canManageMaterials = false): string
    {
        $profile = $this->profileForActivity($activity);
        $stored = trim((string) ($activity->getPackJourneyStep() ?? ''));
        if ($stored !== '' && $this->isValidStep($stored, $profile)) {
            return $this->clampStepToStatus($stored, $activity->getStatus(), $profile, $canManageMaterials);
        }

        return $this->defaultStepForStatus($activity->getStatus(), $profile, $canManageMaterials);
    }

    public function syncStepOnStatusChange(Activity $activity, string $newStatus, bool $canManageMaterials = false): void
    {
        $profile = $this->profileForActivity($activity);
        $next = $this->defaultStepForStatus($newStatus, $profile, $canManageMaterials);

        if (!\in_array($newStatus, [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_COMPLETED,
        ], true)) {
            $activity->setPackJourneyStep(null);

            return;
        }

        $activity->setPackJourneyStep($next);
    }

    public function advanceToStep(Activity $activity, string $targetStep, bool $canManageMaterials = false): ?string
    {
        $profile = $this->profileForActivity($activity);
        if (!$this->isValidStep($targetStep, $profile)) {
            return 'Ungültiger Pack-Schritt.';
        }

        $active = $this->resolveActiveStep($activity, $canManageMaterials);
        $steps = $this->journeyStepsForProfile($profile);
        $activeIdx = array_search($active, $steps, true);
        $targetIdx = array_search($targetStep, $steps, true);
        if ($activeIdx === false || $targetIdx === false) {
            return 'Ungültiger Pack-Schritt.';
        }

        if ($targetIdx !== $activeIdx + 1) {
            return 'Es kann nur der nächste Pack-Schritt freigeschaltet werden.';
        }

        $maxStep = $this->maxStepForStatus($activity->getStatus(), $profile, $canManageMaterials);
        $maxIdx = array_search($maxStep, $steps, true);
        if ($maxIdx === false || $targetIdx > $maxIdx) {
            return 'Dieser Schritt ist für den aktuellen Aktivitäts-Status noch nicht verfügbar.';
        }

        $activity->setPackJourneyStep($targetStep);
        $activity->setUpdatedAt(new \DateTime());

        return null;
    }

    public function maxStepForStatus(string $status, string $profile, bool $canManageMaterials = false): string
    {
        if ($status === Activity::STATUS_PACKING) {
            return self::STEP_PACK;
        }
        if ($status === Activity::STATUS_PACKED) {
            return $profile === PackPipelineService::PROFILE_LOGISTICS
                ? self::STEP_ISSUE
                : self::STEP_ISSUE;
        }
        if ($status === Activity::STATUS_AT_EVENT) {
            return $profile === PackPipelineService::PROFILE_LOGISTICS
                ? self::STEP_RETURN
                : self::STEP_RETURN;
        }
        if ($status === Activity::STATUS_RETURNED) {
            return $canManageMaterials ? self::STEP_STORE : self::STEP_RETURN;
        }
        if ($status === Activity::STATUS_COMPLETED) {
            return $canManageMaterials ? self::STEP_STORE : self::STEP_RETURN;
        }

        return self::STEP_PACK;
    }

    /** Schritte, die per «Weiter»-Button explizit freigeschaltet werden. */
    public function isAdvanceableFromStep(string $step, string $profile): bool
    {
        if ($profile === PackPipelineService::PROFILE_LOGISTICS) {
            return \in_array($step, [self::STEP_TRANSPORT_OUT, self::STEP_TRANSPORT_BACK], true);
        }

        return false;
    }

    public function nextStepAfter(string $step, string $profile): ?string
    {
        $steps = $this->journeyStepsForProfile($profile);
        $idx = array_search($step, $steps, true);
        if ($idx === false || $idx >= \count($steps) - 1) {
            return null;
        }

        return $steps[$idx + 1];
    }

    private function isValidStep(string $step, string $profile): bool
    {
        return \in_array($step, $this->journeyStepsForProfile($profile), true);
    }

    private function clampStepToStatus(
        string $step,
        string $status,
        string $profile,
        bool $canManageMaterials,
    ): string {
        $steps = $this->journeyStepsForProfile($profile);
        $stepIdx = array_search($step, $steps, true);
        $maxStep = $this->maxStepForStatus($status, $profile, $canManageMaterials);
        $maxIdx = array_search($maxStep, $steps, true);
        if ($stepIdx === false || $maxIdx === false) {
            return $this->defaultStepForStatus($status, $profile, $canManageMaterials);
        }
        if ($stepIdx > $maxIdx) {
            return $maxStep;
        }

        return $step;
    }
}
