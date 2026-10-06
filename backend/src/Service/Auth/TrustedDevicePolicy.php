<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;

/** Zentrale Vertrauensdauer: globale Security-Admins 30 Tage, alle anderen 90 Tage. Es gilt immer die aktuelle Rolle. */
class TrustedDevicePolicy
{
    public const DAYS_USER = 90;
    public const DAYS_ADMIN = 30;

    public function __construct(
        private readonly AdminCapabilityChecker $adminCapabilityChecker,
    ) {}

    public function daysFor(User $user): int
    {
        return $this->adminCapabilityChecker->hasGlobalAdminRole($user) ? self::DAYS_ADMIN : self::DAYS_USER;
    }
}
