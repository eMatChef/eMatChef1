<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Public liveness for uptime monitors (e.g. Better Stack). No auth, no secrets.
 */
#[Route('/api/health', name: 'api_health_')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('', name: 'check', methods: ['GET', 'HEAD'])]
    public function check(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();

            return new JsonResponse(['status' => 'ok'], JsonResponse::HTTP_OK);
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'error'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
