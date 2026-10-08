<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Scenario\DemoScenarioInterface;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Gemeinsame Grundstruktur der app:demo:*-Commands: Umgebungsfreigabe zuerst, dann Szenario-Auswahl. */
abstract class AbstractDemoScenarioCommand extends Command
{
    public function __construct(
        protected DemoEnvironmentGuard $environmentGuard,
        protected DemoScenarioRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function addScenarioOptions(bool $allowAll): void
    {
        $this->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Szenario-Schlüssel (materialverwaltung | grossanlass-event | grossanlass-camp)');
        if ($allowAll) {
            $this->addOption('all', null, InputOption::VALUE_NONE, 'Alle Szenarien');
        }
    }

    /** Prüft die Freigabe, bevor irgendetwas gelesen oder geschrieben wird. */
    protected function denied(SymfonyStyle $io, bool $destructive = false): bool
    {
        $denial = $destructive ? $this->environmentGuard->destructiveDenial() : $this->environmentGuard->additiveDenial();
        if ($denial !== null) {
            $io->error($denial);

            return true;
        }

        return false;
    }

    /** @return list<DemoScenarioInterface>|null null = ungültige Auswahl (Fehler ausgegeben) */
    protected function selectedScenarios(InputInterface $input, SymfonyStyle $io, bool $allowAll): ?array
    {
        $key = $input->getOption('scenario');
        $all = $allowAll && (bool) $input->getOption('all');
        if (($key === null || $key === '') === !$all) {
            $io->error($allowAll ? 'Genau eines von --scenario=<key> oder --all angeben.' : '--scenario=<key> angeben.');

            return null;
        }
        if ($all) {
            return array_values($this->registry->all());
        }
        if (!$this->registry->has((string) $key)) {
            $io->error(sprintf('Unbekanntes Szenario «%s». Bekannt: %s', $key, implode(', ', array_keys($this->registry->all()))));

            return null;
        }

        return [$this->registry->get((string) $key)];
    }
}
