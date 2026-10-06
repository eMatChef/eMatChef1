<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Service\AuditLogger;
use App\Util\IdGenerator;
use App\Util\UserAgentSummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trusted Devices: überspringen ausschliesslich die Login-MFA. Sie ersetzen nie ein Step-up.
 *
 * - Credential: 32 zufällige Bytes, serverseitig nur sha256; Klartext nur im HttpOnly-Cookie (Pfad /api/auth).
 * - Gebunden an genau einen User; Credential eines anderen Users wird ignoriert.
 * - Dauer über TrustedDevicePolicy (aktuelle Rolle); nicht gleitend: Erteilung + Dauer.
 * - Erteilen/Erneuern nur nach erfolgreicher MFA (MfaController), nie durch Passwort-/OAuth-Login allein.
 */
class TrustedDeviceService
{
    public const COOKIE_NAME = 'emc_td';
    public const COOKIE_PATH = '/api/auth';

    public const REASON_USER = 'user_revoked';
    public const REASON_TOTP_CHANGED = 'totp_changed';
    public const REASON_PASSWORD = 'password_changed';
    public const REASON_ACCOUNT_DISABLED = 'account_disabled';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrustedDevicePolicy $policy,
        private readonly AuditLogger $auditLogger,
        private readonly CurrentAuthSession $currentSession,
        #[Autowire('%env(default::AUTH_COOKIE_DOMAIN)%')]
        private readonly string $authCookieDomain = '',
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')]
        private readonly bool $authCookieSecure = false,
    ) {}

    /**
     * Login-Pfad: gültiges Gerät dieses Users → Login-MFA darf entfallen. Merkt das Gerät für die neue Sitzung vor
     * (JwtSessionSubscriber markiert sie als per Trusted Device MFA-verifiziert; nie als Step-up).
     */
    public function authenticate(User $user, Request $request): ?TrustedDevice
    {
        $device = $this->findUsable($user, $request);
        if (!$device instanceof TrustedDevice) {
            return null;
        }

        $device->markUsed();
        $this->auditLogger->log('user', $user->getId(), 'trusted_device_used', $user, $user, null, [
            'device' => ['old' => null, 'new' => $device->getLabel()],
        ]);
        $this->entityManager->flush();
        $this->currentSession->setPendingTrustedDevice($device);

        return $device;
    }

    /**
     * Vertrauen erteilen oder erneuern (nur nach erfolgreicher MFA aufrufen).
     *
     * @return array{device: TrustedDevice, cookie: Cookie}
     */
    public function grant(User $user, Request $request): array
    {
        $credential = bin2hex(random_bytes(32));
        $hash = hash('sha256', $credential);
        $label = UserAgentSummary::describe($request->headers->get('User-Agent'))['label'];
        $expiresAt = new \DateTime('+' . $this->policy->daysFor($user) . ' days');

        $existing = $this->findUsable($user, $request);
        if ($existing instanceof TrustedDevice) {
            $existing->renew($hash, $label, $expiresAt);
            $device = $existing;
        } else {
            $device = new TrustedDevice(IdGenerator::generateUnique($this->entityManager, TrustedDevice::class), $user, $hash, $label, $expiresAt);
            $this->entityManager->persist($device);
        }
        $this->auditLogger->log('user', $user->getId(), 'trusted_device_created', $user, $user, null, [
            'device' => ['old' => null, 'new' => $label],
        ]);
        $this->entityManager->flush();

        return ['device' => $device, 'cookie' => $this->buildCookie($credential)];
    }

    /**
     * Aktive Geräte des Users (nicht widerrufen, nicht abgelaufen), neueste zuerst.
     *
     * @return list<TrustedDevice>
     */
    public function listActive(User $user): array
    {
        $days = $this->policy->daysFor($user);
        /** @var list<TrustedDevice> $all */
        $all = $this->entityManager->getRepository(TrustedDevice::class)->findBy(['user' => $user], ['trustedAt' => 'DESC'], 50);

        return array_values(array_filter($all, static fn (TrustedDevice $d): bool => $d->isUsable($days)));
    }

    public function effectiveExpiry(User $user, TrustedDevice $device): \DateTime
    {
        return $device->effectiveExpiry($this->policy->daysFor($user));
    }

    public function isCurrent(User $user, TrustedDevice $device, Request $request): bool
    {
        $found = $this->findByRequest($request);

        return $found instanceof TrustedDevice && $found->getId() === $device->getId() && $found->getUser()->getId() === $user->getId();
    }

    public function revoke(User $user, string $deviceId, string $reason = self::REASON_USER): bool
    {
        $device = $this->entityManager->getRepository(TrustedDevice::class)->find($deviceId);
        if (!$device instanceof TrustedDevice || $device->getUser()->getId() !== $user->getId() || $device->isRevoked()) {
            return false;
        }
        $device->revoke($reason);
        $this->auditLogger->log('user', $user->getId(), 'trusted_device_revoked', $user, $user, null, [
            'device' => ['old' => $device->getLabel(), 'new' => null],
        ]);
        $this->entityManager->flush();

        return true;
    }

    /** @return int Anzahl widerrufener Geräte */
    public function revokeAllForUser(User $user, string $reason): int
    {
        $count = 0;
        foreach ($this->entityManager->getRepository(TrustedDevice::class)->findBy(['user' => $user, 'revokedAt' => null]) as $device) {
            $device->revoke($reason);
            ++$count;
        }
        if ($count > 0) {
            $this->auditLogger->log('user', $user->getId(), 'trusted_device_revoked', $user, $user, null, [
                'count' => ['old' => $count, 'new' => 0],
                'reason' => ['old' => null, 'new' => $reason],
            ]);
            $this->entityManager->flush();
        }

        return $count;
    }

    private function findUsable(User $user, Request $request): ?TrustedDevice
    {
        $device = $this->findByRequest($request);
        if (!$device instanceof TrustedDevice || $device->getUser()->getId() !== $user->getId()) {
            return null;
        }

        return $device->isUsable($this->policy->daysFor($user)) ? $device : null;
    }

    private function findByRequest(Request $request): ?TrustedDevice
    {
        $credential = (string) $request->cookies->get(self::COOKIE_NAME, '');
        if (!preg_match('/^[0-9a-f]{64}$/', $credential)) {
            return null;
        }
        $device = $this->entityManager->getRepository(TrustedDevice::class)->findOneBy(['credentialHash' => hash('sha256', $credential)]);

        return $device instanceof TrustedDevice ? $device : null;
    }

    private function buildCookie(string $credential): Cookie
    {
        $domain = trim($this->authCookieDomain);

        return Cookie::create(self::COOKIE_NAME)
            ->withValue($credential)
            ->withExpires(new \DateTime('+' . TrustedDevicePolicy::DAYS_USER . ' days'))
            ->withPath(self::COOKIE_PATH)
            ->withDomain($domain !== '' ? $domain : null)
            ->withSecure($this->authCookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
