<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioIdentity;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:adopt', description: 'Ein bestehendes Demo-Department (demo_mode) ausdrücklich einem Szenario zuordnen')]
final class DemoAdoptCommand extends AbstractDemoScenarioCommand
{
    public function __construct(
        DemoEnvironmentGuard $environmentGuard,
        DemoScenarioRegistry $registry,
        private DemoScenarioIdentity $identity,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct($environmentGuard, $registry);
    }

    protected function configure(): void
    {
        $this->addScenarioOptions(false);
        $this->addOption('department', null, InputOption::VALUE_REQUIRED, 'Department-ID (muss demo_mode tragen; keine Zuordnung über Namen)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->denied($io)) {
            return Command::FAILURE;
        }
        $scenarios = $this->selectedScenarios($input, $io, false);
        if ($scenarios === null) {
            return Command::FAILURE;
        }
        $id = (string) $input->getOption('department');
        $department = $id === '' ? null : $this->entityManager->getRepository(\App\Entity\Department::class)->find($id);
        if (!$department instanceof \App\Entity\Department) {
            $io->error('--department=<id> eines bestehenden Departments angeben.');

            return Command::FAILURE;
        }

        try {
            $this->identity->assign($department, $scenarios[0]->key());
        } catch (DemoScenarioException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $io->success(sprintf('Department «%s» (%s) gehört zum Szenario «%s».', $department->getName(), $department->getId(), $scenarios[0]->key()));

        return Command::SUCCESS;
    }
}
