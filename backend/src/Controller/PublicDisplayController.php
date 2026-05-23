<?php

namespace App\Controller;

use App\Service\Display\DepartmentDisplayDataService;
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
        private DepartmentDisplayDataService $displayDataService,
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

        return new JsonResponse([
            'authenticated' => true,
            'screen_name' => $screen->getName(),
            'public_id' => $screen->getPublicId(),
        ]);
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

        $cookie = $this->sessionService->createCookie($screen);
        $response = new JsonResponse([
            'authenticated' => true,
            'screen_name' => $screen->getName(),
            'public_id' => $screen->getPublicId(),
        ]);
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/{publicId}/data', name: 'data', methods: ['GET'])]
    public function data(string $publicId, Request $request): JsonResponse
    {
        $screen = $this->displayScreenService->findByPublicId($publicId);
        if ($screen === null || $screen->isRevoked()) {
            return new JsonResponse(['error' => 'Screen nicht gefunden'], 404);
        }

        $resolved = $this->sessionService->resolveScreenFromRequest($request, $publicId, $screen);
        if ($resolved === null) {
            return new JsonResponse(['error' => 'Nicht angemeldet'], 401);
        }

        return new JsonResponse($this->displayDataService->buildPayloadForScreen($screen));
    }

    #[Route('/{publicId}/logout', name: 'logout', methods: ['POST'])]
    public function logout(string $publicId): JsonResponse
    {
        $response = new JsonResponse(['success' => true]);
        $response->headers->setCookie($this->sessionService->createClearCookie());

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
