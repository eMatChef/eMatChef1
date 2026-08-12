<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Legt (oder aktualisiert) den dedizierten Playwright-Smoke-User an.
 * Standard: kein Department (nicht in User-Suche / Auto-Join).
 * Nur auf Develop/Staging verwenden — Passwort nicht committen.
 */
#[AsCommand(
    name: 'app:ensure-e2e-user',
    description: 'Stellt den E2E-Smoke-User sicher (email verified, aktiv, standardmäßig ohne Dept)'
)]
final class EnsureE2eUserCommand extends Command
{
    public const DEFAULT_EMAIL = 'e2e-smoke@ematchef.ch';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-Mail', self::DEFAULT_EMAIL)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Klartext-Passwort (nicht in Git/Historie speichern)')
            ->addOption(
                'department-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Optional: Department-ID zuweisen. Ohne Option: alle Memberships entfernen (kein Dept).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = strtolower(trim((string) $input->getOption('email')));
        $password = (string) $input->getOption('password');
        $departmentIdRaw = $input->getOption('department-id');
        $departmentId = \is_string($departmentIdRaw) ? trim($departmentIdRaw) : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Ungültige E-Mail.');

            return Command::FAILURE;
        }
        if (strlen($password) < 8) {
            $io->error('Passwort mindestens 8 Zeichen (--password=...).');

            return Command::FAILURE;
        }

        $department = null;
        if ($departmentId !== '') {
            $department = $this->em->getRepository(Department::class)->find($departmentId);
            if (!$department) {
                $io->error(sprintf('Department %s nicht gefunden.', $departmentId));

                return Command::FAILURE;
            }
        }

        $profile = $this->em->getRepository(Profile::class)->findOneBy(['email' => $email]);
        $user = $profile
            ? $this->em->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()])
            : null;

        $created = false;
        if (!$profile || !$user) {
            $created = true;
            $profile = new Profile();
            $profile->setId(IdGenerator::generateUnique($this->em, Profile::class));
            $profile->setEmail($email);
            $profile->setFirstName('E2E');
            $profile->setLastName('Smoke');
            $profile->setNickname('e2e-smoke');
            $profile->setLanguage('de');
            $profile->setRoles(['ROLE_USER']);
            $this->em->persist($profile);

            $user = new User();
            $user->setId(IdGenerator::generateUnique($this->em, User::class));
            $user->setProfileId($profile->getId());
            $user->setProfile($profile);
            $this->em->persist($user);
        }

        $user->setState('active');
        $user->setEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationExpiresAt(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $existingMemberships = $this->em->getRepository(Membership::class)->findBy([
            'userId' => $user->getId(),
        ]);
        foreach ($existingMemberships as $membership) {
            $this->em->remove($membership);
        }
        $this->em->flush();

        if ($department !== null) {
            $membership = new Membership();
            $membership->setUser($user);
            $membership->setDepartment($department);
            $membership->setRole('u');
            $membership->setIsPrimary(true);
            $this->em->persist($membership);
            $this->em->flush();

            $io->success($created
                ? sprintf('E2E-User angelegt: %s (User-ID %s)', $email, $user->getId())
                : sprintf('E2E-User aktualisiert: %s (User-ID %s)', $email, $user->getId())
            );
            $io->writeln(sprintf('Department: %s (%s), Rolle: u', $department->getName(), $department->getId()));
        } else {
            $this->em->flush();
            $io->success($created
                ? sprintf('E2E-User angelegt: %s (User-ID %s)', $email, $user->getId())
                : sprintf('E2E-User aktualisiert: %s (User-ID %s)', $email, $user->getId())
            );
            $io->writeln('Kein Department (Standard) — ausgeblendet in User-Suche.');
        }

        $io->note([
            'GitHub Secrets setzen: E2E_USER_EMAIL / E2E_USER_PASSWORD (und ggf. E2E_BASE_URL).',
            'Passwort nicht committen; --password erscheint in der Prozessliste — danach History säubern.',
        ]);

        return Command::SUCCESS;
    }
}
