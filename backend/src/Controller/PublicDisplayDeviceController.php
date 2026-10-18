<?php

namespace App\Controller;

use App\Service\Display\DepartmentDisplayDataService;
use App\Service\Display\DepartmentDisplayDeviceService;
use App\Service\Display\DisplayLiveDataService;
use App\Service\Display\DisplayMapDataService;
use App\Service\Display\DisplayRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Öffentliche Endpunkte des Anzeigegeräts. Authentifizierung ausschliesslich über das Geräte-Credential-Cookie
 * (kein User-Login); jeder Abruf wird serverseitig gegen Gerät, Widerruf, Freigabe und Screen geprüft.
 */
#[Route('/api/public/display-device', name: 'api_public_display_device_')]
class PublicDisplayDeviceController extends AbstractController
{
    private const FAIL_MAX = 60;
    private const FAIL_WINDOW = 600;

    public function __construct(
        private DepartmentDisplayDeviceService $deviceService,
        private DepartmentDisplayDataService $dataService,
        private DisplayRateLimiter $rateLimiter,
        private DisplayMapDataService $mapData,
        private DisplayLiveDataService $liveData,
    ) {
    }

    /** Zustand des Geräts (Startseite, Warteseite bei abgelaufener Freigabe); ohne Anzeigedaten. */
    #[Route('/session', name: 'session', methods: ['GET'])]
    public function session(Request $request): JsonResponse
    {
        return $this->respond($request, false);
    }

    /** Anzeigedaten des zugewiesenen Infoscreens, nur bei gültiger Freigabe. */
    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        return $this->respond($request, true);
    }

    /** Kartendaten (Orte, Bereiche, Pläne) des Grossanlasses des zugewiesenen Infoscreens, nur bei gültiger Freigabe. */
    #[Route('/map-data', name: 'map_data', methods: ['GET'])]
    public function mapDataAction(Request $request): JsonResponse
    {
        $identity = $this->activeIdentity($request);
        if ($identity instanceof JsonResponse) {
            return $identity;
        }

        return $this->noStore(new JsonResponse($this->mapData->forScreen($identity['screen'], true)));
    }

    /** Live-Daten der GA-Fachwidgets (Material, Disposition, Aufgaben, Ereignisse); nur lesend, nur bei gültiger Freigabe. */
    #[Route('/live-data', name: 'live_data', methods: ['GET'])]
    public function liveDataAction(Request $request): JsonResponse
    {
        $identity = $this->activeIdentity($request);
        if ($identity instanceof JsonResponse) {
            return $identity;
        }

        return $this->noStore(new JsonResponse($this->liveData->forScreen($identity['screen'])));
    }

    /** Geländeplan-Bild; nur Pläne des Departments des zugewiesenen Infoscreens. */
    #[Route('/map-image/{mapId}', name: 'map_image', methods: ['GET'], requirements: ['mapId' => '[A-Za-z0-9_-]+'])]
    public function mapImage(string $mapId, Request $request): Response
    {
        $identity = $this->activeIdentity($request);
        if ($identity instanceof JsonResponse) {
            return $identity;
        }
        $path = $this->mapData->mapImagePath($identity['screen'], $mapId);
        if ($path === null) {
            return $this->noStore(new JsonResponse(['error' => 'Nicht gefunden.'], 404));
        }
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse(['success' => true]);
        $response->headers->setCookie($this->deviceService->clearCookie());

        return $this->noStore($response);
    }

    /**
     * Gemeinsame Prüfung der Folge-Endpunkte (Karte): Credential, Widerruf, Freigabe; sonst 401/403 ohne Daten.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function activeIdentity(Request $request): array|JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $hasCookie = $request->cookies->has(DepartmentDisplayDeviceService::COOKIE_NAME);
        if ($hasCookie && $this->rateLimiter->isLimited('device_fail', $ip, self::FAIL_MAX)) {
            return $this->noStore(new JsonResponse(['error' => 'Zu viele Versuche. Bitte später erneut.'], 429));
        }
        $identity = $this->deviceService->identify($request);
        if ($identity['state'] === DepartmentDisplayDeviceService::STATE_ACTIVE) {
            return $identity;
        }
        if ($identity['state'] === DepartmentDisplayDeviceService::STATE_NONE && $hasCookie) {
            $this->rateLimiter->hit('device_fail', $ip, self::FAIL_WINDOW);
        }

        return $this->noStore(new JsonResponse(['state' => $identity['state']], $identity['state'] === DepartmentDisplayDeviceService::STATE_EXPIRED ? 403 : 401));
    }

    private function respond(Request $request, bool $withData): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $hasCookie = $request->cookies->has(DepartmentDisplayDeviceService::COOKIE_NAME);
        if ($hasCookie && $this->rateLimiter->isLimited('device_fail', $ip, self::FAIL_MAX)) {
            return $this->noStore(new JsonResponse(['error' => 'Zu viele Versuche. Bitte später erneut.'], 429));
        }

        $identity = $this->deviceService->identify($request);
        $state = $identity['state'];

        if ($state === DepartmentDisplayDeviceService::STATE_NONE) {
            if ($hasCookie) {
                $this->rateLimiter->hit('device_fail', $ip, self::FAIL_WINDOW);
            }

            return $this->noStore(new JsonResponse(['state' => $state], 401));
        }

        if ($state === DepartmentDisplayDeviceService::STATE_REVOKED) {
            $response = new JsonResponse(['state' => $state], 401);
            $response->headers->setCookie($this->deviceService->clearCookie());

            return $this->noStore($response);
        }

        $device = $identity['device'];
        $meta = [
            'state' => $state,
            'device' => [
                'id' => $device->getId(),
                'name' => $device->getName(),
                'approval_expires_at' => $device->getApprovalExpiresAt()->format('c'),
            ],
        ];

        if ($state === DepartmentDisplayDeviceService::STATE_EXPIRED) {
            $response = new JsonResponse($meta, 403);
        } elseif ($withData) {
            $response = new JsonResponse($meta + $this->dataService->buildPayloadForScreen($identity['screen'], $device));
        } else {
            $response = new JsonResponse($meta);
        }

        if (isset($identity['cookie'])) {
            $response->headers->setCookie($identity['cookie']);
        }

        return $this->noStore($response);
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
