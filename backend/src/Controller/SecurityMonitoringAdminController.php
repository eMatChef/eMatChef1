<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Security\SecurityAlertingStore;
use App\Service\Security\SecurityMetricsStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/security-monitoring', name: 'api_admin_security_monitoring_')]
final class SecurityMonitoringAdminController extends AbstractController
{
    public function __construct(
        private readonly SecurityMetricsStore $metrics,
        private readonly SecurityAlertingStore $alerting,
    ) {}

    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }
        if (
            !$this->isGranted('ROLE_SUPERADMIN')
            && !$this->isGranted('ROLE_ORGANISATIONSCHEF')
            && !$this->isGranted('ROLE_SUBORGCHEF')
        ) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $minutes = max(1, min(1440, (int) $request->query->get('minutes', 60)));
        $historyLimit = max(1, min(500, (int) $request->query->get('history_limit', 100)));

        return new JsonResponse([
            ...$this->metrics->snapshot($minutes),
            'login_threshold' => $this->alerting->loginThresholdConfig(),
            'alerts' => $this->alerting->recentEvents($historyLimit),
        ]);
    }
}

