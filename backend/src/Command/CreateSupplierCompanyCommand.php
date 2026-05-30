<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SupplierCompany;
use App\Service\Supplier\SupplierCompanyFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:supplier-company:create',
    description: 'Legt eine SupplierCompany mit Haupt-Adresse an (Dev/Support, Paket 1)'
)]
class CreateSupplierCompanyCommand extends Command
{
    public function __construct(
        private SupplierCompanyFactory $factory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Firmenname')
            ->addOption('manufacturer-key', null, InputOption::VALUE_OPTIONAL, 'Hersteller-Schlüssel (unique)')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'pending|active|suspended', SupplierCompany::STATUS_PENDING);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = trim((string) $input->getOption('name'));
        if ($name === '') {
            $io->error('--name ist erforderlich');
            return Command::FAILURE;
        }

        $company = $this->factory->createWithAddress(
            name: $name,
            manufacturerKey: $input->getOption('manufacturer-key') !== null
                ? (string) $input->getOption('manufacturer-key')
                : null,
            status: (string) $input->getOption('status'),
        );

        $io->success(sprintf(
            'SupplierCompany angelegt: %s (Adresse %s)',
            $company->getId(),
            $company->getSupplierAddressId()
        ));

        return Command::SUCCESS;
    }
}
