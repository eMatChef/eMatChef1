<?php

namespace App\Service\Display;

use App\Entity\DepartmentDisplayDevice;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Eigenständige Anzeigegeräte eines Infoscreens.
 *
 * Geräteidentität: zufälliges Credential im hostgebundenen HttpOnly-Cookie `EMC_DISPLAY_DEVICE` ("{deviceId}.{secret}"),
 * serverseitig nur als SHA-256-Hash. Das Credential rotiert regelmässig; das vorherige bleibt gültig, bis das neue
 * erstmals benutzt wurde (Verbindungsabbruch mitten in der Rotation sperrt das Gerät nicht aus, ein danach
 * wiederholt vorgelegtes altes Credential wird abgelehnt).
 *
 * Freigabe (`approvalExpiresAt`, 90 Tage): wird nur serverseitig durch Verwalter gesetzt/verlängert. Ohne gültige
 * Freigabe werden keine Anzeigedaten ausgeliefert, die Identität bleibt aber für die Wiederfreigabe bestehen.
 */
class DepartmentDisplayDeviceService
{
    public const COOKIE_NAME = 'EMC_DISPLAY_DEVICE';
    public const COOKIE_PATH = '/api/public/display-device';
    public const APPROVAL_DAYS = 90;
    public const ONLINE_WINDOW_SECONDS = 300;

    public const STATE_NONE = 'none';
    public const STATE_REVOKED = 'revoked';
    public const STATE_EXPIRED = 'expired';
    public const STATE_ACTIVE = 'active';

