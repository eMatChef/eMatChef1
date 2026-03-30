<?php

namespace App\Command;

use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-initial-data',
    description: 'Erstellt initiale Organisation und Department'
)]
class CreateInitialDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Prüfe ob bereits Daten existieren
        $existingOrg = $this->entityManager->getRepository(Organisation::class)->findOneBy([]);
        if ($existingOrg) {
            $io->warning('Organisation existiert bereits: ' . $existingOrg->getName() . ' (ID: ' . $existingOrg->getId() . ')');
            return Command::SUCCESS;
        }

        // Erstelle Organisation
        $organisation = new Organisation();
        $organisation->setId(IdGenerator::generateUnique($this->entityManager, Organisation::class));
        $organisation->setName('Standard Organisation');
        
        $this->entityManager->persist($organisation);
        $this->entityManager->flush();

        $io->success('Organisation erstellt: ' . $organisation->getName() . ' (ID: ' . $organisation->getId() . ')');

        // Erstelle Department
        $department = new Department();
        $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
        $department->setName('Standard Department');
        $department->setOrganisation($organisation);
        
        $this->entityManager->persist($department);
        $this->entityManager->flush();

        $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);

        $io->success('Department erstellt: ' . $department->getName() . ' (ID: ' . $department->getId() . ')');
        $io->info('Organisation ID: ' . $organisation->getId());
        $io->info('Department ID: ' . $department->getId());

        return Command::SUCCESS;
    }
}
