<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\SecurityActivityService;
use App\Service\Auth\TrustedDeviceService;
use App\Service\Auth\UserSessionManager;
use App\Util\UserAgentSummary;
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
}
