<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Workshop\WorkshopOrderReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:workshop:order-reminders',
    description: 'Prüft fällige Werkstatt-Bestell-Erinnerungen in der Inbox',
)]
final class WorkshopOrderRemindersCommand extends Command
{
    public function __construct(
        private WorkshopOrderReminderService $orderReminderService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $due = $this->orderReminderService->processDueReminders();
        $io->success(sprintf('%d fällige Werkstatt-Bestell-Erinnerung(en) in der Inbox.', $due));

        return Command::SUCCESS;
    }
}
