<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Legacy\LegacyDemoRename;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:legacy-rename', description: 'Lokal: Legacy-Demo-Strukturen mit «old-» kennzeichnen (nur Namen; Dry-Run ist Standard; löscht nichts)')]
final class DemoLegacyRenameCommand extends Command
{
    public function __construct(private DemoEnvironmentGuard $environmentGuard, private LegacyDemoRename $rename)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Umbenennung wirklich ausführen (sonst nur Dry-Run)')
            ->addOption('confirm', null, InputOption::VALUE_REQUIRED, 'Bestätigungscode aus dem Dry-Run (nötig mit --execute)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $denial = $this->environmentGuard->localOnlyDenial();
        if ($denial !== null) {
            $io->error($denial);

            return Command::FAILURE;
        }

        $items = $this->rename->plan();
        $structural = array_values(array_filter($items, static fn (array $i): bool => $i['type'] !== 'group'));
        $io->table(['Typ', 'ID', 'Aktuell', 'Neu', 'Status'], array_map(
            static fn (array $i): array => [$i['type'], $i['id'], $i['current'], $i['target'], $i['status'] . ($i['reason'] !== '' ? ' (' . $i['reason'] . ')' : '')],
            $structural,
        ));

        $groups = array_values(array_filter($items, static fn (array $i): bool => $i['type'] === 'group'));
        $byStatus = [];
        foreach ($groups as $g) {
            $byStatus[$g['status']] = ($byStatus[$g['status']] ?? 0) + 1;
        }
        $io->text(sprintf('Gruppen in Legacy-Departments: %d (%s)', \count($groups), implode(', ', array_map(static fn (string $s, int $n): string => "$s: $n", array_keys($byStatus), $byStatus)) ?: 'keine'));
        if ($output->isVerbose()) {
            $io->table(['Gruppe', 'Department', 'Aktuell', 'Neu', 'Status'], array_map(
                static fn (array $g): array => [$g['id'], $g['parent'], $g['current'], $g['target'], $g['status']],
                $groups,
            ));
        }

        $pending = array_filter($items, static fn (array $i): bool => $i['status'] === 'rename');
        $token = LegacyDemoRename::token($items);
        if ($pending === []) {
            $io->success('Nichts zu tun: alles bereits gekennzeichnet (oder nicht vorhanden).');

            return Command::SUCCESS;
        }

        if (!$input->getOption('execute')) {
            $io->note([
                sprintf('Dry-Run: %d Namen würden geändert. Es wurde nichts geschrieben.', \count($pending)),
                'Ausführen: --execute --confirm=' . $token,
                'Geändert werden nur Anzeigenamen; IDs, Benutzer, Mitgliedschaften und Daten bleiben, nichts wird gelöscht.',
            ]);

            return Command::SUCCESS;
        }

        if ((string) $input->getOption('confirm') !== $token) {
            $io->error('Bestätigung fehlt oder passt nicht zum aktuellen Plan. Erwartet: --confirm=' . $token);

            return Command::FAILURE;
        }

        $count = $this->rename->apply($items);
        $io->success(sprintf('%d Namen mit «%s» gekennzeichnet.', $count, LegacyDemoRename::PREFIX));

        return Command::SUCCESS;
    }
}
