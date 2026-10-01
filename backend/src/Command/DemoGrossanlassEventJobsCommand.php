<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Bootstrap\DemoGrossanlassEventJobsSeedService;
use App\Service\DevEnvironmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo-grossanlass:event-jobs',
    description: 'Dev: 20 Bauaufträge mit Material im Demo-Grossanlass-Event anlegen',
)]
final class DemoGrossanlassEventJobsCommand extends Command
{
    public function __construct(
        private DevEnvironmentService $devEnvironmentService,
        private DemoGrossanlassEventJobsSeedService $seedService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Department-Name',
            DemoGrossanlassEventJobsSeedService::DEFAULT_DEPARTMENT_NAME,
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
            $result = $this->seedService->seedByName($name);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '«%s»: %d Bauaufträge, %d Materialwünsche (%d neu / %d Wünsche neu).',
            $result['department'],
            $result['jobs'],
            $result['wishes'],
            $result['created_jobs'],
            $result['created_wishes'],
        ));
        $io->listing([
            'Holzbau → Zimmerei / Schreinerei',
            'Bühne & Gerüst → Gerüst- / Bühnenverleih',
            'Sanitär & Wasser → Sanitärverleih',
            'Energie & Strom → Elektro- / Stromverleih',
            'Zelte & Wetterschutz → Zeltverleih',
            'Sicherheit & Zaun → Zaun- / Absperrtechnik',
        ]);
        $io->note('Idempotent. Material liegt als grobe Wünsche in der offenen Runde «Material am Projekt».');

        return Command::SUCCESS;
    }
}
