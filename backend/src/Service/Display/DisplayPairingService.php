<?php

namespace App\Service\Display;

use App\Entity\DepartmentDisplayScreen;
use App\Entity\DisplayPairingRequest;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * QR-Kopplung: Fernseher (display.-Host) ↔ bestehender Infoscreen, bestätigt von einem berechtigten User.
 *
 * Geheimnisse:
 *  - pair token (im QR): nur für den bestätigenden User, einmalig, kurzlebig
 *  - poll secret: bleibt im Fernseher, nur damit holt er die Display-Sitzung ab
 * Beide liegen ausschliesslich als SHA-256-Hash in der DB.
 */
class DisplayPairingService
{
    public const TTL_SECONDS = 300;
    public const POLL_INTERVAL_SECONDS = 2;

    public const POLL_PENDING = 'pending';
    public const POLL_APPROVED = 'approved';
    public const POLL_EXPIRED = 'expired';
    public const POLL_REVOKED = 'revoked';

    public const APPROVE_OK = 'ok';
    public const APPROVE_FORBIDDEN = 'forbidden';
    public const APPROVE_NOT_FOUND = 'not_found';
    public const APPROVE_GONE = 'gone';

    /** Ohne verwechselbare Zeichen, zum visuellen Abgleich TV ↔ Handy. */
    private const USER_CODE_CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentDisplayScreenService $screenService,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
    ) {
    }

    /**
     * @return array{request: DisplayPairingRequest, token: string, poll_secret: string}
     */
    public function create(): array
    {
        $this->purgeOld();

        $token = $this->randomSecret();
        $pollSecret = $this->randomSecret();

        $request = new DisplayPairingRequest();
        $request->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, DisplayPairingRequest::class, 'dpr'));
        $request->setTokenHash(self::hash($token));
        $request->setPollSecretHash(self::hash($pollSecret));
        $request->setUserCode($this->generateUserCode());
        $request->setExpiresAt(new \DateTime('+' . self::TTL_SECONDS . ' seconds'));

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return ['request' => $request, 'token' => $token, 'poll_secret' => $pollSecret];
    }

    public function buildPairUrl(string $token): string
    {
        return rtrim(trim($this->appFrontendUrl), '/') . '/connect-display/' . rawurlencode($token);
    }

    /** Offene (pending, nicht abgelaufene) Anfrage zum QR-Token. */
    public function findOpenByToken(string $token): ?DisplayPairingRequest
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $request = $this->entityManager->getRepository(DisplayPairingRequest::class)->findOneBy([
            'tokenHash' => self::hash($token),
        ]);
        if ($request === null || $request->getStatus() !== DisplayPairingRequest::STATUS_PENDING || $request->isExpired()) {
            return null;
        }

        return $request;
    }

    /**
     * Bindet die Anfrage einmalig an einen Screen. Atomar: nur ein Aufruf kann pending → approved setzen.
     *
     * @return self::APPROVE_*
     */
    public function approve(string $token, User $user, string $screenId): string
    {
        $request = $this->findOpenByToken($token);
        if ($request === null) {
            return self::APPROVE_GONE;
        }

        $screen = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($screenId);
        if (!$screen instanceof DepartmentDisplayScreen || $screen->isRevoked()) {
            return self::APPROVE_NOT_FOUND;
        }
        if (!$this->screenService->canManageDepartment($user, $screen->getDepartmentId())) {
            return self::APPROVE_FORBIDDEN;
        }

        $now = new \DateTime();
        $updated = $this->entityManager->createQueryBuilder()
            ->update(DisplayPairingRequest::class, 'r')
            ->set('r.status', ':approved')
            ->set('r.screenId', ':screenId')
            ->set('r.approvedByUserId', ':userId')
            ->set('r.approvedAt', ':now')
            ->where('r.id = :id')
            ->andWhere('r.status = :pending')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('approved', DisplayPairingRequest::STATUS_APPROVED)
            ->setParameter('pending', DisplayPairingRequest::STATUS_PENDING)
            ->setParameter('screenId', $screen->getId())
            ->setParameter('userId', $user->getId())
            ->setParameter('now', $now)
            ->setParameter('id', $request->getId())
            ->getQuery()
            ->execute();

        return $updated === 1 ? self::APPROVE_OK : self::APPROVE_GONE;
    }

    /**
     * Legt einen neuen Screen im Department an und bindet die Kopplungsanfrage daran. Alles oder nichts:
     * schlägt die Freigabe fehl (Token abgelaufen/verbraucht), wird der Screen wieder entfernt.
     *
     * @return array{result: string, screen?: DepartmentDisplayScreen}
     */
    public function createScreenAndApprove(string $token, User $user, string $departmentId, string $name): array
    {
        if ($this->findOpenByToken($token) === null) {
            return ['result' => self::APPROVE_GONE];
        }
        if (!$this->screenService->canManageDepartment($user, $departmentId)) {
            return ['result' => self::APPROVE_FORBIDDEN];
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $screen = $this->screenService->create($departmentId, $name, $user)['screen'];
            $result = $this->approve($token, $user, $screen->getId());
            if ($result !== self::APPROVE_OK) {
                $screenId = $screen->getId();
                $connection->rollBack();
                $this->entityManager->clear();
                // Zusätzlich explizit entfernen: greift auch, wenn dieser Aufruf in einer äusseren Transaktion läuft.
                $connection->executeStatement('DELETE FROM department_display_screen WHERE id = ?', [$screenId]);

                return ['result' => $result];
            }
            $connection->commit();
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
            throw $e;
        }

        return ['result' => self::APPROVE_OK, 'screen' => $screen];
    }

    /**
     * Abfrage durch den Fernseher. Bei Freigabe wird die Anfrage atomar verbraucht und der Screen geliefert.
     *
     * @return array{status: string, screen?: DepartmentDisplayScreen, expires_at?: \DateTimeInterface}|null null = unbekannt oder falsches Secret
     */
    public function poll(string $requestId, string $pollSecret): ?array
    {
        $request = $this->entityManager->getRepository(DisplayPairingRequest::class)->find($requestId);
        if (!$request instanceof DisplayPairingRequest) {
            return null;
        }
        if (!hash_equals($request->getPollSecretHash(), self::hash($pollSecret))) {
            return null;
        }
        // Status wird per Bulk-UPDATE gesetzt (atomar); Identity Map nicht veraltet lassen.
        $this->entityManager->refresh($request);

        if ($request->getStatus() === DisplayPairingRequest::STATUS_CONSUMED || $request->isExpired()) {
            return ['status' => self::POLL_EXPIRED];
        }
        if ($request->getStatus() === DisplayPairingRequest::STATUS_PENDING) {
            return ['status' => self::POLL_PENDING, 'expires_at' => $request->getExpiresAt()];
        }

        $screen = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find((string) $request->getScreenId());
        if (!$screen instanceof DepartmentDisplayScreen || $screen->isRevoked()) {
            return ['status' => self::POLL_REVOKED];
        }

        $consumed = $this->entityManager->createQueryBuilder()
            ->update(DisplayPairingRequest::class, 'r')
            ->set('r.status', ':consumed')
            ->set('r.consumedAt', ':now')
            ->where('r.id = :id')
            ->andWhere('r.status = :approved')
            ->setParameter('consumed', DisplayPairingRequest::STATUS_CONSUMED)
            ->setParameter('approved', DisplayPairingRequest::STATUS_APPROVED)
            ->setParameter('now', new \DateTime())
            ->setParameter('id', $request->getId())
            ->getQuery()
            ->execute();
        if ($consumed !== 1) {
            return ['status' => self::POLL_EXPIRED];
        }

        $this->screenService->touchLastUsed($screen);

        return ['status' => self::POLL_APPROVED, 'screen' => $screen];
    }

    private function purgeOld(): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(DisplayPairingRequest::class, 'r')
            ->where('r.expiresAt < :cutoff')
            ->setParameter('cutoff', new \DateTime('-1 day'))
            ->getQuery()
            ->execute();
    }

    private function randomSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateUserCode(): string
    {
        $max = strlen(self::USER_CODE_CHARSET) - 1;
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= self::USER_CODE_CHARSET[random_int(0, $max)];
        }

        return $out;
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
