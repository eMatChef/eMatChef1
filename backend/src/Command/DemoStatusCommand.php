<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Clock\BusinessClock;
use App\Service\Demo\Scenario\DemoScenarioIdentity;
use App\Service\Demo\Scenario\DemoSeedLedger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:status', description: 'Demo-Szenarien: Department, Uhr, Ledger (nur lesend)')]
final class DemoStatusCommand extends AbstractDemoScenarioCommand
{
    public function __construct(
        \App\Service\Demo\DemoEnvironmentGuard $environmentGuard,
        \App\Service\Demo\Scenario\DemoScenarioRegistry $registry,
        private DemoScenarioIdentity $identity,
        private DemoSeedLedger $ledger,
        private BusinessClock $clock,
    ) {
        parent::__construct($environmentGuard, $registry);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->denied($io)) {
            return Command::FAILURE;
        }

        $rows = [];
        foreach ($this->registry->all() as $key => $scenario) {
            $department = $this->identity->findDepartment($key);
            $rows[] = [
                $key,
                $department ? sprintf('%s (%s)', $department->getName(), $department->getId()) : '— nicht zugeordnet —',
                $department ? $this->clock->now($department)->format('Y-m-d H:i') : '',
                $department?->getDemoClockOffsetSeconds() ?? '',
                $department ? \count($this->ledger->forScenario($key)) : 0,
                $scenario->supportsReset() ? 'ja' : 'nein',
            ];
        }
        $io->table(['Szenario', 'Department', 'Demo-Zeit', 'Offset s', 'Ledger', 'Reset'], $rows);

        return Command::SUCCESS;
    }
}
