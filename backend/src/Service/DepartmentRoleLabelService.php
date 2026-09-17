<?php

namespace App\Service;

use App\Entity\DepartmentSetting;
use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Löst Anzeige-Namen für Department-Mitgliedschaftsrollen auf.
 * L1–L3 können pro Department über department_setting (roles.label.*) umbenannt werden.
 */
class DepartmentRoleLabelService
{
    private const ROLE_SETTING_KEYS = [
        'l1' => 'roles.label.l1',
        'l2' => 'roles.label.l2',
        'l3' => 'roles.label.l3',
    ];

    private const FALLBACK_LABELS = [
        'mw' => 'Materialchef',
        'cmw' => 'Co-Materialchef',
        'dc' => 'Departmentchef',
        'komm' => 'Kommunikation',
        'spon' => 'Sponsoring',
        'l1' => 'Leiter 1',
        'l2' => 'Leiter 2',
        'l3' => 'Leiter 3',
        'u' => 'Mitglied',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function labelForRole(string $role, ?string $departmentId = null): string
    {
        $normalized = strtolower(trim($role));
        $aliases = [
            'matwart' => 'mw',
            'co_matwart' => 'cmw',
            'depchef' => 'dc',
            'kommunikation' => 'komm',
            'sponsoring' => 'spon',
            'leader1' => 'l1',
            'leader2' => 'l2',
            'leader3' => 'l3',
            'user' => 'u',
        ];
        $code = $aliases[$normalized] ?? $normalized;

        if ($departmentId && isset(self::ROLE_SETTING_KEYS[$code])) {
            $custom = $this->loadCustomLabel($departmentId, self::ROLE_SETTING_KEYS[$code]);
            if ($custom !== null) {
                return $custom;
            }
        }

        if ($code === 'dc' && $departmentId) {
            $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
            if ($department instanceof Department && $department->isGrossanlass()) {
                return 'OK-Leitung';
            }
        }

        return self::FALLBACK_LABELS[$code] ?? 'Mitglied';
    }

    /**
     * @return array{l1: string, l2: string, l3: string}
     */
    public function getCustomLeaderLabels(string $departmentId): array
    {
        $stored = $this->loadRoleSettings($departmentId);

        return [
            'l1' => trim((string) ($stored['roles.label.l1'] ?? '')),
            'l2' => trim((string) ($stored['roles.label.l2'] ?? '')),
            'l3' => trim((string) ($stored['roles.label.l3'] ?? '')),
        ];
    }

    private function loadCustomLabel(string $departmentId, string $settingKey): ?string
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => $settingKey,
        ]);
        if (!$setting) {
            return null;
        }
        $value = trim($setting->getSettingValue());

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    private function loadRoleSettings(string $departmentId): array
    {
        $settings = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(DepartmentSetting::class, 's')
            ->where('s.departmentId = :deptId')
            ->andWhere('s.settingKey LIKE :prefix')
            ->setParameter('deptId', $departmentId)
            ->setParameter('prefix', 'roles.%')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getSettingValue();
        }

        return $result;
    }
}
