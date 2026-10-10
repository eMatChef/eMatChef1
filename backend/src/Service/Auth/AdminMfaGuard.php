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
    /** User ohne TOTP: die Sitzung ist nicht frisch angemeldet, bitte neu anmelden. */
    public const REAUTH_REQUIRED = 'reauth_required';

    /** Reauthentifizierung ohne TOTP: Die Anmeldung dieser Sitzung darf höchstens so alt sein. */
    public const RECENT_LOGIN_SECONDS = 600;

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
        $isAdmin = $this->adminCapabilityChecker->hasGlobalAdminRole($user);
        if ($level === AdminMfaPolicy::LEVEL_SELF_SENSITIVE) {
            if ($isAdmin) {
                $level = AdminMfaPolicy::LEVEL_STEP_UP;
            } elseif ($this->totpService->isEnabled($user)) {
                return $session === null || !$session->hasFreshStepUp(StepUpService::FRESHNESS_SECONDS, $now)
                    ? self::STEP_UP_REQUIRED
                    : null;
            } else {
                // Ohne TOTP: kein Code zum Bestätigen. Eine gerade erfolgte Anmeldung dieser Sitzung gilt als Reauthentifizierung;
                // Legacy-Sitzungen (per Refresh übernommen) und Sitzungen ohne sid zählen nie als frisch.
                return $session !== null && $session->hasRecentLogin(self::RECENT_LOGIN_SECONDS, $now) ? null : self::REAUTH_REQUIRED;
            }
        }
        if ($level === AdminMfaPolicy::LEVEL_SELF_STEP_UP) {
            if ($isAdmin) {
                $level = AdminMfaPolicy::LEVEL_STEP_UP;
            } else {
                // Normaler User: nur mit aktivem TOTP gibt es etwas zu bestätigen.
                return $this->totpService->isEnabled($user) && ($session === null || !$session->hasFreshStepUp(StepUpService::FRESHNESS_SECONDS, $now))
                    ? self::STEP_UP_REQUIRED
                    : null;
            }
        }
        if (!$isAdmin) {
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
