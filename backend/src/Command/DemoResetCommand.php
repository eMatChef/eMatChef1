<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:reset', description: 'Ein Demo-Szenario zurücksetzen (Phase 1: für alle Szenarien noch gesperrt)')]
final class DemoResetCommand extends AbstractDemoScenarioCommand
{
    public function __construct(DemoEnvironmentGuard $environmentGuard, DemoScenarioRegistry $registry, private DemoScenarioRunner $runner)
    {
        parent::__construct($environmentGuard, $registry);
    }

    protected function configure(): void
    {
        $this->addScenarioOptions(false);
        $this->addOption('confirm', null, InputOption::VALUE_REQUIRED, 'Muss dem Szenario-Schlüssel entsprechen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->denied($io, true)) {
            return Command::FAILURE;
        }
        $scenarios = $this->selectedScenarios($input, $io, false);
        if ($scenarios === null) {
            return Command::FAILURE;
        }
        $scenario = $scenarios[0];
        if ((string) $input->getOption('confirm') !== $scenario->key()) {
            $io->error(sprintf('Bestätigung fehlt: --confirm=%s angeben.', $scenario->key()));

            return Command::FAILURE;
        }

        try {
            $result = $this->runner->reset($scenario);
        } catch (DemoScenarioException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $io->success(sprintf('%s: %s', $scenario->key(), $result->message));

        return Command::SUCCESS;
    }
}
