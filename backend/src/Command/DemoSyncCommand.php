<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\Demo\Scenario\SeedResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:sync', description: 'Demo-Szenarien additiv und idempotent ergänzen (nie löschen, nie Zustände/Uhr überschreiben)')]
final class DemoSyncCommand extends AbstractDemoScenarioCommand
{
    public function __construct(DemoEnvironmentGuard $environmentGuard, DemoScenarioRegistry $registry, private DemoScenarioRunner $runner)
    {
        parent::__construct($environmentGuard, $registry);
    }

    protected function configure(): void
    {
        $this->addScenarioOptions(true);
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nichts schreiben');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->denied($io)) {
            return Command::FAILURE;
        }
        $scenarios = $this->selectedScenarios($input, $io, true);
        if ($scenarios === null) {
            return Command::FAILURE;
        }

        $failed = false;
        foreach ($scenarios as $scenario) {
            try {
                $result = $this->runner->sync($scenario, (bool) $input->getOption('dry-run'));
            } catch (DemoScenarioException $e) {
                $io->error(sprintf('%s: %s', $scenario->key(), $e->getMessage()));
                $failed = true;
                continue;
            }
            $line = sprintf('%s: %s', $scenario->key(), $result->message);
            match ($result->status) {
                SeedResult::OK => $io->success($line . sprintf(' (neu %d, vorhanden %d)', $result->created, $result->existing)),
                default => $io->note($line),
            };
            if ($result->notes !== []) {
                $io->listing($result->notes);
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
