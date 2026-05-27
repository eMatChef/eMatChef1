<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Dev-/Test-Tools (DB-Reset, Aktivitäten löschen) — in Produktion deaktiviert.
 */
class DevEnvironmentService
{
    public function __construct(
        private KernelInterface $kernel,
    ) {}

    public function isDevToolsEnabled(): bool
    {
        return $this->kernel->getEnvironment() !== 'prod';
    }
}
