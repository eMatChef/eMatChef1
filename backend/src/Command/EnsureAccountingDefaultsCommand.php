<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Department;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:accounting:ensure-defaults',
    description: 'Legt fehlende Standard-Kostenstellen und Zuordnungsregeln pro Department an',
)]
final class EnsureAccountingDefaultsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingCostCenterBootstrapService $bootstrap,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $departments = $this->entityManager->getRepository(Department::class)->findAll();
        $deptCount = 0;
        $ccTotal = 0;
        $rulesTotal = 0;

        foreach ($departments as $department) {
            if (!$department instanceof Department) {
                continue;
            }
            $result = $this->bootstrap->ensureDefaults($this->entityManager, $department);
            $ccTotal += $result['cost_centers_created'];
            $rulesTotal += $result['rules_created'];
            $deptCount++;
        }

        $io->success(sprintf(
            '%d Department(s): %d Kostenstelle(n) und %d Regel(n) neu angelegt.',
            $deptCount,
            $ccTotal,
            $rulesTotal,
        ));

        return Command::SUCCESS;
    }
}
