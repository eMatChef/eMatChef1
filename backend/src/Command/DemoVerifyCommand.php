<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:verify', description: 'Demo-Szenarien prüfen: Identität, Ownership, Invarianten (nur lesend)')]
final class DemoVerifyCommand extends AbstractDemoScenarioCommand
{
    public function __construct(DemoEnvironmentGuard $environmentGuard, DemoScenarioRegistry $registry, private DemoScenarioRunner $runner)
    {
        parent::__construct($environmentGuard, $registry);
    }

    protected function configure(): void
    {
        $this->addScenarioOptions(true);
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

        $violations = 0;
        foreach ($scenarios as $scenario) {
            $found = $this->runner->verify($scenario);
            $violations += \count($found);
            if ($found === []) {
                $io->text(sprintf('✓ %s', $scenario->key()));
                continue;
            }
            $io->error(array_map(static fn (string $v): string => $scenario->key() . ': ' . $v, $found));
        }

        return $violations > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
