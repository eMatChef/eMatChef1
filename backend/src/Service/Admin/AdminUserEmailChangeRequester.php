<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\User;
use App\Service\AuditLogger;
use App\Service\UserEmailAliasService;
use App\Service\VerificationEmailService;

/**
 * Admin-seitige Änderung der Login-E-Mail über den bestehenden Pending-Flow:
 * Profile.email bleibt bis zur Bestätigung (GET /api/auth/verify) unverändert.
 * Kein Flush — der Aufrufer speichert.
 */
final class AdminUserEmailChangeRequester
{
    private const TOKEN_TTL = '+10 days';

    public function __construct(
        private readonly UserEmailAliasService $emailAliases,
        private readonly VerificationEmailService $verificationEmailService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @throws AdminUserUpdateDeniedException
     */
    public function request(User $actor, User $target, string $newEmail): void
    {
        $profile = $target->getProfile();
        if ($profile === null) {
            throw new AdminUserUpdateDeniedException('Profil nicht gefunden', 404);
        }

        $email = strtolower(trim($newEmail));
        if ($email === '') {
            throw new AdminUserUpdateDeniedException('E-Mail darf nicht leer sein', 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AdminUserUpdateDeniedException('Ungültige E-Mail-Adresse', 400);
        }
        // Registrierung noch offen: emailVerificationToken gehört zum Registrierungslink, und
        // /api/auth/verify würde bei gesetztem pendingEmail nur die Adresse tauschen, ohne zu verifizieren.
        if (!$target->isEmailVerified()) {
            throw new AdminUserUpdateDeniedException(
                'Der Benutzer hat seine Registrierung noch nicht bestätigt. Die E-Mail-Adresse kann erst danach geändert werden.',
                409
            );
        }
        if ($this->emailAliases->isEmailTaken($email, $target)) {
            throw new AdminUserUpdateDeniedException('E-Mail ist bereits vergeben', 409);
        }

        $previousPendingEmail = $target->getPendingEmail();
        $previousToken = $target->getEmailVerificationToken();
        $previousExpiresAt = $target->getEmailVerificationExpiresAt();

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime())->modify(self::TOKEN_TTL);
        $target->setPendingEmail($email);
        $target->setEmailVerificationToken($token);
        $target->setEmailVerificationExpiresAt($expiresAt);

        try {
            $this->verificationEmailService->sendPendingEmailChangeVerification($target, $email, $token, $expiresAt);
        } catch (\Throwable) {
            $target->setPendingEmail($previousPendingEmail);
            $target->setEmailVerificationToken($previousToken);
            $target->setEmailVerificationExpiresAt($previousExpiresAt);

            throw new AdminUserUpdateDeniedException(
                'Bestätigungslink konnte nicht gesendet werden. Bitte E-Mail-Adresse prüfen.',
                400
            );
        }

        $this->auditLogger->log(
            'profile',
            (string) $profile->getId(),
            'profile_email_change_requested',
            $actor,
            $target,
            null,
            [
                'email' => ['old' => strtolower($profile->getEmail()), 'new' => $email],
                'pending_email' => ['old' => $previousPendingEmail, 'new' => $email],
                'source' => ['old' => null, 'new' => 'admin'],
            ]
        );
    }
}
