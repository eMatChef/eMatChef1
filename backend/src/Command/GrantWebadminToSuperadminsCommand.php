<?php

namespace App\Command;

use App\Entity\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fügt ROLE_WEBADMIN zu allen Profilen hinzu, die bereits ROLE_SUPERADMIN haben
 * (Webseiten-Editor in der App).
 */
#[AsCommand(
    name: 'app:grant-webadmin-to-superadmins',
    description: 'ROLE_WEBADMIN für alle Superadmin-Profile setzen (fehlt nur, wenn noch nicht gesetzt)'
)]
class GrantWebadminToSuperadminsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->em->getRepository(Profile::class);
        /** @var Profile[] $profiles */
        $profiles = $repo->findAll();

        $updated = 0;
        foreach ($profiles as $profile) {
            $roles = $profile->getRoles();
            if (!\in_array('ROLE_SUPERADMIN', $roles, true)) {
                continue;
            }
            if (\in_array('ROLE_WEBADMIN', $roles, true)) {
                continue;
            }
            $roles[] = 'ROLE_WEBADMIN';
            $profile->setRoles(array_values(array_unique($roles)));
            $updated++;
        }

        if ($updated === 0) {
            $io->success('Keine Änderung nötig (alle Superadmins haben ROLE_WEBADMIN bereits, oder es gibt keine Superadmins).');

            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success(sprintf('%d Profil(e) aktualisiert: ROLE_WEBADMIN ergänzt.', $updated));

        return Command::SUCCESS;
    }
}
