<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassConfig;

final class GrossanlassDepartmentSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function serializeDepartmentForMembership(Department $department): array
    {
        $data = [
            'id' => $department->getId(),
            'name' => $department->getName(),
            'organisation_id' => $department->getOrganisationId(),
            'is_grossanlass' => $department->isGrossanlass(),
        ];

        if ($department->isGrossanlass()) {
            $config = $department->getGrossanlassConfig();
            if ($config instanceof DepartmentGrossanlassConfig) {
                $data['grossanlass_config'] = self::serializeConfig($config);
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeConfig(DepartmentGrossanlassConfig $config): array
    {
        return [
            'status' => $config->getStatus(),
            'struktur_modus' => $config->getStrukturModus(),
            'planned_event_start' => $config->getPlannedEventStart()->format(\DateTimeInterface::ATOM),
            'planned_event_end' => $config->getPlannedEventEnd()?->format(\DateTimeInterface::ATOM),
            'main_activity_id' => $config->getMainActivityId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeCreateResponse(Department $department, DepartmentGrossanlassConfig $config): array
    {
        return [
            'id' => $department->getId(),
            'name' => $department->getName(),
            'organisation_id' => $department->getOrganisationId(),
            'parent_id' => $department->getParentId(),
            'is_grossanlass' => true,
            'grossanlass_config' => self::serializeConfig($config),
        ];
    }
}
