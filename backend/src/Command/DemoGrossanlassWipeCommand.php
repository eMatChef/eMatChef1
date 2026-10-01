<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Bootstrap\DemoGrossanlassWipeService;
use App\Service\DevEnvironmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo-grossanlass:wipe',
    description: 'Dev: Demo-Grossanlass-Department inkl. aller Daten löschen',
)]
final class DemoGrossanlassWipeCommand extends Command
{
    public function __construct(
        private DevEnvironmentService $devEnvironmentService,
        private DemoGrossanlassWipeService $wipeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Department-Name (Standard: Demo Grossanlass)',
            DemoGrossanlassSeedService::DEPARTMENT_NAME,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->devEnvironmentService->isDevToolsEnabled()) {
            $io->error('Dev-Tools sind deaktiviert (EMATCHEF_DEV_TOOLS / APP_ENV). Abbruch.');

            return Command::FAILURE;
        }

        $name = (string) $input->getOption('name');

        try {
            $result = $this->wipeService->wipeByName($name);
        } catch (\InvalidArgumentException $e) {
            $io->warning($e->getMessage());

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Grossanlass «%s» (%s) gelöscht.',
            $result['department_name'] ?? $name,
            $result['department_id'],
        ));

        $summary = array_filter(
            $result['deleted'],
            static fn (int $count): bool => $count > 0,
        );
        if ($summary !== []) {
            $io->listing(array_map(
                static fn (string $table, int $count): string => sprintf('%s: %d', $table, $count),
                array_keys($summary),
                array_values($summary),
            ));
        }

        $io->note([
            'GA-Testuser (ga-mw@, ga-helfer@, …) existieren weiter, haben aber kein Grossanlass-Dept mehr.',
            'Neu anlegen: Verwaltung → Abteilungen → Grossanlass hinzufügen (org/sub/sa).',
            'Demo-Szenario wiederherstellen: app:create-role-users --with-ga-demo',
        ]);

        return Command::SUCCESS;
    }
}