    private const COOKIE_TTL_SECONDS = 63072000; // 2 Jahre; die Freigabe selbst begrenzt die Nutzung
    private const ROTATE_AFTER_SECONDS = 604800; // 7 Tage
    private const CONTACT_WRITE_INTERVAL_SECONDS = 60;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentDisplayScreenService $screenService,
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')] private bool $cookieSecure,
    ) {
    }

    /**
     * @return array{device: DepartmentDisplayDevice, secret: string}
     */
    public function create(
        DepartmentDisplayScreen $screen,
        string $name,
        string $via,
        ?string $createdByUserId = null,
        ?\DateTimeInterface $approvalExpiresAt = null,
    ): array {
        $name = $this->normalizeName($name);
        $secret = $this->randomSecret();
        $now = new \DateTime();

        $device = new DepartmentDisplayDevice();
        $device->setId($this->screenService->generateUnusedId(DepartmentDisplayDevice::class, 'ddv', 'id'));
        $device->setScreenId($screen->getId());
        $device->setName($name);
        $device->setCredentialHash(self::hash($secret));
        $device->setCredentialRotatedAt($now);
        $device->setCreatedVia($via);
        $device->setApprovalExpiresAt($approvalExpiresAt ?? $this->newApprovalExpiry());
        $device->setApprovedAt($now);
        $device->setCreatedByUserId($createdByUserId);

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return ['device' => $device, 'secret' => $secret];
    }

    public function newApprovalExpiry(): \DateTime
    {
        return new \DateTime('+' . self::APPROVAL_DAYS . ' days');
    }

    public function buildCookie(DepartmentDisplayDevice $device, string $secret): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue($device->getId() . '.' . $secret)
            ->withExpires(new \DateTimeImmutable('+' . self::COOKIE_TTL_SECONDS . ' seconds'))
            ->withPath(self::COOKIE_PATH)
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function clearCookie(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue('')
            ->withExpires(new \DateTimeImmutable('-1 day'))
            ->withPath(self::COOKIE_PATH)
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    /**
     * Prüft das Credential des Requests (jeder geschützte Abruf) und liefert den serverseitigen Zustand.
     *
     * @return array{state: string, device?: DepartmentDisplayDevice, screen?: DepartmentDisplayScreen, cookie?: Cookie}
     */
    public function identify(Request $request): array
    {
        $raw = (string) $request->cookies->get(self::COOKIE_NAME, '');
        if ($raw === '' || !str_contains($raw, '.') || strlen($raw) > 160) {
            return ['state' => self::STATE_NONE];
        }
        [$deviceId, $secret] = explode('.', $raw, 2);
        $device = $deviceId !== '' ? $this->entityManager->getRepository(DepartmentDisplayDevice::class)->find($deviceId) : null;
        if (!$device instanceof DepartmentDisplayDevice) {
            return ['state' => self::STATE_NONE];
        }

        $given = self::hash($secret);
        $isCurrent = hash_equals($device->getCredentialHash(), $given);
        $previous = $device->getPreviousCredentialHash();
        $isPrevious = !$isCurrent && $previous !== null && hash_equals($previous, $given);
        if (!$isCurrent && !$isPrevious) {
            return ['state' => self::STATE_NONE];
        }

        if ($device->isRevoked()) {
            return ['state' => self::STATE_REVOKED, 'device' => $device];
        }
        $screen = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($device->getScreenId());
        if (!$screen instanceof DepartmentDisplayScreen || $screen->isRevoked()) {
            return ['state' => self::STATE_REVOKED, 'device' => $device];
        }

        $now = new \DateTime();
        $result = ['device' => $device, 'screen' => $screen];

        // Rotation: neues Credential ausstellen (Klartext nur jetzt bekannt); das bisherige bleibt gültig, bis das neue benutzt wird.
        $needsRotation = $isPrevious
            || ($now->getTimestamp() - $device->getCredentialRotatedAt()->getTimestamp()) > self::ROTATE_AFTER_SECONDS;
        if ($needsRotation) {
            $newSecret = $this->randomSecret();
            if ($isCurrent) {
                $device->setPreviousCredentialHash($device->getCredentialHash());
            }
            $device->setCredentialHash(self::hash($newSecret));
            $device->setCredentialRotatedAt($now);
            $result['cookie'] = $this->buildCookie($device, $newSecret);
        } elseif ($isCurrent && $previous !== null) {
            // Das neue Credential wurde benutzt: das alte ist damit ungültig (Replay-Schutz).
            $device->setPreviousCredentialHash(null);
        }

        $last = $device->getLastContactAt();
        if ($last === null || ($now->getTimestamp() - $last->getTimestamp()) >= self::CONTACT_WRITE_INTERVAL_SECONDS) {
            $device->setLastContactAt($now);
        }
        $device->setUpdatedAt($now);
        $this->entityManager->flush();

        $result['state'] = $device->isApprovalExpired($now) ? self::STATE_EXPIRED : self::STATE_ACTIVE;

        return $result;
    }

    // --- Verwaltung ----------------------------------------------------------------------------------------

    /**
     * @return list<DepartmentDisplayDevice>
     */
    public function listForDepartment(string $departmentId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DepartmentDisplayDevice::class, 'd')
            ->innerJoin(DepartmentDisplayScreen::class, 's', 'WITH', 's.id = d.screenId')
            ->where('s.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findInDepartment(string $departmentId, string $deviceId): ?DepartmentDisplayDevice
    {
        $device = $this->entityManager->getRepository(DepartmentDisplayDevice::class)->find($deviceId);
        if (!$device instanceof DepartmentDisplayDevice) {
            return null;
        }
        $screen = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($device->getScreenId());

        return $screen instanceof DepartmentDisplayScreen && $screen->getDepartmentId() === $departmentId ? $device : null;
    }

    public function rename(DepartmentDisplayDevice $device, string $name): void
    {
        $device->setName($this->normalizeName($name));
        $device->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Setzt die Freigabe auf 90 Tage ab jetzt. Auch für abgelaufene Geräte (administrative Wiederfreigabe);
     * widerrufene Geräte müssen neu gekoppelt werden.
     */
    public function extend(DepartmentDisplayDevice $device): void
    {
        if ($device->isRevoked()) {
            throw new \InvalidArgumentException('Widerrufene Geräte müssen neu gekoppelt werden.');
        }
        $now = new \DateTime();
        $device->setApprovalExpiresAt($this->newApprovalExpiry());
        $device->setApprovedAt($now);
        $device->setUpdatedAt($now);
        $this->entityManager->flush();
    }

    public function revoke(DepartmentDisplayDevice $device): void
    {
        if ($device->isRevoked()) {
            throw new \InvalidArgumentException('Gerät ist bereits widerrufen.');
        }
        $now = new \DateTime();
        $device->setRevokedAt($now);
        $device->setUpdatedAt($now);
        $this->entityManager->flush();
    }

    /** Anderen Infoscreen desselben Departments zuweisen. */
    public function reassign(DepartmentDisplayDevice $device, DepartmentDisplayScreen $target): void
    {
        if ($device->isRevoked()) {
            throw new \InvalidArgumentException('Widerrufene Geräte müssen neu gekoppelt werden.');
        }
        if ($target->isRevoked()) {
            throw new \InvalidArgumentException('Ziel-Infoscreen ist widerrufen.');
        }
        $current = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($device->getScreenId());
        if (!$current instanceof DepartmentDisplayScreen || $current->getDepartmentId() !== $target->getDepartmentId()) {
            throw new \InvalidArgumentException('Ziel-Infoscreen gehört zu einem anderen Department.');
        }
        $device->setScreenId($target->getId());
        $device->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public function deletePermanently(DepartmentDisplayDevice $device, ?User $deletedBy): void
    {
        if (!$device->isRevoked()) {
            throw new \InvalidArgumentException('Nur widerrufene Geräte können endgültig gelöscht werden.');
        }
        $this->removeWithTombstone($device, $deletedBy);
        $this->entityManager->flush();
    }

    public function isOnline(DepartmentDisplayDevice $device, ?\DateTimeInterface $now = null): bool
    {
        $last = $device->getLastContactAt();

        return $last !== null && (($now ?? new \DateTime())->getTimestamp() - $last->getTimestamp()) <= self::ONLINE_WINDOW_SECONDS;
    }

    public function approvalState(DepartmentDisplayDevice $device, ?\DateTimeInterface $now = null): string
    {
        if ($device->isRevoked()) {
            return self::STATE_REVOKED;
        }

        return $device->isApprovalExpired($now) ? self::STATE_EXPIRED : self::STATE_ACTIVE;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DepartmentDisplayDevice $device): array
    {
        $now = new \DateTime();

        return [
            'id' => $device->getId(),
            'screen_id' => $device->getScreenId(),
            'name' => $device->getName(),
            'approval_state' => $this->approvalState($device, $now),
            'approval_expires_at' => $device->getApprovalExpiresAt()->format('c'),
            'approved_at' => $device->getApprovedAt()->format('c'),
            'online' => $this->isOnline($device, $now),
            'last_contact_at' => $device->getLastContactAt()?->format('c'),
            'revoked_at' => $device->getRevokedAt()?->format('c'),
            'created_via' => $device->getCreatedVia(),
            'created_at' => $device->getCreatedAt()->format('c'),
        ];
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Gerätename ist erforderlich (maximal 120 Zeichen).');
        }

        return $name;
    }

    private function removeWithTombstone(DepartmentDisplayDevice $device, ?User $deletedBy): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO display_deleted_id (id, kind, deleted_at, deleted_by_user_id) VALUES (?, ?, ?, ?) ON CONFLICT (id) DO NOTHING',
            [$device->getId(), 'device', (new \DateTime())->format('Y-m-d H:i:s'), $deletedBy?->getId()],
        );
        $this->entityManager->remove($device);
    }

    private function randomSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
