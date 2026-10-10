<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoEnvironmentGuard;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fixe Dev-Vorlage: Demo-User (@demo.ematchef.ch, siehe data/seeds/dev-demo/demo-accounts.json) + E2E-Smoke ohne Department.
 * Nur wenn EMATCHEF_DEV_TOOLS aktiv (bzw. nicht APP_ENV=prod ohne Override).
 */
#[AsCommand(
    name: 'app:dev-demo:reset',
    description: 'Dev-Demo: Rollen-User anlegen/aktualisieren (löscht nichts) + E2E-Smoke-User ohne Department'
)]
final class DevDemoResetCommand extends Command
{
    public function __construct(
        private DemoEnvironmentGuard $environmentGuard,
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
            ->addOption('department', null, InputOption::VALUE_REQUIRED, 'Demo-Department-ID (wie app:create-role-users)')
            ->addOption('mark-department-demo', null, InputOption::VALUE_NONE, 'Department ausdrücklich als Demo markieren')
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

        $denial = $this->environmentGuard->additiveDenial();
        if ($denial !== null) {
            $io->error($denial);

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
        $roleArgs = [];
        if ($input->getOption('department')) {
            $roleArgs['--department'] = (string) $input->getOption('department');
        }
        if ($input->getOption('mark-department-demo')) {
            $roleArgs['--mark-department-demo'] = true;
        }
        $roleCode = $application->find('app:create-role-users')->run(new ArrayInput($roleArgs), $output);
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
            'Banner-Logins: *@' . \App\Util\DemoAccounts::domain() . ' / ' . CreateRoleUsersCommand::DEMO_PASSWORD,
            'E2E: ' . EnsureE2eUserCommand::DEFAULT_EMAIL . ' (ohne Department, ausgeblendet in User-Suche)',
        ]);

        return Command::SUCCESS;
    }
}
