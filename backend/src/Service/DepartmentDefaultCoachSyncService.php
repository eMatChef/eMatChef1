<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\Membership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rolle «coach» → Default-J+S-Coach in den Department-Settings übernehmen.
 */
class DepartmentDefaultCoachSyncService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function applyCoachRole(Department $department, User $user): void
    {
        $departmentId = $department->getId();
        if ($departmentId === null) {
            return;
        }

        // Nur ein Coach pro Department — andere zurück auf Mitglied
        $others = $this->entityManager->getRepository(Membership::class)->findBy([
            'departmentId' => $departmentId,
            'role' => 'coach',
        ]);
        foreach ($others as $membership) {
            if ($membership->getUserId() === $user->getId()) {
                continue;
            }
            $membership->setRole('u');
        }

        $profile = $user->getProfile();
        $first = trim((string) ($profile?->getFirstName() ?? ''));
        $last = trim((string) ($profile?->getLastName() ?? ''));
        $email = trim((string) ($profile?->getEmail() ?? ''));

        $this->upsertSetting($department, 'js.default_coach_first_name', $first);
        $this->upsertSetting($department, 'js.default_coach_last_name', $last);
        $this->upsertSetting($department, 'js.default_coach_email', $email);
        // Personen-Nr. bleibt manuell / unverändert, falls schon gesetzt
        $existingNr = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => 'js.default_coach_person_nr',
        ]);
        if (!$existingNr) {
            $this->upsertSetting($department, 'js.default_coach_person_nr', '');
        }
    }

    private function upsertSetting(Department $department, string $key, string $value): void
    {
        $departmentId = $department->getId();
        if ($departmentId === null) {
            return;
        }

        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => $key,
        ]);
        if (!$setting instanceof DepartmentSetting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey($key);
            $this->entityManager->persist($setting);
        }
        $setting->setSettingValue($value);
    }
}
