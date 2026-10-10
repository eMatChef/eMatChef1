<?php

namespace App\Controller;

use App\Service\Display\DepartmentDisplaySessionService;
use App\Service\Display\DisplayPairingService;
use App\Service\Display\DisplayRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Öffentliche Endpunkte des Fernsehers für die QR-Kopplung (kein User-Login).
 */
#[Route('/api/public/display-pairing', name: 'api_public_display_pairing_')]
class PublicDisplayPairingController extends AbstractController
{
    private const CREATE_MAX = 20;
    private const CREATE_WINDOW = 600;
    private const POLL_FAIL_MAX = 20;
    private const POLL_FAIL_WINDOW = 900;

    public function __construct(
        private DisplayPairingService $pairingService,
        private DepartmentDisplaySessionService $sessionService,
        private DisplayRateLimiter $rateLimiter,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        if ($this->rateLimiter->isLimited('pair_create', $ip, self::CREATE_MAX)) {
            return new JsonResponse(['error' => 'Zu viele Versuche. Bitte später erneut.'], 429);
        }
        $this->rateLimiter->hit('pair_create', $ip, self::CREATE_WINDOW);

        $created = $this->pairingService->create();
        $pairing = $created['request'];

        return new JsonResponse([
            'request_id' => $pairing->getId(),
            'pair_url' => $this->pairingService->buildPairUrl($created['token']),
            'user_code' => $pairing->getUserCode(),
            'poll_secret' => $created['poll_secret'],
            'expires_at' => $pairing->getExpiresAt()->format('c'),
            'poll_interval_seconds' => DisplayPairingService::POLL_INTERVAL_SECONDS,
        ], 201);
    }

    /** POST statt GET: das Poll-Secret gehört nicht in URLs oder Access-Logs. */
    #[Route('/{requestId}/poll', name: 'poll', methods: ['POST'])]
    public function poll(string $requestId, Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        if ($this->rateLimiter->isLimited('pair_poll_fail', $ip, self::POLL_FAIL_MAX)) {
            return new JsonResponse(['error' => 'Zu viele Versuche. Bitte später erneut.'], 429);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $secret = is_array($data) ? (string) ($data['poll_secret'] ?? '') : '';

        $result = $secret === '' ? null : $this->pairingService->poll($requestId, $secret);
        if ($result === null) {
            $this->rateLimiter->hit('pair_poll_fail', $ip, self::POLL_FAIL_WINDOW);

            return new JsonResponse(['error' => 'Kopplungsanfrage nicht gefunden'], 404);
        }

        // Vor der Freigabe nur der Status, keine Screen-Daten.
        if ($result['status'] !== DisplayPairingService::POLL_APPROVED) {
            return new JsonResponse(['status' => $result['status']]);
        }

        $screen = $result['screen'];
        $response = new JsonResponse([
            'status' => DisplayPairingService::POLL_APPROVED,
            'public_id' => $screen->getPublicId(),
            'screen_name' => $screen->getName(),
        ]);
        $response->headers->setCookie($this->sessionService->createCookie($screen));

        return $response;
    }
}
