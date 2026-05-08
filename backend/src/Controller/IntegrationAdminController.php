<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Integration\IntegrationSettingsStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/integrations/fcal', name: 'api_admin_integrations_fcal_')]
class IntegrationAdminController extends AbstractController
{
    public function __construct(
        private readonly IntegrationSettingsStore $integrationSettings,
    ) {
    }

    /**
     * Status nur für Superadmin (kein Key im Klartext).
     */
    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse([
            'fcal_api_key_configured' => $this->integrationSettings->isFcalApiKeyConfigured(),
            'auth_session_limit_per_minute' => $this->integrationSettings->getAuthSessionLimitPerMinute(),
            'auth_refresh_limit_per_minute' => $this->integrationSettings->getAuthRefreshLimitPerMinute(),
            'autologout' => $this->integrationSettings->getAutoLogoutConfig(),
        ]);
    }

    /**
     * API-Schlüssel setzen oder leeren (für alle Abteilungen).
     */
    #[Route('', name: 'put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Nur Superadmin kann den API-Schlüssel ändern'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }

        $key = isset($data['fcal_api_key']) && \is_string($data['fcal_api_key']) ? $data['fcal_api_key'] : '';
        $sessionLimitRaw = $data['auth_session_limit_per_minute'] ?? null;
        $refreshLimitRaw = $data['auth_refresh_limit_per_minute'] ?? null;
        $sessionLimit = is_numeric($sessionLimitRaw)
            ? (int) $sessionLimitRaw
            : $this->integrationSettings->getAuthSessionLimitPerMinute();
        $refreshLimit = is_numeric($refreshLimitRaw)
            ? (int) $refreshLimitRaw
            : $this->integrationSettings->getAuthRefreshLimitPerMinute();
        $autologoutRaw = \is_array($data['autologout'] ?? null) ? $data['autologout'] : [];

        try {
            $this->integrationSettings->setFcalApiKey($key);
            $this->integrationSettings->setAuthRateLimitsPerMinute($sessionLimit, $refreshLimit);
            $this->integrationSettings->setAutoLogoutConfig([
                'timeout_ms' => is_numeric($autologoutRaw['timeout_ms'] ?? null) ? (int) $autologoutRaw['timeout_ms'] : null,
                'warning_ms' => is_numeric($autologoutRaw['warning_ms'] ?? null) ? (int) $autologoutRaw['warning_ms'] : null,
                'activity_throttle_ms' => is_numeric($autologoutRaw['activity_throttle_ms'] ?? null) ? (int) $autologoutRaw['activity_throttle_ms'] : null,
                'refresh_interval_ms' => is_numeric($autologoutRaw['refresh_interval_ms'] ?? null) ? (int) $autologoutRaw['refresh_interval_ms'] : null,
                'activity_events' => isset($autologoutRaw['activity_events']) ? (string) $autologoutRaw['activity_events'] : '',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        return new JsonResponse([
            'fcal_api_key_configured' => $this->integrationSettings->isFcalApiKeyConfigured(),
            'auth_session_limit_per_minute' => $this->integrationSettings->getAuthSessionLimitPerMinute(),
            'auth_refresh_limit_per_minute' => $this->integrationSettings->getAuthRefreshLimitPerMinute(),
            'autologout' => $this->integrationSettings->getAutoLogoutConfig(),
        ]);
    }
}
