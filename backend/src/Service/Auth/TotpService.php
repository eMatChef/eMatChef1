<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\UserRecoveryCode;
use App\Entity\UserTotp;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\AuditLogger;
use App\Service\Crypto\SecretBox;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * TOTP (RFC 6238, SHA-1, 6 Stellen, 30 s) und Recovery Codes.
 *
 * - Secret nur verschlüsselt (SecretBox); im Klartext nur in der Antwort auf startEnrollment().
 * - Aktiv erst nach bestätigtem Code. Neueinrichtung lässt das alte Secret bis zur Bestätigung gültig.
 * - Genau 3 Recovery Codes, nur als HMAC gespeichert, einmal verwendbar; jede Neuerzeugung ersetzt alle.
 * - Pflicht für globale Security-Adminrollen (superadmin/orgchef/suborgchef): TOTP nicht deaktivierbar,
 *   isAdminAccessBlocked() liefert die Sperre geschützter Adminfunktionen bis zur Einrichtung.
 * - Code-Prüfung: ±1 Zeitschritt, Replay-Schutz, nach 5 Fehlversuchen 15 Minuten Sperre.
 */
class TotpService
{
    public const RECOVERY_CODE_COUNT = 3;

    private const ISSUER = 'eMatChef';
    private const PERIOD = 30;
    private const PENDING_TTL = '+15 minutes';
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_DURATION = '+15 minutes';
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretBox $secretBox,
        private readonly AdminCapabilityChecker $adminCapabilityChecker,
        private readonly AuditLogger $auditLogger,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
        private readonly ?TrustedDeviceService $trustedDevices = null,
    ) {}

    /** Globale Security-Adminrolle: TOTP verpflichtend. */
    public function isRequired(User $user): bool
    {
        return $this->adminCapabilityChecker->hasGlobalAdminRole($user);
    }

    public function isEnabled(User $user): bool
    {
        return $this->find($user)?->isActive() === true;
    }

    /** Geschützte Adminfunktionen bleiben gesperrt, solange TOTP verpflichtend, aber nicht aktiv ist. */
    public function isAdminAccessBlocked(User $user): bool
    {
        return $this->isRequired($user) && !$this->isEnabled($user);
    }

    /**
     * Status ohne Secrets.
     *
     * @return array<string, mixed>
     */
    public function status(User $user): array
    {
        $totp = $this->find($user);
        $enabled = $totp?->isActive() === true;
        $required = $this->isRequired($user);
        $remaining = $enabled ? $this->remainingRecoveryCodes($user) : null;

        return [
            'enabled' => $enabled,
            'required' => $required,
            'admin_blocked' => $required && !$enabled,
            'can_disable' => $enabled && !$required,
            'pending_enrollment' => $totp?->hasPendingEnrollment() === true,
            'locked' => $totp?->isLocked() === true,
            'recovery_codes_total' => self::RECOVERY_CODE_COUNT,
            'recovery_codes_remaining' => $remaining,
            'recovery_codes_low' => $remaining === 1,
            'recovery_codes_empty' => $remaining === 0,
        ];
    }

    /**
     * Neues Secret erzeugen; aktiviert nichts. Bei aktivem TOTP ist ein gültiger aktueller Code (TOTP oder
     * Recovery Code) nötig, damit eine gestohlene Sitzung den zweiten Faktor nicht ersetzen kann.
     *
     * @return array{secret: string, otpauth_uri: string}
     *
     * @throws TotpException
     */
    public function startEnrollment(User $user, ?string $currentCode = null): array
    {
        $totp = $this->find($user);
        if ($totp?->isActive() === true) {
            $this->requireValidCode($totp, $currentCode);
        }
        if (!$totp instanceof UserTotp) {
            $totp = new UserTotp();
            $totp->setId(IdGenerator::generateUnique($this->entityManager, UserTotp::class));
            $totp->setUser($user);
            $this->entityManager->persist($totp);
        }

        // 160 Bit (RFC 4226), von allen gängigen Authenticator-Apps unterstützt
        $otp = TOTP::createFromSecret(Base32::encodeUpperUnpadded(random_bytes(20)));
        $otp->setPeriod(self::PERIOD);
        $otp->setIssuer(self::ISSUER);
        $otp->setLabel($this->label($user));
        $totp->startEnrollment($this->secretBox->encrypt($otp->getSecret()), new \DateTime(self::PENDING_TTL));
        $this->entityManager->flush();

        return ['secret' => $otp->getSecret(), 'otpauth_uri' => $otp->getProvisioningUri()];
    }

    /**
     * Erster Code bestätigt die Einrichtung: TOTP wird aktiv, alte Recovery Codes sind ungültig.
     *
     * @return list<string> die 3 neuen Recovery Codes (einmalig im Klartext)
     *
     * @throws TotpException
     */
    public function confirmEnrollment(User $user, string $code): array
    {
        $totp = $this->find($user);
        if (!$totp instanceof UserTotp || !$totp->hasPendingEnrollment()) {
            throw new TotpException(TotpException::NO_PENDING, 'Keine laufende Einrichtung. Bitte neu starten.');
        }
        $this->assertNotLocked($totp);

        $step = $this->matchStep($this->secretBox->decrypt((string) $totp->getPendingSecretEncrypted()), $code, null);
        if ($step === null) {
            $this->registerFailure($totp);
            throw new TotpException(TotpException::INVALID_CODE, 'Der Code ist ungültig.');
        }

        $reset = $totp->isActive();
        $codes = $this->entityManager->wrapInTransaction(function () use ($user, $totp): array {
            // Bestätigung verbraucht keinen Auth-Zeitschritt; Replay-Schutz gilt ab dem ersten Login/Step-up.
            $totp->activatePending();

            return $this->replaceRecoveryCodes($user);
        });
        $this->audit($user, $reset ? 'totp_reset' : 'totp_enabled', ['totp' => ['old' => $reset, 'new' => true]]);
        if ($reset) {
            $this->trustedDevices?->revokeAllForUser($user, TrustedDeviceService::REASON_TOTP_CHANGED);
        }

        return $codes;
    }

    /**
     * @throws TotpException
     */
    public function disable(User $user, ?string $code): void
    {
        $totp = $this->find($user);
        if (!$totp instanceof UserTotp || !$totp->isActive()) {
            throw new TotpException(TotpException::NOT_ACTIVE, 'Zwei-Faktor-Authentifizierung ist nicht aktiv.');
        }
        if ($this->isRequired($user)) {
            throw new TotpException(TotpException::REQUIRED_FOR_ADMIN, 'Für deine Rolle ist die Zwei-Faktor-Authentifizierung verpflichtend.');
        }
        $this->requireValidCode($totp, $code);

        $this->entityManager->wrapInTransaction(function () use ($user, $totp): void {
            $this->deleteRecoveryCodes($user);
            $this->entityManager->remove($totp);
            $this->entityManager->flush();
        });
        $this->audit($user, 'totp_disabled', ['totp' => ['old' => true, 'new' => false]]);
        $this->trustedDevices?->revokeAllForUser($user, TrustedDeviceService::REASON_TOTP_CHANGED);
    }

    /**
     * Ersetzt alle Recovery Codes durch genau 3 neue.
     *
     * @return list<string>
     *
     * @throws TotpException
     */
    public function regenerateRecoveryCodes(User $user, ?string $code): array
    {
        $totp = $this->find($user);
        if (!$totp instanceof UserTotp || !$totp->isActive()) {
            throw new TotpException(TotpException::NOT_ACTIVE, 'Zwei-Faktor-Authentifizierung ist nicht aktiv.');
        }
        $this->requireValidCode($totp, $code);

        $codes = $this->entityManager->wrapInTransaction(fn (): array => $this->replaceRecoveryCodes($user));
        $this->audit($user, 'recovery_codes_regenerated', ['recovery_codes' => ['old' => null, 'new' => self::RECOVERY_CODE_COUNT]]);

        return $codes;
    }

    /**
     * Zweiter Faktor für Login/Step-up (spätere Phase): gültiger TOTP-Code oder unverbrauchter Recovery Code.
     * Ein Recovery Code wird verbraucht und deaktiviert TOTP nicht.
     *
     * @throws TotpException bei Sperre
     */
    public function verifySecondFactor(User $user, string $code): bool
    {
        $totp = $this->find($user);
        if (!$totp instanceof UserTotp || !$totp->isActive()) {
            return false;
        }

        try {
            $this->requireValidCode($totp, $code);
        } catch (TotpException $e) {
            if ($e->reason === TotpException::LOCKED) {
                throw $e;
            }

            return false;
        }

        return true;
    }

    /**
     * Nur für Demo-/Test-Seeds (geschützte Dev-Commands): aktiviert TOTP mit einem festen, öffentlichen Test-Secret.
     * Ist genau dieses Secret schon aktiv, bleibt alles unverändert (Reseed ändert weder Secret noch Recovery Codes).
     *
     * @return bool true, wenn TOTP neu gesetzt wurde
     */
    public function provisionFixedSecret(User $user, string $secret): bool
    {
        $totp = $this->find($user);
        if ($totp?->isActive() === true && hash_equals($this->secretBox->decrypt((string) $totp->getSecretEncrypted()), $secret)) {
            return false;
        }
        if (!$totp instanceof UserTotp) {
            $totp = new UserTotp();
            $totp->setId(IdGenerator::generateUnique($this->entityManager, UserTotp::class));
            $totp->setUser($user);
            $this->entityManager->persist($totp);
        }

        $this->entityManager->wrapInTransaction(function () use ($user, $totp, $secret): void {
            $totp->startEnrollment($this->secretBox->encrypt($secret), new \DateTime(self::PENDING_TTL));
            $totp->activatePending(0);
            $this->replaceRecoveryCodes($user);
        });

        return true;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return $this->entityManager->getRepository(UserRecoveryCode::class)->count(['user' => $user, 'usedAt' => null]);
    }

    /**
     * @throws TotpException
     */
    private function requireValidCode(UserTotp $totp, ?string $code): void
    {
        $code = trim((string) $code);
        if ($code === '') {
            throw new TotpException(TotpException::CODE_REQUIRED, 'Bitte gib einen aktuellen Code ein.');
        }
        $this->assertNotLocked($totp);

        if (preg_match('/^\d{6}$/', str_replace(' ', '', $code)) === 1) {
            $step = $this->matchStep($this->secretBox->decrypt((string) $totp->getSecretEncrypted()), $code, $totp->getLastUsedStep());
            if ($step !== null) {
                $totp->setLastUsedStep($step)->resetFailures();
                $this->entityManager->flush();

                return;
            }
        } elseif ($this->consumeRecoveryCode($totp->getUser(), $code)) {
            $totp->resetFailures();
            $this->entityManager->flush();
            $this->audit($totp->getUser(), 'recovery_code_used', ['recovery_codes' => ['old' => null, 'new' => 'used']]);

            return;
        }

        $this->registerFailure($totp);
        throw new TotpException(TotpException::INVALID_CODE, 'Der Code ist ungültig.');
    }

    /** @return int|null der passende Zeitschritt (Replay: nur größer als $lastStep) */
    private function matchStep(string $secret, string $code, ?int $lastStep): ?int
    {
        $code = str_replace(' ', '', trim($code));
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }
        $otp = TOTP::createFromSecret($secret);
        $otp->setPeriod(self::PERIOD);
        $current = intdiv(time(), self::PERIOD);
        foreach ([0, -1, 1] as $offset) {
            $step = $current + $offset;
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals($otp->at($step * self::PERIOD), $code)) {
                return $step;
            }
        }

        return null;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);
        if ($normalized === '') {
            return false;
        }
        $row = $this->entityManager->getRepository(UserRecoveryCode::class)->findOneBy([
            'user' => $user,
            'codeHash' => $this->hashRecoveryCode($normalized),
            'usedAt' => null,
        ]);
        if (!$row instanceof UserRecoveryCode) {
            return false;
        }
        $row->markUsed();

        return true;
    }

    /** @return list<string> */
    private function replaceRecoveryCodes(User $user): array
    {
        $this->deleteRecoveryCodes($user);
        $plain = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; ++$i) {
            $code = $this->generateRecoveryCode();
            $row = new UserRecoveryCode();
            $row->setId(IdGenerator::generateUnique($this->entityManager, UserRecoveryCode::class));
            $row->setUser($user);
            $row->setCodeHash($this->hashRecoveryCode($this->normalizeRecoveryCode($code)));
            $this->entityManager->persist($row);
            $this->entityManager->flush();
            $plain[] = $code;
        }

        return $plain;
    }

    private function deleteRecoveryCodes(User $user): void
    {
        $repo = $this->entityManager->getRepository(UserRecoveryCode::class);
        foreach ($repo->findBy(['user' => $user]) as $row) {
            $this->entityManager->remove($row);
        }
        $this->entityManager->flush();
    }

    private function generateRecoveryCode(): string
    {
        $chars = '';
        $max = \strlen(self::RECOVERY_ALPHABET) - 1;
        for ($i = 0; $i < 10; ++$i) {
            $chars .= self::RECOVERY_ALPHABET[random_int(0, $max)];
        }

        return substr($chars, 0, 5) . '-' . substr($chars, 5);
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $code) ?? '');
    }

    private function hashRecoveryCode(string $normalized): string
    {
        return hash_hmac('sha256', 'recovery-code|' . $normalized, $this->appSecret);
    }

    private function assertNotLocked(UserTotp $totp): void
    {
        if ($totp->isLocked()) {
            throw new TotpException(TotpException::LOCKED, 'Zu viele Fehlversuche. Bitte versuche es später erneut.');
        }
    }

    private function registerFailure(UserTotp $totp): void
    {
        $wasLocked = $totp->isLocked();
        $totp->registerFailure(self::MAX_FAILED_ATTEMPTS, new \DateTime(self::LOCK_DURATION));
        $this->entityManager->flush();
        if (!$wasLocked && $totp->isLocked()) {
            $this->audit($totp->getUser(), 'totp_locked', ['totp' => ['old' => null, 'new' => 'locked']]);
        }
    }

    private function find(User $user): ?UserTotp
    {
        $totp = $this->entityManager->getRepository(UserTotp::class)->findOneBy(['user' => $user]);

        return $totp instanceof UserTotp ? $totp : null;
    }

    private function label(User $user): string
    {
        $email = trim((string) ($user->getProfile()?->getEmail() ?? ''));

        return $email !== '' ? $email : $user->getId();
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    private function audit(User $user, string $action, array $changes): void
    {
        $this->auditLogger->log('user', $user->getId(), $action, $user, $user, null, $changes);
        $this->entityManager->flush();
    }
}
