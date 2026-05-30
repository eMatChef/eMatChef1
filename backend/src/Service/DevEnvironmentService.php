<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Dev-/Test-Tools (DB-Reset, Aktivitäten löschen) — in Produktion deaktiviert.
 * Auf dem Develop-Droplet läuft APP_ENV=prod; dort EMATCHEF_DEV_TOOLS=1 setzen.
 */
class DevEnvironmentService
{
    public function __construct(
        private KernelInterface $kernel,
        #[Autowire('%env(bool:EMATCHEF_DEV_TOOLS)%')]
        private bool $devToolsOverride = false,
    ) {}

    public function isDevToolsEnabled(): bool
    {
        return $this->kernel->getEnvironment() !== 'prod' || $this->devToolsOverride;
    }
}
