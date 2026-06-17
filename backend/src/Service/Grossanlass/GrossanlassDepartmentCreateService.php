<?php

namespace App\Service\Grossanlass;

use App\Entity\Activity;
use App\Entity\ActivityGrossanlassConfig;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Repository\DepartmentRepository;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\AuditLogger;
use App\Service\InboxMessageService;
use App\Service\OrganisationUserPickerFilter;
use App\Service\Public\PublicCodeService;
use App\Service\VerificationEmailService;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassDepartmentCreateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentRepository $departmentRepository,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
        private WorkshopSparePartsCategoryBootstrapService $workshopSparePartsCategoryBootstrap,
        private PublicCodeService $publicCodeService,
        private VerificationEmailService $verificationEmailService,
        private InboxMessageService $inboxMessageService,
        private AuditLogger $auditLogger,
        private AdminCapabilityChecker $adminCapabilityChecker,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @return array{department: Department, config: DepartmentGrossanlassConfig, chief_mw_user: ?User}
     */
    public function create(User $currentUser, array $data): array
    {
        if (!isset($data['name'], $data['organisation_id'], $data['planned_event_start'])) {
            throw new \InvalidArgumentException('Name, organisation_id und planned_event_start sind erforderlich');
        }

        if (!$this->adminCapabilityChecker->hasGlobalAdminRole($currentUser)) {
            throw new \RuntimeException('Zugriff verweigert');
        }

        $organisation = $this->entityManager->getRepository(Organisation::class)->find($data['organisation_id']);
        if (!$organisation) {
            throw new \InvalidArgumentException('Organisation nicht gefunden');
        }
        if (!OrganisationUserPickerFilter::isVisibleForUserPickers($organisation)) {
            throw new \InvalidArgumentException('Organisation nicht verfuegbar');
        }
        if (!$this->adminCapabilityChecker->canAccessOrganisation($currentUser, $organisation->getId())) {
            throw new \RuntimeException('Zugriff verweigert');
        }

        $parent = null;
        if (!empty($data['parent_id'])) {
            $parent = $this->departmentRepository->find($data['parent_id']);
            if (!$parent) {
                throw new \InvalidArgumentException('Parent Department nicht gefunden');
            }
            if (!$this->adminCapabilityChecker->canAccessDepartment($currentUser, $parent->getId())) {
                throw new \RuntimeException('Zugriff verweigert');
            }
            if ($parent->getOrganisationId() !== $organisation->getId()) {
                throw new \InvalidArgumentException('Parent Department muss zur gleichen Organisation gehören');
            }
        }

        $plannedStart = $this->parseDate((string) $data['planned_event_start'], false);
        $plannedEndRaw = $data['planned_event_end'] ?? null;
        $plannedEnd = $plannedEndRaw !== null && $plannedEndRaw !== ''
            ? $this->parseDate((string) $plannedEndRaw, true)
            : (clone $plannedStart)->setTime(23, 59, 59);

        if ($plannedEnd < $plannedStart) {
            throw new \InvalidArgumentException('Anlassdatum bis muss am oder nach Anlassdatum von liegen');
        }

        $departmentName = trim((string) $data['name']);
        if ($departmentName === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        $conflict = $this->departmentRepository->findConflictingByOrganisationAndName(
            $organisation->getId(),
            $departmentName,
        );
        if ($conflict instanceof Department) {
            throw new \InvalidArgumentException(
                'Ein Department mit diesem oder einem sehr ähnlichen Namen existiert bereits: «' . $conflict->getName() . '»',
                409,
            );
        }

        $chiefMwUser = null;
        if (!empty($data['chief_mw_user_id'])) {
            $chiefMwUser = $this->entityManager->getRepository(User::class)->find($data['chief_mw_user_id']);
            if (!$chiefMwUser) {
                throw new \InvalidArgumentException('Chief-MW User nicht gefunden');
            }
            if ($chiefMwUser->hasSuperAdminProfile()) {
                throw new \InvalidArgumentException('Superadmin-Konten sind keiner Abteilung zuordenbar');
            }
        }

        return $this->entityManager->wrapInTransaction(function () use (
            $currentUser,
            $data,
            $organisation,
            $parent,
            $plannedStart,
            $plannedEnd,
            $chiefMwUser,
            $departmentName,
        ) {
            $department = new Department();
            $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
            $department->setName($departmentName);
            $department->setOrganisation($organisation);
            $department->setIsGrossanlass(true);
            if ($parent) {
                $department->setParent($parent);
            }
            $this->entityManager->persist($department);

            $config = new DepartmentGrossanlassConfig();
            $config->setDepartment($department);
            $config->setStatus(DepartmentGrossanlassConfig::STATUS_DRAFT);
            $config->setStrukturModus(DepartmentGrossanlassConfig::STRUKTUR_OFFEN);
            $config->setPlannedEventStart($plannedStart);
            $config->setPlannedEventEnd($plannedEnd);
            $this->entityManager->persist($config);
            $department->setGrossanlassConfig($config);

            $activity = new Activity();
            $activity->setId(IdGenerator::generate());
            $activity->setDepartment($department);
            $activity->setName($department->getName());
            $activity->setType('grossanlass');
            $activity->setStatus(Activity::STATUS_DRAFT);
            $activity->setUsageStart($plannedStart);
            $activity->setUsageEnd($plannedEnd);
            $activity->setCreatedByUser($currentUser);
            $activity->setResponsibleUser($currentUser);
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
            $this->publicCodeService->ensureActivityPublicCode($activity, $currentUser->getId());

            if ($chiefMwUser) {
                $membership = new Membership();
                $membership->setUser($chiefMwUser);
                $membership->setDepartment($department);
                $membership->setRole('mw');
                $membership->setIsPrimary(true);

                $this->auditLogger->log(
                    'membership',
                    AuditLogger::buildMembershipEntityId($chiefMwUser->getId(), $department->getId()),
                    'membership_created',
                    $currentUser,
                    $chiefMwUser,
                    $department,
                    [
                        'role' => ['old' => null, 'new' => 'mw'],
                        'is_primary' => ['old' => null, 'new' => true],
                    ]
                );

                $this->entityManager->persist($membership);
            }

            $this->entityManager->flush();

            return [
                'department' => $department,
                'config' => $config,
                'chief_mw_user' => $chiefMwUser,
            ];
        });
    }

    public function notifyChiefMw(User $adder, Department $department, DepartmentGrossanlassConfig $config, User $chiefMw): void
    {
        $profile = $chiefMw->getProfile();
        if ($profile && filter_var($profile->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $adderName = trim((string) ($adder->getProfile()?->getDisplayName() ?? ''));
            if ($adderName === '') {
                $adderName = 'Ein Teammitglied';
            }
            try {
                $this->verificationEmailService->sendDepartmentMemberAddedEmail(
                    $profile->getEmail(),
                    $profile->getDisplayName(),
                    $adderName,
                    $department->getName(),
                    'Materialchef',
                    $profile->getLanguage()
                );
            } catch (\Throwable) {
            }
        }

        $this->inboxMessageService->notifyGrossanlassMwAssigned(
            $chiefMw,
            $department,
            $config,
            $adder->getId()
        );
    }

    private function parseDate(string $value, bool $endOfDay): \DateTime
    {
        $value = trim($value);
        $formats = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt instanceof \DateTime) {
                if ($format === 'Y-m-d') {
                    $dt->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);
                }

                return $dt;
            }
        }

        try {
            $dt = new \DateTime($value);
            if (strlen($value) === 10) {
                $dt->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);
            }

            return $dt;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Ungültiges Datum: ' . $value, 0, $e);
        }
    }
}
