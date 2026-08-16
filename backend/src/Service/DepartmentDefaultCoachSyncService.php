<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\Membership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Default-J+S-Coach aus Mitglied mit is_js_coach (Department-Setting → User-ID + Stammdaten).
 */
class DepartmentDefaultCoachSyncService
{
    public const SETTING_USER_ID = 'js.default_coach_user_id';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Setzt oder aktualisiert den Default-Coach aus einem Mitglieds-User.
     * Mehrere is_js_coach sind erlaubt; Default ist optional und zeigt auf einen davon.
     */
    public function setDefaultCoachFromUser(Department $department, User $user): void
    {
        $departmentId = $department->getId();
        if ($departmentId === null) {
            return;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership || !$membership->getIsJsCoach()) {
            return;
        }

        $profile = $user->getProfile();
        $this->upsertSetting($department, self::SETTING_USER_ID, (string) $user->getId());
        $this->upsertSetting($department, 'js.default_coach_first_name', trim((string) ($profile?->getFirstName() ?? '')));
        $this->upsertSetting($department, 'js.default_coach_last_name', trim((string) ($profile?->getLastName() ?? '')));
        $this->upsertSetting($department, 'js.default_coach_email', trim((string) ($profile?->getEmail() ?? '')));
        $existingNr = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => 'js.default_coach_person_nr',
        ]);
        if (!$existingNr) {
            $this->upsertSetting($department, 'js.default_coach_person_nr', '');
        }
    }

    /**
     * Wenn der bisherige Default-Coach das Flag verliert: auf einen anderen Coach umstellen oder leeren.
     */
    public function refreshDefaultAfterFlagChange(Department $department, ?string $changedUserId = null): void
    {
        $departmentId = $department->getId();
        if ($departmentId === null) {
            return;
        }

        $currentDefaultId = $this->readSetting($departmentId, self::SETTING_USER_ID);
        $coaches = $this->entityManager->getRepository(Membership::class)->findBy([
            'departmentId' => $departmentId,
            'isJsCoach' => true,
        ]);

        $coachUserIds = array_map(static fn (Membership $m) => $m->getUserId(), $coaches);

        if ($currentDefaultId !== '' && in_array($currentDefaultId, $coachUserIds, true)) {
            $user = $this->entityManager->getRepository(User::class)->find($currentDefaultId);
            if ($user) {
                $this->setDefaultCoachFromUser($department, $user);
            }
            return;
        }

        if ($coaches === []) {
            $this->upsertSetting($department, self::SETTING_USER_ID, '');
            return;
        }

        // Neuer Default: erster verbleibender Coach (oder der geänderte, falls Flag gesetzt)
        $pickId = $changedUserId && in_array($changedUserId, $coachUserIds, true)
            ? $changedUserId
            : $coachUserIds[0];
        $user = $this->entityManager->getRepository(User::class)->find($pickId);
        if ($user) {
            $this->setDefaultCoachFromUser($department, $user);
        }
    }

    private function readSetting(string $departmentId, string $key): string
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => $key,
        ]);

        return $setting ? trim($setting->getSettingValue()) : '';
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
