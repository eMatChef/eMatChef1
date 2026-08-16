<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Bootstrap\DemoSupplierSeedService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ensure-demo-supplier',
    description: 'Dev-Demo: Testfirma + supplier@ematchef.ch (Supplier-Bereich)'
)]
final class EnsureDemoSupplierCommand extends Command
{
    public function __construct(
        private DemoSupplierSeedService $demoSupplierSeed,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->demoSupplierSeed->ensure();
        $io->success([
            'Testfirma: ' . DemoSupplierSeedService::COMPANY_NAME,
            'Login: ' . DemoSupplierSeedService::EMAIL . ' / ' . CreateRoleUsersCommand::DEMO_PASSWORD,
        ]);

        return Command::SUCCESS;
    }
}
