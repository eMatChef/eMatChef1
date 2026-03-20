<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'app:list-users',
    description: 'Listet alle User mit ihren IDs auf'
)]
class ListUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->em->getRepository(User::class)->findAll();

        $table = new Table($output);
        $table->setHeaders(['User-ID', 'Profile-ID', 'Email', 'Name', 'ID-Format']);

        foreach ($users as $user) {
            $profile = $user->getProfile();
            $userId = $user->getId();
            $profileId = $user->getProfileId();
            $email = $profile ? $profile->getEmail() : 'N/A';
            $name = $profile ? trim(($profile->getFirstName() ?? '') . ' ' . ($profile->getLastName() ?? '')) : 'N/A';
            if ($name === '') {
                $name = $profile->getNickname() ?? 'N/A';
            }
            
            // Prüfe ID-Format
            $isHex = preg_match('/^[0-9a-f]{12}$/', $userId) && preg_match('/^[0-9a-f]{12}$/', $profileId);
            $format = $isHex ? '✅ Hex' : '❌ Alt';
            
            $table->addRow([$userId, $profileId, $email, $name, $format]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
