<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\UserSession;
use App\Service\Admin\AdminCapabilityChecker;

/**
 * Entscheidet, ob eine Sitzung eine geschützte Adminfunktion ausführen darf (siehe AdminMfaPolicy).
 * Geprüft wird die Sitzung, nicht nur der User: «hat TOTP» genügt nicht, die Sitzung muss MFA-verifiziert sein.
 */
class AdminMfaGuard
{
    public const MFA_SETUP_REQUIRED = 'mfa_setup_required';
    public const MFA_REQUIRED = 'mfa_required';
    public const STEP_UP_REQUIRED = 'step_up_required';

    public function __construct(
        private readonly AdminCapabilityChecker $adminCapabilityChecker,
        private readonly TotpService $totpService,
    ) {}

    /**
     * @param AdminMfaPolicy::LEVEL_* $level
     *
     * @return self::*|null null = erlaubt
     */
    public function denialReason(User $user, ?UserSession $session, string $level, ?\DateTime $now = null): ?string
    {
        if (!$this->adminCapabilityChecker->hasGlobalAdminRole($user)) {
            return null;
        }
        if (!$this->totpService->isEnabled($user)) {
            return self::MFA_SETUP_REQUIRED;
        }
        // Sitzungen ohne sid (Legacy) oder ohne MFA-Nachweis, z. B. vor der TOTP-Aktivierung entstanden.
        if ($session === null || $session->getMfaVerifiedAt() === null) {
            return self::MFA_REQUIRED;
        }
        if ($level === AdminMfaPolicy::LEVEL_STEP_UP && !$session->hasFreshStepUp(StepUpService::FRESHNESS_SECONDS, $now)) {
            return self::STEP_UP_REQUIRED;
        }

        return null;
    }
}
