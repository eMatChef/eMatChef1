<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\MfaChallenge;
use App\Entity\User;
use App\Enum\AuthMethod;
use App\Util\IdGenerator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zweiter Faktor beim Login: statt Sitzung und Tokens gibt es nach der primären Authentifizierung
 * zuerst eine kurzlebige Einmal-Challenge (5 Minuten); erst deren erfolgreiche TOTP-/Recovery-Code-Prüfung
 * führt zu Sitzung (mit mfa_verified_at) und Tokens.
 *
 * Provider-MFA: Weder Google (unsere Integration liest nur den Userinfo-Endpoint, `amr` gibt es dort nicht)
 * noch MiData/Hitobito (kein dokumentiertes amr/acr/auth_time) liefert einen belastbaren Nachweis, dass bei
 * genau diesem Login MFA stattfand. Deshalb ist $providerMfaConfirmed für alle Wege false.
 */
class MfaChallengeService
{
    public const TTL = '+5 minutes';
    public const TTL_SECONDS = 300;
    public const METHOD_TOTP = 'totp';
    public const METHOD_RECOVERY_CODE = 'recovery_code';

    private const MAX_FAILED_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TotpService $totpService,
    ) {}

    /**
     * @return array{mfa_required: true, challenge: string, methods: list<string>, expires_in: int}|null
     *         null = kein zweiter Faktor nötig (kein aktives TOTP oder belastbar bestätigte Provider-MFA)
     */
    public function issueIfRequired(User $user, AuthMethod $authMethod, bool $providerMfaConfirmed = false): ?array
    {
        if ($providerMfaConfirmed || !$this->totpService->isEnabled($user)) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $challenge = new MfaChallenge(
            IdGenerator::generateUnique($this->entityManager, MfaChallenge::class),
            $user,
            hash('sha256', $token),
            $authMethod,
            new \DateTime(self::TTL),
        );
        $this->entityManager->persist($challenge);
        $this->purgeExpired($user);
        $this->entityManager->flush();

        return [
            'mfa_required' => true,
            'challenge' => $token,
            'methods' => [self::METHOD_TOTP, self::METHOD_RECOVERY_CODE],
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * Prüft Challenge und zweiten Faktor und verbraucht die Challenge.
     *
     * @throws MfaException
     */
    public function verify(string $token, string $method, string $code): MfaChallenge
    {
        $token = trim($token);
        $challenge = $token === '' ? null : $this->entityManager->getRepository(MfaChallenge::class)
            ->findOneBy(['tokenHash' => hash('sha256', $token)]);
        if (!$challenge instanceof MfaChallenge || !$challenge->isUsable()) {
            throw new MfaException(MfaException::INVALID_CHALLENGE, 'Die Anmeldung ist abgelaufen. Bitte melde dich erneut an.');
        }

        $user = $challenge->getUser();
        $normalized = str_replace(' ', '', trim($code));
        $isSixDigits = preg_match('/^\d{6}$/', $normalized) === 1;
        $matchesMethod = match ($method) {
            self::METHOD_TOTP => $isSixDigits,
            self::METHOD_RECOVERY_CODE => !$isSixDigits && $normalized !== '',
            default => false,
        };

        try {
            $valid = $matchesMethod && $this->totpService->verifySecondFactor($user, $code);
        } catch (TotpException $e) {
            throw new MfaException(MfaException::LOCKED, $e->getMessage());
        }
        if (!$valid) {
            if ($challenge->registerFailure() >= self::MAX_FAILED_ATTEMPTS) {
                $challenge->markUsed();
            }
            $this->entityManager->flush();

            throw new MfaException(MfaException::INVALID_CODE, 'Der Code ist ungültig.');
        }

        // Einmalig auch bei parallelen Requests: unter Zeilensperre neu prüfen, nur ein Request darf verbrauchen.
        $this->entityManager->wrapInTransaction(function () use ($challenge): void {
            $this->entityManager->refresh($challenge, LockMode::PESSIMISTIC_WRITE);
            if (!$challenge->isUsable()) {
                throw new MfaException(MfaException::INVALID_CHALLENGE, 'Die Anmeldung ist abgelaufen. Bitte melde dich erneut an.');
            }
            $challenge->markUsed();
            $this->entityManager->flush();
        });

        return $challenge;
    }

    private function purgeExpired(User $user): void
    {
        $cutoff = new \DateTime('-1 day');
        foreach ($this->entityManager->getRepository(MfaChallenge::class)->findBy(['user' => $user]) as $old) {
            if ($old->getExpiresAt() < $cutoff) {
                $this->entityManager->remove($old);
            }
        }
    }
}
