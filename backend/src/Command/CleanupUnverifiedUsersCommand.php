<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-unverified-users',
    description: 'Loescht unbestaetigte Benutzerkonten mit abgelaufener Verifikation'
)]
class CleanupUnverifiedUsersCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Gueltigkeit der Verifikation in Tagen', 10)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was geloescht wird');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($days < 1) {
            $io->error('Die Option --days muss mindestens 1 sein.');
            return Command::INVALID;
        }

        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days");
        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('p')
            ->where('u.emailVerified = false')
            ->andWhere('u.createdAt <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            $io->success("Keine unbestaetigten User aelter als {$days} Tage gefunden.");
            return Command::SUCCESS;
        }

        $io->section(sprintf('%d unbestaetigte User gefunden', count($users)));
        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                $user->getId(),
                $user->getProfile()?->getEmail() ?? '-',
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }
        $io->table(['User ID', 'E-Mail', 'Erstellt am'], $rows);

        if ($dryRun) {
            $io->warning('Dry-Run: keine Loeschung ausgefuehrt.');
            return Command::SUCCESS;
        }

        $deletedUsers = 0;
        $deletedProfiles = 0;
        foreach ($users as $user) {
            $profile = $user->getProfile();
            $this->entityManager->remove($user);
            $deletedUsers++;
            if ($profile) {
                $this->entityManager->remove($profile);
                $deletedProfiles++;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d User und %d Profile geloescht.', $deletedUsers, $deletedProfiles));
        return Command::SUCCESS;
    }
}
