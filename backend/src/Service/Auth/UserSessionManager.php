<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\Service\AuditLogger;
use App\Util\UserAgentSummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Lebenszyklus der serverseitigen Login-Sitzungen (UserSession).
 * Refresh-Tokens werden über den bestehenden RefreshTokenRevoker mitwiderrufen.
 */
class UserSessionManager
{
    public const REASON_LOGOUT = 'logout';
    public const REASON_PASSWORD_CHANGE = 'password_change';
    public const REASON_PASSWORD_RESET = 'password_reset';
    public const REASON_ACCOUNT_DISABLED = 'account_disabled';
    public const REASON_USER_REVOKED = 'user_revoked';
    public const REASON_USER_REVOKED_OTHERS = 'user_revoked_others';

    /** last_seen_at höchstens alle 5 Minuten schreiben. */
    public const LAST_SEEN_THROTTLE_SECONDS = 300;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRevoker $refreshTokenRevoker,
        private readonly LegacySessionCutoff $legacyCutoff,
        private readonly RequestStack $requestStack,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function startSession(User $user, AuthMethod $authMethod): UserSession
    {
        $userAgent = $this->requestStack->getCurrentRequest()?->headers->get('User-Agent');
        $session = new UserSession($user, $authMethod, $userAgent);
        $this->entityManager->persist($session);
        if ($authMethod !== AuthMethod::LEGACY) {
            $this->auditLogger->log('user', $user->getId(), 'login_success', $user, $user, null, [
                'auth_method' => ['old' => null, 'new' => $authMethod->value],
            ]);
        }
        $this->entityManager->flush();

        return $session;
    }

    /** Login ohne MFA-Challenge dank Trusted Device: Sitzung als so MFA-verifiziert kennzeichnen (kein Step-up). */
    public function markTrustedDeviceLogin(UserSession $session, TrustedDevice $device): void
    {
        $session->markMfaViaTrustedDevice($device);
        $this->entityManager->flush();
    }

    /**
     * Aktive (nicht widerrufene) Sitzungen des Users, zuletzt aktive zuerst.
     *
     * @return list<UserSession>
     */
    public function listActiveForUser(User $user): array
    {
        /** @var list<UserSession> $sessions */
        $sessions = $this->entityManager->getRepository(UserSession::class)
            ->findBy(['user' => $user, 'revokedAt' => null], ['lastSeenAt' => 'DESC'], 50);

        return $sessions;
    }

    /**
     * Der User beendet eine seiner anderen Sitzungen. Die aktuelle Sitzung ist hier nie widerrufbar (dafür gibt es Logout).
     *
     * @throws \LogicException wenn $target die aktuelle Sitzung ist
     */
    public function revokeOtherSessionByUser(User $user, UserSession $target, UserSession $current): void
    {
        if ($target->getId() === $current->getId()) {
            throw new \LogicException('Die aktuelle Sitzung kann hier nicht beendet werden.');
        }
        if ($target->getUser()->getId() !== $user->getId() || $target->isRevoked()) {
            return;
        }
        $label = UserAgentSummary::describe($target->getUserAgent())['label'];
        $this->revokeSession($target, self::REASON_USER_REVOKED);
        $this->auditLogger->log('user', $user->getId(), 'session_revoked', $user, $user, null, [
            'session' => ['old' => $label, 'new' => null],
        ]);
        $this->entityManager->flush();
    }

    /** @return int Anzahl beendeter Sitzungen (die aktuelle bleibt) */
    public function revokeOtherSessionsByUser(User $user, UserSession $current, ?string $keepRefreshToken = null): int
    {
        $count = $this->revokeAllForUser($user, self::REASON_USER_REVOKED_OTHERS, $current, $keepRefreshToken);
        $this->auditLogger->log('user', $user->getId(), 'other_sessions_revoked', $user, $user, null, [
            'count' => ['old' => $count, 'new' => 0],
        ]);
        $this->entityManager->flush();

        return $count;
    }

