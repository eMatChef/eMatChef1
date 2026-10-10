<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Display\DisplayDeviceExpiryReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:display:expiry-reminders',
    description: 'Erinnert Verantwortliche 14 und 3 Tage vor Ablauf der Freigabe von Infoscreen-Geräten (täglich per Cron)',
)]
final class DisplayDeviceExpiryRemindersCommand extends Command
{
    public function __construct(private DisplayDeviceExpiryReminderService $reminders)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->reminders->process();
        $io->success(sprintf('%d Gerät(e) erinnert, %d Inbox-Nachricht(en) erstellt.', $result['devices'], $result['messages']));

        return Command::SUCCESS;
    }
}
