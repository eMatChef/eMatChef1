<?php

namespace App\Command;

use App\Service\Bootstrap\DevBootstrapContextService;
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
        private DevBootstrapContextService $bootstrapContext,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existingOrg = $this->entityManager->getRepository(\App\Entity\Organisation::class)->findOneBy([]);
        if ($existingOrg) {
            $io->warning('Organisation existiert bereits: ' . $existingOrg->getName() . ' (ID: ' . $existingOrg->getId() . ')');

            return Command::SUCCESS;
        }

        [$organisation, $department] = $this->bootstrapContext->findOrCreateOrganisationAndDepartment();

        $io->success('Organisation erstellt: ' . $organisation->getName() . ' (ID: ' . $organisation->getId() . ')');
        $io->success('Department erstellt: ' . $department->getName() . ' (ID: ' . $department->getId() . ')');

        return Command::SUCCESS;
    }
}
