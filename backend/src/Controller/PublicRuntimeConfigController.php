<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Integration\IntegrationSettingsStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/runtime-config', name: 'api_public_runtime_config_')]
class PublicRuntimeConfigController extends AbstractController
{
    public function __construct(
        private readonly IntegrationSettingsStore $integrationSettings,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse([
            'autologout' => $this->integrationSettings->getAutoLogoutConfig(),
        ]);
    }
}

