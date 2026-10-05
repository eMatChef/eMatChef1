<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Repository\MiDataDepartmentOnboardingRepository;
use App\Service\Support\UnassignedUserQueueExclusion;

/**
 * An open, not expired MiData onboarding offer replaces the generic "Benutzer ohne Zuordnung" process.
 */
final class MiDataOnboardingQueueExclusion implements UnassignedUserQueueExclusion
{
    public function __construct(
        private readonly MiDataDepartmentOnboardingRepository $onboardings,
    ) {}

    public function excludedUserIds(array $userIds): array
    {
        return $this->onboardings->findUserIdsWithOpenOffer($userIds, new \DateTime());
    }
}
