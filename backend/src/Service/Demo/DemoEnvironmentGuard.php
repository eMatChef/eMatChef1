<?php

declare(strict_types=1);

namespace App\Service\Demo;

use App\Service\DevEnvironmentService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Explizite Freigabe für Demo-Seed- und Demo-Wipe-Commands (fail-closed).
 *
 * `APP_ENV=prod` und `EMATCHEF_DEV_TOOLS=1` gelten auf Develop UND Staging und reichen deshalb nicht.
 * Die Umgebung wird über `EMATCHEF_ENV_NAME` benannt: local | develop | staging | production.
 *
 * - Hinzufügende Befehle (Konten anlegen/aktualisieren): Dev-Tools aktiv und Umgebung bekannt und nicht production.
 *   Auf einem prod-Kernel muss der Name explizit `develop` oder `staging` lauten.
 * - Zerstörende Befehle (Löschen, Wipe): zusätzlich nur `local` oder `develop`, auf `develop` nur mit
 *   `EMATCHEF_DEMO_DESTRUCTIVE=1`. Staging und Production sind nie freigegeben.
 *
 * @see docs/demo/SEED-KONZEPT.md §3.1 (P8, P12) und §7.9
 */
class DemoEnvironmentGuard
{
    public const ENV_LOCAL = 'local';
    public const ENV_DEVELOP = 'develop';
    public const ENV_STAGING = 'staging';
    public const ENV_PRODUCTION = 'production';

    private const KNOWN = [self::ENV_LOCAL, self::ENV_DEVELOP, self::ENV_STAGING, self::ENV_PRODUCTION];

    public function __construct(
        private KernelInterface $kernel,
        private DevEnvironmentService $devEnvironmentService,
        #[Autowire('%env(EMATCHEF_ENV_NAME)%')]
        private string $environmentName = '',
        #[Autowire('%env(bool:EMATCHEF_DEMO_DESTRUCTIVE)%')]
        private bool $destructiveEnabled = false,
    ) {
    }

    /** Normalisierter Name; leer auf einem Nicht-prod-Kernel = local, auf prod-Kernel bleibt leer (= unbekannt). */
    public function environmentName(): string
    {
        $name = strtolower(trim($this->environmentName));
        if ($name === '' && $this->kernel->getEnvironment() !== 'prod') {
            return self::ENV_LOCAL;
        }

        return $name;
    }

    /** Grund der Ablehnung für hinzufügende Seeds oder null, wenn erlaubt. */
    public function additiveDenial(): ?string
    {
        if (!$this->devEnvironmentService->isDevToolsEnabled()) {
            return 'Dev-Tools sind deaktiviert (EMATCHEF_DEV_TOOLS / APP_ENV).';
        }
        $name = $this->environmentName();
        if (!\in_array($name, self::KNOWN, true)) {
            return 'EMATCHEF_ENV_NAME fehlt oder ist unbekannt (erlaubt: local, develop, staging, production). Abbruch.';
        }
        if ($name === self::ENV_PRODUCTION) {
            return 'Demo-Befehle sind in der Umgebung «production» gesperrt.';
        }
        if ($name === self::ENV_LOCAL && $this->kernel->getEnvironment() === 'prod') {
            return 'EMATCHEF_ENV_NAME=local ist auf einem prod-Kernel nicht zulässig.';
        }

        return null;
    }

    /**
     * Nur die lokale Entwicklungsumgebung (WSL/Docker, Nicht-prod-Kernel, Name «local» oder leer).
     * Für einmalige Umbauten an Legacy-Daten, die nirgends sonst laufen dürfen (auch nicht als Dry-Run).
     */
    public function localOnlyDenial(): ?string
    {
        $denial = $this->additiveDenial();
        if ($denial !== null) {
            return $denial;
        }
        if ($this->environmentName() !== self::ENV_LOCAL || $this->kernel->getEnvironment() === 'prod') {
            return 'Dieser Befehl ist nur in der lokalen Umgebung erlaubt (Nicht-prod-Kernel, EMATCHEF_ENV_NAME leer oder «local»).';
        }

        return null;
    }

    /** Grund der Ablehnung für löschende Demo-Befehle oder null, wenn erlaubt. */
    public function destructiveDenial(): ?string
    {
        $denial = $this->additiveDenial();
        if ($denial !== null) {
            return $denial;
        }
        $name = $this->environmentName();
        if ($name === self::ENV_STAGING) {
            return 'Löschende Demo-Befehle sind auf Staging gesperrt.';
        }
        if ($name === self::ENV_DEVELOP && !$this->destructiveEnabled) {
            return 'Löschende Demo-Befehle auf Develop brauchen die explizite Freigabe EMATCHEF_DEMO_DESTRUCTIVE=1.';
        }

        return null;
    }
}
