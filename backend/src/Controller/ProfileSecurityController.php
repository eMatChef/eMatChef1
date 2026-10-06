<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ExternalIdentity;
use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use App\Service\AuditLogger;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\SecurityActivityService;
use App\Service\Auth\TrustedDeviceService;
use App\Service\Auth\UserSessionManager;
use App\Util\UserAgentSummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Profil → Sicherheit: eigene Sitzungen, Trusted Devices und Sicherheitsaktivität. Nur das eigene Konto.
 * «Alle anderen Sitzungen beenden» ist über AdminMfaPolicy (LEVEL_SELF_STEP_UP) zentral step-up-pflichtig.
 */
#[Route('/api/profiles/{id}/security', name: 'api_profile_security_')]
#[IsGranted('ROLE_USER')]
final class ProfileSecurityController extends AbstractController
{
    public function __construct(
        private readonly UserSessionManager $sessionManager,
        private readonly UserSessionRepository $sessionRepository,
        private readonly CurrentAuthSession $currentSession,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly SecurityActivityService $activity,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('/sessions', name: 'sessions', methods: ['GET'])]
    public function sessions(string $id): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->noStore(new JsonResponse(['sessions' => $this->serializeSessions($user)]));
    }

    /** Beendet eine ANDERE eigene Sitzung. Die aktuelle Sitzung wird serverseitig abgelehnt (dafür gibt es Logout). */
    #[Route('/sessions/revoke-others', name: 'sessions_revoke_others', methods: ['POST'])]
    public function revokeOthers(string $id): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $current = $this->currentSession->getAuthenticated();
        if ($current === null) {
            return $this->sessionRequired();
        }

        $count = $this->sessionManager->revokeOtherSessionsByUser($user, $current);

        return $this->noStore(new JsonResponse(['revoked' => $count, 'sessions' => $this->serializeSessions($user)]));
    }

    #[Route('/sessions/{sessionId}', name: 'session_revoke', methods: ['DELETE'])]
    public function revokeSession(string $id, string $sessionId): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $current = $this->currentSession->getAuthenticated();
        if ($current === null) {
            return $this->sessionRequired();
        }

        $target = $this->sessionRepository->findOneById($sessionId);
        // Fremde, unbekannte und bereits beendete Sitzungen sind nicht unterscheidbar (404).
        if (!$target instanceof UserSession || $target->getUser()->getId() !== $user->getId() || $target->isRevoked()) {
            return new JsonResponse(['error' => 'Sitzung nicht gefunden'], 404);
        }
        if ($target->getId() === $current->getId()) {
            return new JsonResponse(['error' => 'current_session', 'message' => 'Die aktuelle Sitzung beendest du mit «Abmelden».'], 409);
        }

        $this->sessionManager->revokeOtherSessionByUser($user, $target, $current);

        return $this->noStore(new JsonResponse(['sessions' => $this->serializeSessions($user)]));
    }

    /** Verbundene externe Identitäten (nur Provider und Zeitpunkt; keine Tokens, keine externe User-ID). */
    #[Route('/external-identities', name: 'external_identities', methods: ['GET'])]
    public function externalIdentities(string $id): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->noStore(new JsonResponse(['identities' => $this->serializeExternalIdentities($user)]));
    }

    /**
     * Trennt ausschliesslich die ExternalIdentity des Providers. Memberships, Departments und Gruppen bleiben unberührt.
     * Nur MiData ist trennbar; das Konto darf sich dabei nicht aussperren.
     */
    #[Route('/external-identities/{provider}', name: 'external_identity_disconnect', methods: ['DELETE'])]
    public function disconnectExternalIdentity(string $id, string $provider): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($provider !== 'midata') {
            return new JsonResponse(['error' => 'Dieser Anbieter kann hier nicht getrennt werden'], 400);
        }

        $identity = $this->findIdentity($user, $provider);
        if (!$identity instanceof ExternalIdentity) {
            return new JsonResponse(['error' => 'Verbindung nicht gefunden'], 404);
        }
        if (!$this->canDisconnect($user, $identity)) {
            return new JsonResponse([
                'error' => 'last_login_method',
                'message' => 'MiData ist deine einzige Anmeldemöglichkeit. Bestätige zuerst deine E-Mail-Adresse oder verbinde einen anderen Anbieter.',
            ], 409);
        }

        $user->getExternalIdentities()->removeElement($identity);
        $this->entityManager->remove($identity);
        $this->auditLogger->log('user', $user->getId(), 'external_identity_unlinked', $user, $user, null, [
            'provider' => ['old' => $provider, 'new' => null],
        ]);
        $this->entityManager->flush();

        return $this->noStore(new JsonResponse(['identities' => $this->serializeExternalIdentities($user)]));
    }

    #[Route('/trusted-devices', name: 'trusted_devices', methods: ['GET'])]
    public function trustedDevices(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->noStore(new JsonResponse(['trusted_devices' => $this->serializeDevices($user, $request)]));
    }

    /** Vertrauen widerrufen beendet keine Sitzung; der nächste Login verlangt wieder MFA. */
    #[Route('/trusted-devices/{deviceId}', name: 'trusted_device_revoke', methods: ['DELETE'])]
    public function revokeTrustedDevice(string $id, string $deviceId, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->trustedDevices->revoke($user, $deviceId)) {
            return new JsonResponse(['error' => 'Gerät nicht gefunden'], 404);
        }

        return $this->noStore(new JsonResponse(['trusted_devices' => $this->serializeDevices($user, $request)]));
    }

    #[Route('/activity', name: 'activity', methods: ['GET'])]
    public function activity(string $id, Request $request): JsonResponse
    {
        $user = $this->requireOwnUser($id);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $limit = $request->query->getInt('limit', SecurityActivityService::DEFAULT_LIMIT);

        return $this->noStore(new JsonResponse(['events' => $this->activity->recent($user, $limit)]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeSessions(User $user): array
    {
        $currentId = $this->currentSession->getAuthenticated()?->getId();

        return array_map(function (UserSession $session) use ($user, $currentId): array {
            $ua = UserAgentSummary::describe($session->getUserAgent());
            $device = $session->getTrustedDevice();

            return [
                'id' => $session->getId(),
                'current' => $session->getId() === $currentId,
                'auth_method' => $session->getAuthMethod()->value,
                'browser' => $ua['browser'],
                'os' => $ua['os'],
                'label' => $ua['label'],
                'created_at' => $session->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'last_seen_at' => $session->getLastSeenAt()->format(\DateTimeInterface::ATOM),
                'mfa_verified' => $session->getMfaVerifiedAt() !== null,
                'mfa_source' => $session->getMfaSource(),
                'trusted' => $device !== null && $device->getUser()->getId() === $user->getId() && !$device->isRevoked(),
            ];
        }, $this->sessionManager->listActiveForUser($user));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeDevices(User $user, Request $request): array
    {
        return array_map(fn ($device): array => [
            'id' => $device->getId(),
            'label' => $device->getLabel(),
            'trusted_at' => $device->getTrustedAt()->format(\DateTimeInterface::ATOM),
            'last_used_at' => $device->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
            'valid_until' => $this->trustedDevices->effectiveExpiry($user, $device)->format(\DateTimeInterface::ATOM),
            'current' => $this->trustedDevices->isCurrent($user, $device, $request),
        ], $this->trustedDevices->listActive($user));
    }

    private function sessionRequired(): JsonResponse
    {
        return $this->noStore(new JsonResponse(['error' => 'session_required', 'message' => 'Bitte melde dich erneut an.'], 409));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** Nur das eigene Konto (keine Admin-Ausnahme). */
    private function requireOwnUser(string $profileId): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        if ($user->getProfileId() !== $profileId) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        return $user;
    }

    private function findIdentity(User $user, string $provider): ?ExternalIdentity
    {
        foreach ($user->getExternalIdentities() as $identity) {
            if ($identity->getProvider() === $provider) {
                return $identity;
            }
        }

        return null;
    }

    /**
     * Passwörter von Konten, die über einen Anbieter angelegt wurden, sind zufällig und unbekannt. Trennen ist deshalb
     * nur erlaubt, wenn eine andere Anmeldung bleibt: ein anderer Anbieter oder eine bestätigte E-Mail-Adresse
     * (Passwort-Zurücksetzen).
     */
    private function canDisconnect(User $user, ExternalIdentity $identity): bool
    {
        foreach ($user->getExternalIdentities() as $other) {
            if ($other !== $identity) {
                return true;
            }
        }

        return $user->isEmailVerified();
    }

    /** @return list<array{provider: string, label: string, linked_at: string, can_disconnect: bool}> */
    private function serializeExternalIdentities(User $user): array
    {
        $labels = ['midata' => 'MiData / db.scout.ch', 'google' => 'Google'];
        $result = [];
        foreach ($user->getExternalIdentities() as $identity) {
            $provider = $identity->getProvider();
            $result[] = [
                'provider' => $provider,
                'label' => $labels[$provider] ?? $provider,
                'linked_at' => $identity->getCreatedAt()->format('c'),
                'can_disconnect' => $provider === 'midata' && $this->canDisconnect($user, $identity),
            ];
        }

        return $result;
    }
}
