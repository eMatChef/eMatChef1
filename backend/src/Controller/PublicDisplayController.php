<?php

namespace App\Controller;

use App\Entity\DepartmentDisplayDevice;
use App\Service\Display\DepartmentDisplayDeviceService;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DepartmentDisplaySessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/display', name: 'api_public_display_')]
class PublicDisplayController extends AbstractController
{
    private const PIN_MAX_ATTEMPTS = 12;
    private const PIN_WINDOW_SECONDS = 900;

    public function __construct(
        private DepartmentDisplayScreenService $displayScreenService,
        private DepartmentDisplaySessionService $sessionService,
        private DepartmentDisplayDeviceService $deviceService,
        private CacheItemPoolInterface $cache,
    ) {
    }

    #[Route('/{publicId}/lookup', name: 'lookup', methods: ['GET'])]
    public function lookup(string $publicId): JsonResponse
    {
        $screen = $this->displayScreenService->findByPublicId($publicId);
        if ($screen === null || $screen->isRevoked()) {
            return new JsonResponse(['error' => 'Screen nicht gefunden'], 404);
        }

        return new JsonResponse(['valid' => true]);
    }

    /**
     * Übergang aus Phase 5.1: ein gültiges Alt-Cookie dieses Browsers wird einmalig in ein eigenes Gerät überführt
     * (Freigabe höchstens bis zum bisherigen Ablauf, nie länger). Jeder Browser behält seine eigene Identität;
     * es werden keine Geräte zusammengeführt. Danach werden die Alt-Cookies gelöscht.
     */
    #[Route('/{publicId}/session', name: 'session', methods: ['GET'])]
    public function session(string $publicId, Request $request): JsonResponse
    {
        $screen = $this->displayScreenService->findByPublicId($publicId);
        if ($screen === null || $screen->isRevoked()) {
            return new JsonResponse(['error' => 'Screen nicht gefunden'], 404);
        }

        $resolved = $this->sessionService->resolveScreenFromRequest($request, $publicId, $screen);
        if ($resolved === null) {
            return new JsonResponse(['authenticated' => false], 401);
        }

        $expires = min($resolved['exp'], time() + DepartmentDisplayDeviceService::APPROVAL_DAYS * 86400);
        $created = $this->deviceService->create(
            $screen,
            'Migriertes Gerät ' . (new \DateTime())->format('d.m.Y'),
            DepartmentDisplayDevice::VIA_MIGRATED,
            null,
            (new \DateTime())->setTimestamp($expires),
        );

        $response = new JsonResponse([
            'authenticated' => true,
            'screen_name' => $screen->getName(),
            'public_id' => $screen->getPublicId(),
        ]);
        $response->headers->setCookie($this->deviceService->buildCookie($created['device'], $created['secret']));
        $response->headers->setCookie($this->sessionService->createClearCookie($publicId));
        $response->headers->setCookie($this->sessionService->createLegacyClearCookie());

        return $response;
    }

    #[Route('/{publicId}/authenticate', name: 'authenticate', methods: ['POST'])]
    public function authenticate(string $publicId, Request $request): JsonResponse
    {
        $screen = $this->displayScreenService->findByPublicId($publicId);
        if ($screen === null || $screen->isRevoked()) {
            return new JsonResponse(['error' => 'Screen nicht gefunden'], 404);
        }

        if ($this->isPinRateLimited($request, $publicId)) {
            return new JsonResponse(['error' => 'Zu viele Versuche. Bitte später erneut.'], 429);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $code = (string) ($data['access_code'] ?? $data['code'] ?? '');

        if (!$this->displayScreenService->verifyAccessCode($screen, $code)) {
            $this->recordPinFailure($request, $publicId);

            return new JsonResponse(['error' => 'Zugangscode ungültig'], 401);
        }

        $this->clearPinFailures($request, $publicId);
        $this->displayScreenService->touchLastUsed($screen);

        // Manuelle Anmeldung (ID + Code): es entsteht ein eigenes Gerät mit 90 Tagen Freigabe.
        $created = $this->deviceService->create(
            $screen,
            'Manuell ' . (new \DateTime())->format('d.m.Y H:i'),
            DepartmentDisplayDevice::VIA_MANUAL,
        );
        $response = new JsonResponse([
            'authenticated' => true,
            'screen_name' => $screen->getName(),
            'public_id' => $screen->getPublicId(),
        ]);
        $response->headers->setCookie($this->deviceService->buildCookie($created['device'], $created['secret']));

        return $response;
    }

    #[Route('/{publicId}/logout', name: 'logout', methods: ['POST'])]
    public function logout(string $publicId): JsonResponse
    {
        $response = new JsonResponse(['success' => true]);
        $response->headers->setCookie($this->sessionService->createClearCookie($publicId));
        $response->headers->setCookie($this->deviceService->clearCookie());

        return $response;
    }

    private function pinCacheKey(Request $request, string $publicId): string
    {
        $ip = $request->getClientIp() ?? 'unknown';

        return 'display_pin|' . hash('sha256', $publicId . '|' . $ip);
    }

    private function isPinRateLimited(Request $request, string $publicId): bool
    {
        return $this->getPinAttempts($request, $publicId) >= self::PIN_MAX_ATTEMPTS;
    }

    private function getPinAttempts(Request $request, string $publicId): int
    {
        $item = $this->cache->getItem($this->pinCacheKey($request, $publicId));
        if (!$item->isHit()) {
            return 0;
        }
        $value = $item->get();

        return is_numeric($value) ? (int) $value : 0;
    }

    private function recordPinFailure(Request $request, string $publicId): void
    {
        $key = $this->pinCacheKey($request, $publicId);
        $item = $this->cache->getItem($key);
        $count = $this->getPinAttempts($request, $publicId) + 1;
        $item->set($count);
        $item->expiresAfter(self::PIN_WINDOW_SECONDS);
        $this->cache->save($item);
    }

    private function clearPinFailures(Request $request, string $publicId): void
    {
        $this->cache->deleteItem($this->pinCacheKey($request, $publicId));
    }
}
