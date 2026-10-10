<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Profile;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Nur lesend: listet alle Orgchef-/Suborgchef-Konten mit ihrem Verwaltungs-Scope und der daraus folgenden Reichweite.
 * Ein leerer Scope gibt keine Verwaltungsrechte (früher: «alles»). Der Bericht zeigt vor einer Umstellung, welche Konten
 * davon betroffen sind. Es werden keine Scopes vergeben oder verändert.
 */
#[AsCommand(name: 'app:admin:scope-report', description: 'Lesend: Verwaltungs-Scopes von Orgchef/Suborgchef und Konten ohne Zuweisung auflisten')]
final class AdminScopeReportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminCapabilityChecker $adminCapabilities,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        $withoutScope = 0;
        foreach ($this->entityManager->getRepository(Profile::class)->findAll() as $profile) {
            $role = AdminCapabilityRegistry::resolveGlobalRole($profile->getRoles());
            if (!\in_array($role, [AdminCapabilityRegistry::GLOBAL_ROLE_ORG, AdminCapabilityRegistry::GLOBAL_ROLE_SUB], true)) {
                continue;
            }
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);
            if (!$user instanceof User) {
                continue;
            }
            $scope = $this->adminCapabilities->getScope($user);
            $hasScope = $this->adminCapabilities->hasAdministrativeScope($user);
            $withoutScope += $hasScope ? 0 : 1;
            $rows[] = [
                $profile->getEmail(),
                $role,
                $user->getState(),
                \count($scope['organisation_ids']),
                \count($scope['department_root_ids']),
                \count($this->adminCapabilities->getAccessibleDepartmentIds($user) ?? []),
                $hasScope ? 'ja' : 'NEIN: keine Verwaltungsrechte (früher unbeschränkt)',
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a[0], (string) $b[0]));

        $io->table(['Konto', 'Rolle', 'Status', 'Organisationen', 'Dept-Wurzeln', 'Departments im Bereich', 'Zuweisung'], $rows);
        $io->text(sprintf('%d Org-/Suborgchef-Konten, %d ohne Zuweisung. Es wurde nichts geändert.', \count($rows), $withoutScope));

        return Command::SUCCESS;
    }
}
