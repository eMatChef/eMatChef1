<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\Activity;
use App\Entity\ActivityGrossanlassConfig;
use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Dev-Demo: eigenes Grossanlass-Department + Ressort für Rollen-User (CMW, OK-L, Komm, …).
 */
final class DemoGrossanlassSeedService
{
    public const DEPARTMENT_NAME = 'Demo Grossanlass';
    public const RESSORT_NAME = 'Demo-Ressort';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
        private WorkshopSparePartsCategoryBootstrapService $workshopSparePartsCategoryBootstrap,
    ) {
    }

    public function ensureDepartment(Organisation $organisation, User $createdBy): Department
    {
        $existing = $this->entityManager->getRepository(Department::class)->findOneBy([
            'organisationId' => $organisation->getId(),
            'name' => self::DEPARTMENT_NAME,
        ]);
        if ($existing instanceof Department && $existing->isGrossanlass()) {
            return $existing;
        }

        $start = new \DateTime('today');
        $end = (clone $start)->modify('+2 days')->setTime(23, 59, 59);

        $department = new Department();
        $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
        $department->setName(self::DEPARTMENT_NAME);
        $department->setOrganisation($organisation);
        $department->setIsGrossanlass(true);
        $this->entityManager->persist($department);

        $config = new DepartmentGrossanlassConfig();
        $config->setDepartment($department);
        $config->setStatus(DepartmentGrossanlassConfig::STATUS_DRAFT);
        $config->setStrukturModus(DepartmentGrossanlassConfig::STRUKTUR_OFFEN);
        $config->setPlannedEventStart($start);
        $config->setPlannedEventEnd($end);
        $this->entityManager->persist($config);
        $department->setGrossanlassConfig($config);

        $period = new DepartmentCalendarPeriod();
        $period->setId(IdGenerator::generate());
        $period->setDepartmentId($department->getId());
        $period->setLabel(DepartmentCalendarPeriod::LABEL_GROSSANLASS);
        $period->setName($department->getName());
        $period->setStartDate((clone $start)->setTime(0, 0, 0));
        $period->setEndDate((clone $end)->setTime(0, 0, 0));
        $period->setCreatedByUserId($createdBy->getId());
        $this->entityManager->persist($period);

        $activity = new Activity();
        $activity->setId(IdGenerator::generate());
        $activity->setDepartment($department);
        $activity->setName($department->getName());
        $activity->setType('grossanlass');
        $activity->setStatus(Activity::STATUS_DRAFT);
        $activity->setUsageStart($start);
        $activity->setUsageEnd($end);
        $activity->setCreatedByUser($createdBy);
        $activity->setResponsibleUser($createdBy);
        $activity->setNo(1);
        $this->entityManager->persist($activity);

        $activityConfig = new ActivityGrossanlassConfig();
        $activityConfig->setActivity($activity);
        $activityConfig->setGrossanlassRole(ActivityGrossanlassConfig::ROLE_ANLASS);
        $this->entityManager->persist($activityConfig);
        $config->setMainActivity($activity);

        $this->entityManager->flush();

        $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);
        $this->workshopSparePartsCategoryBootstrap->ensure($department);

        return $department;
    }

    public function ensureDemoRessort(Department $department, User $leader, User $member): Group
    {
        $group = $this->entityManager->getRepository(Group::class)->findOneBy([
            'departmentId' => $department->getId(),
            'name' => self::RESSORT_NAME,
        ]);
        if (!$group instanceof Group) {
            $group = new Group();
            $group->setId(IdGenerator::generateUnique($this->entityManager, Group::class));
            $group->setDepartment($department);
            $group->setName(self::RESSORT_NAME);
            $group->setGrossanlassKind(Group::GROSSANLASS_KIND_RESSORT);
            $this->entityManager->persist($group);
            $this->entityManager->flush();
        }

        $this->ensureGroupMembership($group, $leader, 'leader', true);
        $this->ensureGroupMembership($group, $member, 'member', true);
        $this->entityManager->flush();

        return $group;
    }

    private function ensureGroupMembership(Group $group, User $user, string $role, bool $isPrimary): void
    {
        $existing = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
            'userId' => $user->getId(),
            'groupId' => $group->getId(),
        ]);
        if ($existing instanceof GroupMembership) {
            $existing->setRole($role);
            $existing->setIsPrimary($isPrimary);

            return;
        }

        $row = new GroupMembership();
        $row->setUser($user);
        $row->setGroup($group);
        $row->setRole($role);
        $row->setIsPrimary($isPrimary);
        $this->entityManager->persist($row);
    }
}
