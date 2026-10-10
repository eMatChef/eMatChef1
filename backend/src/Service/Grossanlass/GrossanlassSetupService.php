<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verbindliche Ersteinrichtung eines Grossanlasses. Drei Pflichtbereiche, danach die Freigabe:
 *  1. Stammdaten: Name, Anlassbeginn, Ort und GA-Typ (Lager oder Event).
 *  2. Ressorts und Verantwortlichkeiten: mindestens ein Ressort, jedes mit mindestens einer Leitung.
 *  3. Mitglieder und OK-Zuordnung: mindestens ein MW und eine OK-Leitung (Rolle dc).
 * Einrichten dürfen MW, Co-MW und OK-Leitung, freigeben nur MW und OK-Leitung. Globale Admins im Scope des Departments
 * behalten ihre bisherigen Rechte (sie unterliegen der Sperre nicht). Teilnahme von Departments und Materialleihe sind davon
 * unabhängige Konzepte und keine Pflichtbereiche.
 */
final class GrossanlassSetupService
{
    public const STEP_STAMMDATEN = 'stammdaten';
    public const STEP_RESSORTS = 'ressorts';
    public const STEP_MITGLIEDER = 'mitglieder';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassAccessService $access,
        private readonly AdminCapabilityChecker $adminCapabilities,
    ) {
    }

    public function isReleased(Department $department): bool
    {
        $config = $department->getGrossanlassConfig();

        // Ohne Config (kein Grossanlass) gibt es nichts zu sperren.
        return !$department->isGrossanlass() || $config === null || $config->isSetupReleased();
    }

    public function canSetup(User $user, Department $department): bool
    {
        return $this->adminCapabilities->canAdministerDepartment($user, $department->getId())
            || GrossanlassAccessRoles::canSetup($this->access->membershipRole($user, $department) ?? '');
    }

    public function canRelease(User $user, Department $department): bool
    {
        return $this->adminCapabilities->canAdministerDepartment($user, $department->getId())
            || GrossanlassAccessRoles::canReleaseSetup($this->access->membershipRole($user, $department) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function status(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->canSetup($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für die Einrichtung');
        }

        return $this->payload($department, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function release(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->canRelease($user, $department)) {
            throw new \RuntimeException('Nur MW oder OK-Leitung dürfen die Einrichtung freigeben');
        }
        $config = $this->requireConfig($department);
        if (!$config->isSetupReleased()) {
            $steps = $this->steps($department, $config);
            if (!$this->complete($steps)) {
                throw new GrossanlassSetupIncompleteException($steps);
            }
            $config->setSetupReleased(new \DateTime(), $user->getId());
            $this->entityManager->flush();
        }

        return $this->payload($department, $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Department $department, User $user): array
    {
        $config = $this->requireConfig($department);
        $steps = $this->steps($department, $config);

        return [
            'released' => $config->isSetupReleased(),
            'released_at' => $config->getSetupReleasedAt()?->format(\DateTimeInterface::ATOM),
            'can_setup' => $this->canSetup($user, $department),
            'can_release' => $this->canRelease($user, $department),
            'complete' => $this->complete($steps),
            'steps' => $steps,
        ];
    }

    /**
     * @return list<array{id: string, done: bool, missing: list<array<string, string>>}>
     */
    public function steps(Department $department, DepartmentGrossanlassConfig $config): array
    {
        $stammdaten = [];
        if (trim($department->getName()) === '') {
            $stammdaten[] = ['code' => 'name'];
        }
        if (trim($config->getLocationText()) === '' && $config->getVenueAddressId() === null) {
            $stammdaten[] = ['code' => 'location'];
        }
        if (!\in_array($config->getGuestActivityType(), [DepartmentGrossanlassConfig::GUEST_ACTIVITY_CAMP, DepartmentGrossanlassConfig::GUEST_ACTIVITY_EVENT], true)) {
            $stammdaten[] = ['code' => 'type'];
        }
        $end = $config->getPlannedEventEnd();
        if ($end !== null && $end < $config->getPlannedEventStart()) {
            $stammdaten[] = ['code' => 'period'];
        }

        $ressorts = [];
        $groups = $this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $department->getId(), 'parentId' => null]);
        if ($groups === []) {
            $ressorts[] = ['code' => 'no_ressort'];
        }
        foreach ($groups as $group) {
            $leaders = $this->entityManager->getRepository(GroupMembership::class)->count(['groupId' => $group->getId(), 'role' => 'leader']);
            if ($leaders === 0) {
                $ressorts[] = ['code' => 'ressort_without_leader', 'name' => $group->getName()];
            }
        }

        $roles = array_map(
            static fn (Membership $m): string => GrossanlassAccessRoles::normalize((string) $m->getRole()),
            $this->entityManager->getRepository(Membership::class)->findBy(['departmentId' => $department->getId()]),
        );
        $mitglieder = [];
        if (!\in_array('mw', $roles, true)) {
            $mitglieder[] = ['code' => 'no_mw'];
        }
        if (!\in_array('dc', $roles, true)) {
            $mitglieder[] = ['code' => 'no_ok'];
        }

        return [
            ['id' => self::STEP_STAMMDATEN, 'done' => $stammdaten === [], 'missing' => $stammdaten],
            ['id' => self::STEP_RESSORTS, 'done' => $ressorts === [], 'missing' => $ressorts],
            ['id' => self::STEP_MITGLIEDER, 'done' => $mitglieder === [], 'missing' => $mitglieder],
        ];
    }

    /** @param list<array{id: string, done: bool, missing: list<array<string, string>>}> $steps */
    private function complete(array $steps): bool
    {
        foreach ($steps as $step) {
            if (!$step['done']) {
                return false;
            }
        }

        return true;
    }

    private function requireConfig(Department $department): DepartmentGrossanlassConfig
    {
        $config = $department->getGrossanlassConfig();
        if ($config === null) {
            throw new \InvalidArgumentException('Grossanlass-Konfiguration fehlt');
        }

        return $config;
    }
}
