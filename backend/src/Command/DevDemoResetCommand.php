<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DevEnvironmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fixe Dev-Vorlage: Rollen-User (@ematchef.ch / test) + E2E-Smoke ohne Department.
 * Nur wenn EMATCHEF_DEV_TOOLS aktiv (bzw. nicht APP_ENV=prod ohne Override).
 */
#[AsCommand(
    name: 'app:dev-demo:reset',
    description: 'Dev-Demo: Rollen-User neu anlegen + E2E-Smoke-User ohne Department'
)]
final class DevDemoResetCommand extends Command
{
    public function __construct(
        private DevEnvironmentService $devEnvironmentService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'e2e-password',
                null,
                InputOption::VALUE_REQUIRED,
                'Passwort für e2e-smoke@ematchef.ch (mind. 8 Zeichen). Fehlt → E2E-Schritt wird übersprungen.'
            )
            ->addOption(
                'skip-e2e',
                null,
                InputOption::VALUE_NONE,
                'E2E-Smoke-User nicht anlegen/aktualisieren'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->devEnvironmentService->isDevToolsEnabled()) {
            $io->error('Dev-Tools sind deaktiviert (EMATCHEF_DEV_TOOLS / APP_ENV). Abbruch.');

            return Command::FAILURE;
        }

        $application = $this->getApplication();
        if ($application === null) {
            $io->error('Console-Application nicht verfügbar.');

            return Command::FAILURE;
        }
        $application->setAutoExit(false);

        $io->title('Dev-Demo Reset');

        $io->section('Rollen-User (app:create-role-users)');
        $roleCode = $application->find('app:create-role-users')->run(new ArrayInput([]), $output);
        if ($roleCode !== Command::SUCCESS) {
            $io->error('app:create-role-users fehlgeschlagen.');

            return Command::FAILURE;
        }

        $skipE2e = (bool) $input->getOption('skip-e2e');
        $e2ePassword = (string) ($input->getOption('e2e-password') ?? '');

        if ($skipE2e) {
            $io->note('E2E-Smoke übersprungen (--skip-e2e).');
        } elseif ($e2ePassword === '') {
            $io->warning('Kein --e2e-password → E2E-Smoke nicht aktualisiert. Bestehenden User ggf. manuell mit app:ensure-e2e-user setzen.');
        } else {
            $io->section('E2E-Smoke ohne Department (app:ensure-e2e-user)');
            $e2eInput = new ArrayInput([
                'command' => 'app:ensure-e2e-user',
                '--email' => EnsureE2eUserCommand::DEFAULT_EMAIL,
                '--password' => $e2ePassword,
            ]);
            $e2eCode = $application->find('app:ensure-e2e-user')->run($e2eInput, $output);
            if ($e2eCode !== Command::SUCCESS) {
                $io->error('app:ensure-e2e-user fehlgeschlagen.');

                return Command::FAILURE;
            }
        }

        $io->success([
            'Dev-Demo bereit.',
            'Banner-Logins: *@ematchef.ch / test',
            'E2E: ' . EnsureE2eUserCommand::DEFAULT_EMAIL . ' (ohne Department, ausgeblendet in User-Suche)',
        ]);

        return Command::SUCCESS;
    }
}
