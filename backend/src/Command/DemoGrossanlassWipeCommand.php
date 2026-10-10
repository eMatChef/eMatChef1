<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Bootstrap\DemoGrossanlassWipeService;
use App\Service\Demo\DemoEnvironmentGuard;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo-grossanlass:wipe',
    description: 'Dev: Demo-Grossanlass-Department (demo_mode) inkl. aller Daten löschen; gesperrt ohne Freigabe',
)]
final class DemoGrossanlassWipeCommand extends Command
{
    public function __construct(
        private DemoEnvironmentGuard $environmentGuard,
        private DemoGrossanlassWipeService $wipeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'confirm',
            null,
            InputOption::VALUE_REQUIRED,
            'Muss dem Department-Namen entsprechen (Bestätigung)',
        );
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

        $denial = $this->environmentGuard->destructiveDenial();
        if ($denial !== null) {
            $io->error($denial);

            return Command::FAILURE;
        }

        $name = (string) $input->getOption('name');
        if ((string) $input->getOption('confirm') !== $name) {
            $io->error(sprintf('Bestätigung fehlt: --confirm="%s" angeben.', $name));

            return Command::FAILURE;
        }

        try {
            $result = $this->wipeService->wipeByName($name);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
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
