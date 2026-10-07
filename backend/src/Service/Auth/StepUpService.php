<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\UserSession;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Step-up: frische MFA-Bestätigung innerhalb der aktuellen Sitzung (TOTP- oder Recovery Code).
 * Die Codeprüfung (inkl. Replay-Schutz, Sperre, Verbrauch von Recovery Codes und deren Audit) liegt im TotpService.
 * Ergebnis ist nur UserSession.stepUpAt (und mfaVerifiedAt, falls die Sitzung noch keinen MFA-Nachweis hatte);
 * keine neue Sitzung, keine neuen Tokens, nichts accountweit.
 */
class StepUpService
{
    /** Zentrale Frische für alle Step-up-pflichtigen Aktionen. */
    public const FRESHNESS_SECONDS = 600;

    public function __construct(
        private readonly TotpService $totpService,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws TotpException NOT_ACTIVE (kein TOTP), LOCKED, INVALID_CODE
     */
    public function confirm(User $user, UserSession $session, string $code): void
    {
        if (!$this->totpService->isEnabled($user)) {
            throw new TotpException(TotpException::NOT_ACTIVE, 'Zwei-Faktor-Authentifizierung ist nicht aktiv.');
        }

        if (!$this->totpService->verifySecondFactor($user, $code)) {
            $this->auditLogger->log('user', $user->getId(), 'step_up_failure', $user, $user, null, ['step_up' => ['old' => null, 'new' => 'failed']]);
            $this->entityManager->flush();

            throw new TotpException(TotpException::INVALID_CODE, 'Der Code ist ungültig.');
        }

        $session->markStepUp();
        $this->auditLogger->log('user', $user->getId(), 'step_up_success', $user, $user, null, ['step_up' => ['old' => null, 'new' => 'success']]);
        $this->entityManager->flush();
    }
}