    /**
     * Sitzung für einen Refresh: bestehende Sitzung des Tokens, oder befristet eine Legacy-Sitzung
     * für Tokens aus der Zeit vor UserSession.
     *
     * @throws CustomUserMessageAuthenticationException
     */
    public function resolveForRefresh(RefreshToken $refreshToken, User $user): UserSession
    {
        $session = $refreshToken->getSession();
        if ($session === null) {
            if (!$this->legacyCutoff->allowsRefreshTokenWithoutSession($refreshToken)) {
                throw new CustomUserMessageAuthenticationException('Sitzung ungültig.');
            }

            return $this->startSession($user, AuthMethod::LEGACY);
        }

        if ($session->isRevoked() || $session->getUser()->getId() !== $user->getId()) {
            throw new CustomUserMessageAuthenticationException('Sitzung ungültig.');
        }

        $this->touchLastSeen($session, true);

        return $session;
    }

    /**
     * Widerruft die Sitzung und löscht ihre Refresh-Tokens.
     */
    public function revokeSession(UserSession $session, string $reason): void
    {
        $session->revoke($reason);
        $this->entityManager->flush();
        $this->refreshTokenRevoker->revokeForSession($session->getId());
    }

    /**
     * Widerruft alle Sitzungen des Users (optional außer einer) und deren Refresh-Tokens.
     *
     * @param string|null $keepRefreshToken zusätzlich zu behaltendes Legacy-Token ohne Sitzung
     *
     * @return int Anzahl widerrufener Sitzungen
     */
    public function revokeAllForUser(
        User $user,
        string $reason,
        ?UserSession $keep = null,
        ?string $keepRefreshToken = null,
    ): int {
        $dql = 'UPDATE ' . UserSession::class . ' s SET s.revokedAt = :now, s.revokedReason = :reason'
            . ' WHERE s.user = :user AND s.revokedAt IS NULL';
        if ($keep !== null) {
            $dql .= ' AND s.id <> :keepId';
        }
        $query = $this->entityManager->createQuery($dql)
            ->setParameter('now', new \DateTime())
            ->setParameter('reason', $reason)
            ->setParameter('user', $user->getId());
        if ($keep !== null) {
            $query->setParameter('keepId', $keep->getId());
        }
        $revoked = (int) $query->execute();

        $this->refreshTokenRevoker->revokeAllForUser($user, $keepRefreshToken, $keep?->getId());

        // Passwortwechsel/-reset und Deaktivierung: auch das Login-MFA-Vertrauen aller Geräte endet.
        $trustReason = match ($reason) {
            self::REASON_PASSWORD_CHANGE, self::REASON_PASSWORD_RESET => TrustedDeviceService::REASON_PASSWORD,
            self::REASON_ACCOUNT_DISABLED => TrustedDeviceService::REASON_ACCOUNT_DISABLED,
            default => null,
        };
        if ($trustReason !== null) {
            $this->trustedDevices->revokeAllForUser($user, $trustReason);
        }

        return $revoked;
    }

    /**
     * Nach einem State-Wechsel weg von `active`: alle Sitzungen und Refresh-Tokens widerrufen.
     * Laufende Requests blockiert zusätzlich der UserChecker.
     *
     * @return bool true, wenn widerrufen wurde
     */
    public function revokeAllIfDeactivated(User $user, string $previousState): bool
    {
        $state = $user->getState();
        if ($state === 'active' || $state === $previousState) {
            return false;
        }

        $this->revokeAllForUser($user, self::REASON_ACCOUNT_DISABLED);

        return true;
    }

    /**
     * Gedrosseltes last_seen_at: bedingtes Update, höchstens ein Write pro Drosselfenster.
     */
    public function touchLastSeen(UserSession $session, bool $force = false): void
    {
        $now = new \DateTime();
        $threshold = (clone $now)->modify('-' . self::LAST_SEEN_THROTTLE_SECONDS . ' seconds');
        if (!$force && $session->getLastSeenAt() > $threshold) {
            return;
        }

        $dql = 'UPDATE ' . UserSession::class . ' s SET s.lastSeenAt = :now WHERE s.id = :id';
        if (!$force) {
            $dql .= ' AND s.lastSeenAt < :threshold';
        }
        $query = $this->entityManager->createQuery($dql)
            ->setParameter('now', $now)
            ->setParameter('id', $session->getId());
        if (!$force) {
            $query->setParameter('threshold', $threshold);
        }
        $query->execute();
        $session->setLastSeenAt($now);
    }
}
