<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Activity;
use App\Entity\ActivityGrossanlassConfig;
use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\DepartmentGrossanlassParticipant;
use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\InboxMessageService;
use App\Service\Public\PublicCodeService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassPlanungService
{
    /** @var list<string> */
    public const PHASE_ROLES = [
        ActivityGrossanlassConfig::ROLE_AUFBAU,
        ActivityGrossanlassConfig::ROLE_ABBAU,
        ActivityGrossanlassConfig::ROLE_VOREVENT,
        ActivityGrossanlassConfig::ROLE_NACH_EVENT,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassGroupService $groups,
        private PublicCodeService $publicCodes,
        private InboxMessageService $inbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertMember($department, $user);
        $config = $this->requireConfig($department);

        return [
            'config' => GrossanlassDepartmentSerializer::serializeConfig($config),
            'department_name' => $department->getName(),
            'checks' => $this->checks($department, $config),
            'activities' => $this->listActivities($department),
            'ressorts' => $this->listRessortSummaries($department),
            'storage_locations' => $this->listStorageLocations($department),
            'participants' => $this->listParticipants($department),
            'can_manage' => $this->access->canManagePlanung($user, $department),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, array $data): array
    {
        $this->assertManage($department, $user);
        $config = $this->requireConfig($department);

        if (array_key_exists('struktur_modus', $data)) {
            $modus = trim((string) $data['struktur_modus']);
            $allowed = [
                DepartmentGrossanlassConfig::STRUKTUR_OFFEN,
                DepartmentGrossanlassConfig::STRUKTUR_VERSCHACHTELT,
                DepartmentGrossanlassConfig::STRUKTUR_PARALLEL,
            ];
            if (!in_array($modus, $allowed, true)) {
                throw new \InvalidArgumentException('Ungültiger Strukturmodus');
            }
            $config->setStrukturModus($modus);
        }
        if (array_key_exists('location_text', $data)) {
            $config->setLocationText((string) $data['location_text']);
        }
        if (array_key_exists('notes', $data)) {
            $config->setNotes(trim((string) $data['notes']));
        }
        if (array_key_exists('guest_activity_type', $data)) {
            $type = trim((string) $data['guest_activity_type']);
            $allowed = [
                DepartmentGrossanlassConfig::GUEST_ACTIVITY_CAMP,
                DepartmentGrossanlassConfig::GUEST_ACTIVITY_EVENT,
            ];
            if (!in_array($type, $allowed, true)) {
                throw new \InvalidArgumentException('Gäste sehen Lager oder Event');
            }
            $config->setGuestActivityType($type);
        }
        if (isset($data['planned_event_start']) && is_string($data['planned_event_start']) && $data['planned_event_start'] !== '') {
            $start = $this->parseDate($data['planned_event_start'], false);
            $endRaw = $data['planned_event_end'] ?? null;
            $end = is_string($endRaw) && $endRaw !== ''
                ? $this->parseDate($endRaw, true)
                : (clone $start)->setTime(23, 59, 59);
            if ($end < $start) {
                throw new \InvalidArgumentException('Anlassdatum bis muss am oder nach Anlassdatum von liegen');
            }
            $config->setPlannedEventStart($start);
            $config->setPlannedEventEnd($end);
            $main = $config->getMainActivity();
            if ($main instanceof Activity) {
                $main->setUsageStart($start);
                $main->setUsageEnd($end);
            }
            $this->syncEventCalendarPeriod($department, $start, $end);
        }

        $this->entityManager->flush();

        return $this->overview($department, $user);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPhaseActivity(Department $department, User $user, array $data): array
    {
        $this->assertManage($department, $user);
        $config = $this->requireConfig($department);
        $role = trim((string) ($data['role'] ?? ''));
        if (!in_array($role, self::PHASE_ROLES, true)) {
            throw new \InvalidArgumentException('Ungültige Activity-Rolle');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = match ($role) {
                ActivityGrossanlassConfig::ROLE_AUFBAU => $department->getName() . ' Aufbau',
                ActivityGrossanlassConfig::ROLE_ABBAU => $department->getName() . ' Abbau',
                ActivityGrossanlassConfig::ROLE_VOREVENT => $department->getName() . ' Vorevent',
                default => $department->getName() . ' Nach-Event',
            };
        }
        $plannedStart = $config->getPlannedEventStart();
        $plannedEnd = $config->getPlannedEventEnd() ?? (clone $plannedStart)->setTime(23, 59, 59);
        $start = isset($data['usage_start']) && is_string($data['usage_start']) && $data['usage_start'] !== ''
            ? $this->parseDate($data['usage_start'], false)
            : match ($role) {
                ActivityGrossanlassConfig::ROLE_AUFBAU => (clone $plannedStart)->modify('-2 days')->setTime(8, 0, 0),
                ActivityGrossanlassConfig::ROLE_ABBAU => (clone $plannedEnd)->modify('+1 day')->setTime(8, 0, 0),
                default => clone $plannedStart,
            };
        $end = isset($data['usage_end']) && is_string($data['usage_end']) && $data['usage_end'] !== ''
            ? $this->parseDate($data['usage_end'], true)
            : match ($role) {
                ActivityGrossanlassConfig::ROLE_AUFBAU => (clone $plannedStart)->modify('-1 day')->setTime(18, 0, 0),
                ActivityGrossanlassConfig::ROLE_ABBAU => (clone $plannedEnd)->modify('+2 days')->setTime(18, 0, 0),
                default => clone $plannedEnd,
            };
        if ($end < $start) {
            throw new \InvalidArgumentException('Zeitraum bis muss am oder nach von liegen');
        }

        $maxNo = (int) $this->entityManager->createQueryBuilder()
            ->select('MAX(a.no)')
            ->from(Activity::class, 'a')
            ->where('a.departmentId = :id')
            ->setParameter('id', $department->getId())
            ->getQuery()
            ->getSingleScalarResult();

        $activity = new Activity();
        $activity->setId(IdGenerator::generate());
        $activity->setDepartment($department);
        $activity->setName($name);
        $activity->setType('grossanlass');
        $activity->setStatus(Activity::STATUS_DRAFT);
        $activity->setUsageStart($start);
        $activity->setUsageEnd($end);
        $activity->setCreatedByUser($user);
        $activity->setResponsibleUser($user);
        $activity->setNo($maxNo + 1);
        $activity->setCreateWizardCompleted(true);
        $this->entityManager->persist($activity);

        $roleCfg = new ActivityGrossanlassConfig();
        $roleCfg->setActivity($activity);
        $roleCfg->setGrossanlassRole($role);
        $this->entityManager->persist($roleCfg);
        $this->entityManager->flush();
        $this->publicCodes->ensureActivityPublicCode($activity, $user->getId());
        $this->entityManager->flush();

        return $this->overview($department, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $config = $this->requireConfig($department);
        if ($config->getStatus() === DepartmentGrossanlassConfig::STATUS_PUBLISHED) {
            return $this->overview($department, $user);
        }
        $checks = $this->checks($department, $config);
        if (!$checks['period']) {
            throw new \InvalidArgumentException('Anlass-Zeitraum fehlt');
        }
        $config->markPublished($user->getId());
        $now = new \DateTime();
        foreach ($this->participantsForHost($department) as $row) {
            if ($row->getStatus() === DepartmentGrossanlassParticipant::STATUS_PLANNED) {
                $row->setStatus(DepartmentGrossanlassParticipant::STATUS_PENDING);
                $row->setInvitedAt($now);
            }
        }
        $this->entityManager->flush();
        $this->inbox->syncGrossanlassParticipantInvites($department);

        return $this->overview($department, $user);
    }

    /**
     * @return list<array{id: string, name: string, organisation_name: string}>
     */
    public function searchGuests(Department $department, User $user, string $query): array
    {
        $this->assertManage($department, $user);
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $taken = [];
        foreach ($this->participantsForHost($department) as $row) {
            $taken[$row->getGuestDepartmentId()] = true;
        }

        $rows = $this->entityManager->getRepository(Department::class)->createQueryBuilder('d')
            ->innerJoin('d.organisation', 'o')
            ->addSelect('o')
            ->where('d.id != :self')
            ->andWhere('d.isGrossanlass = false')
            ->andWhere('LOWER(d.name) LIKE :q OR LOWER(o.name) LIKE :q')
            ->setParameter('self', $department->getId())
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->orderBy('d.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof Department || isset($taken[$row->getId()])) {
                continue;
            }
            $out[] = [
                'id' => $row->getId(),
                'name' => $row->getName(),
                'organisation_name' => $row->getOrganisation()?->getName() ?? '',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function addParticipant(Department $department, User $user, string $guestDepartmentId): array
    {
        $this->assertManage($department, $user);
        $guestId = trim($guestDepartmentId);
        if ($guestId === '' || $guestId === $department->getId()) {
            throw new \InvalidArgumentException('Ungültige Abteilung');
        }
        $guest = $this->entityManager->getRepository(Department::class)->find($guestId);
        if (!$guest instanceof Department) {
            throw new \InvalidArgumentException('Abteilung nicht gefunden');
        }
        if ($guest->isGrossanlass()) {
            throw new \InvalidArgumentException('Ein Grossanlass kann nicht Teilnehmer sein');
        }
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassParticipant::class)->findOneBy([
            'hostDepartmentId' => $department->getId(),
            'guestDepartmentId' => $guest->getId(),
        ]);
        if ($existing instanceof DepartmentGrossanlassParticipant) {
            throw new \InvalidArgumentException('Abteilung ist bereits Teilnehmer');
        }

        $config = $this->requireConfig($department);
        $row = new DepartmentGrossanlassParticipant();
        $row->setId(IdGenerator::generateUnique($this->entityManager, DepartmentGrossanlassParticipant::class));
        $row->setHostDepartment($department);
        $row->setGuestDepartment($guest);
        if ($config->getStatus() === DepartmentGrossanlassConfig::STATUS_PUBLISHED) {
            $row->setStatus(DepartmentGrossanlassParticipant::STATUS_PENDING);
            $row->setInvitedAt(new \DateTime());
        } else {
            $row->setStatus(DepartmentGrossanlassParticipant::STATUS_PLANNED);
        }
        $this->entityManager->persist($row);
        $this->entityManager->flush();
        if ($row->getStatus() === DepartmentGrossanlassParticipant::STATUS_PENDING) {
            $this->inbox->syncGrossanlassParticipantInvites($department);
        }

        return $this->overview($department, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeParticipant(Department $department, User $user, string $participantId): array
    {
        $this->assertManage($department, $user);
        $row = $this->entityManager->getRepository(DepartmentGrossanlassParticipant::class)->find($participantId);
        if (!$row instanceof DepartmentGrossanlassParticipant || $row->getHostDepartment()->getId() !== $department->getId()) {
            throw new \InvalidArgumentException('Teilnehmer nicht gefunden');
        }
        if ($row->getStatus() === DepartmentGrossanlassParticipant::STATUS_ACCEPTED) {
            throw new \InvalidArgumentException('Zugesagte Abteilungen können nicht entfernt werden');
        }
        $this->inbox->removeGrossanlassParticipantInvite($row);
        $this->entityManager->remove($row);
        $this->entityManager->flush();

        return $this->overview($department, $user);
    }

    /**
     * @return array{ok: true, guest_activity_id: string|null}
     */
    public function respondInvite(Department $guestDepartment, User $user, string $participantId, string $decision, ?string $groupId): array
    {
        if (!in_array($decision, [DepartmentGrossanlassParticipant::STATUS_ACCEPTED, DepartmentGrossanlassParticipant::STATUS_REJECTED], true)) {
            throw new \InvalidArgumentException('decision muss accepted oder rejected sein');
        }
        $this->assertGuestManager($guestDepartment, $user);
        $row = $this->entityManager->getRepository(DepartmentGrossanlassParticipant::class)->find($participantId);
        if (!$row instanceof DepartmentGrossanlassParticipant || $row->getGuestDepartmentId() !== $guestDepartment->getId()) {
            throw new \InvalidArgumentException('Einladung nicht gefunden');
        }
        if ($row->getStatus() !== DepartmentGrossanlassParticipant::STATUS_PENDING) {
            throw new \InvalidArgumentException('Einladung ist nicht mehr offen');
        }

        $host = $row->getHostDepartment();
        $config = $host->getGrossanlassConfig();
        if (!$config instanceof DepartmentGrossanlassConfig) {
            throw new \InvalidArgumentException('Grossanlass-Konfiguration fehlt');
        }

        $group = null;
        if ($decision === DepartmentGrossanlassParticipant::STATUS_ACCEPTED) {
            $gid = trim((string) $groupId);
            if ($gid === '') {
                throw new \InvalidArgumentException('Gruppe ist bei Annahme nötig');
            }
            $group = $this->entityManager->getRepository(Group::class)->find($gid);
            if (!$group instanceof Group || $group->getDepartmentId() !== $guestDepartment->getId()) {
                throw new \InvalidArgumentException('Gruppe gehört nicht zu dieser Abteilung');
            }
        }

        $row->setStatus($decision);
        $row->setDecidedAt(new \DateTime());
        $row->setDecidedByUserId($user->getId());
        $row->setGuestGroup($group);

        $activityId = null;
        if ($decision === DepartmentGrossanlassParticipant::STATUS_ACCEPTED) {
            $activity = $this->createGuestActivity($guestDepartment, $user, $host, $config, $group);
            $row->setGuestActivity($activity);
            $activityId = $activity->getId();
        }

        $this->entityManager->flush();
        $this->inbox->removeGrossanlassParticipantInvite($row);
        if ($activityId !== null && $row->getGuestActivity() instanceof Activity) {
            $this->publicCodes->ensureActivityPublicCode($row->getGuestActivity(), $user->getId());
            $this->entityManager->flush();
        }

        return ['ok' => true, 'guest_activity_id' => $activityId];
    }

    /**
     * @return array{period: bool, ressorts: bool, participants: bool}
     */
    private function checks(Department $department, DepartmentGrossanlassConfig $config): array
    {
        $ressorts = $this->listRessortSummaries($department);

        return [
            'period' => $config->getPlannedEventStart() instanceof \DateTime,
            'ressorts' => $ressorts !== [],
            'participants' => $this->participantsForHost($department) !== [],
        ];
    }

    /**
     * @return list<DepartmentGrossanlassParticipant>
     */
    private function participantsForHost(Department $department): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassParticipant::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.guestDepartment', 'g')
            ->addSelect('g')
            ->where('p.hostDepartmentId = :id')
            ->setParameter('id', $department->getId())
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listParticipants(Department $department): array
    {
        $out = [];
        foreach ($this->participantsForHost($department) as $row) {
            $guest = $row->getGuestDepartment();
            $out[] = [
                'id' => $row->getId(),
                'department_id' => $guest->getId(),
                'name' => $guest->getName(),
                'organisation_name' => $guest->getOrganisation()?->getName() ?? '',
                'status' => $row->getStatus(),
                'guest_activity_id' => $row->getGuestActivityId(),
            ];
        }

        return $out;
    }

    private function createGuestActivity(
        Department $guest,
        User $user,
        Department $host,
        DepartmentGrossanlassConfig $config,
        Group $group,
    ): Activity {
        $type = $config->getGuestActivityType() === DepartmentGrossanlassConfig::GUEST_ACTIVITY_EVENT
            ? 'event'
            : 'camp';
        $start = $config->getPlannedEventStart();
        $end = $config->getPlannedEventEnd() ?? (clone $start)->setTime(23, 59, 59);
        $maxNo = (int) $this->entityManager->createQueryBuilder()
            ->select('MAX(a.no)')
            ->from(Activity::class, 'a')
            ->where('a.departmentId = :id')
            ->setParameter('id', $guest->getId())
            ->getQuery()
            ->getSingleScalarResult();

        $activity = new Activity();
        $activity->setId(IdGenerator::generate());
        $activity->setDepartment($guest);
        $activity->setName($host->getName());
        $activity->setType($type);
        $activity->setStatus(Activity::STATUS_DRAFT);
        $activity->setUsageStart($start);
        $activity->setUsageEnd($end);
        $activity->setGroup($group);
        $activity->setCreatedByUser($user);
        $activity->setResponsibleUser($user);
        $activity->setNo($maxNo + 1);
        $activity->setCreateWizardCompleted(true);
        $this->entityManager->persist($activity);

        return $activity;
    }

    private function assertGuestManager(Department $department, User $user): void
    {
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        $role = strtolower(trim((string) ($membership?->getRole() ?? '')));
        if (!in_array($role, ['mw', 'dc', 'org', 'sub', 'sa'], true)) {
            throw new \RuntimeException('Keine Berechtigung für diese Einladung');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listActivities(Department $department): array
    {
        $activities = $this->entityManager->getRepository(Activity::class)
            ->createQueryBuilder('a')
            ->where('a.departmentId = :id')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.type = :type')
            ->setParameter('id', $department->getId())
            ->setParameter('type', 'grossanlass')
            ->orderBy('a.usageStart', 'ASC')
            ->getQuery()
            ->getResult();

        $roles = [];
        if ($activities !== []) {
            $ids = [];
            foreach ($activities as $activity) {
                if ($activity instanceof Activity) {
                    $ids[] = $activity->getId();
                }
            }
            $configs = $this->entityManager->getRepository(ActivityGrossanlassConfig::class)
                ->createQueryBuilder('c')
                ->where('c.activityId IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
            foreach ($configs as $row) {
                if ($row instanceof ActivityGrossanlassConfig) {
                    $roles[$row->getActivityId()] = $row->getGrossanlassRole();
                }
            }
        }

        $out = [];
        foreach ($activities as $activity) {
            if (!$activity instanceof Activity) {
                continue;
            }
            $out[] = [
                'id' => $activity->getId(),
                'name' => $activity->getName(),
                'role' => $roles[$activity->getId()] ?? ActivityGrossanlassConfig::ROLE_ANLASS,
                'status' => $activity->getStatus(),
                'usage_start' => $activity->getUsageStart()?->format(\DateTimeInterface::ATOM),
                'usage_end' => $activity->getUsageEnd()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, name: string, node_type: string, member_count: int}>
     */
    private function listRessortSummaries(Department $department): array
    {
        $rows = [];
        foreach ($this->groups->listGroups($department) as $group) {
            $rows[] = [
                'id' => (string) ($group['id'] ?? ''),
                'name' => (string) ($group['name'] ?? ''),
                'node_type' => (string) ($group['node_type'] ?? 'ressort'),
                'member_count' => (int) ($group['member_count'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: string, name: string, is_primary: bool}>
     */
    private function listStorageLocations(Department $department): array
    {
        $addresses = $this->entityManager->getRepository(Address::class)
            ->createQueryBuilder('a')
            ->where('a.departmentId = :id')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('id', $department->getId())
            ->setParameter('type', 'storage')
            ->orderBy('a.isPrimary', 'DESC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($addresses as $address) {
            if (!$address instanceof Address) {
                continue;
            }
            $label = trim((string) ($address->getName() ?: $address->getCityLine() ?: $address->getFullAddress()));
            $out[] = [
                'id' => (string) $address->getId(),
                'name' => $label !== '' ? $label : 'Lager',
                'is_primary' => $address->isPrimary(),
            ];
        }

        return $out;
    }

    private function syncEventCalendarPeriod(Department $department, \DateTime $start, \DateTime $end): void
    {
        $period = $this->entityManager->getRepository(DepartmentCalendarPeriod::class)->findOneBy([
            'departmentId' => $department->getId(),
            'label' => DepartmentCalendarPeriod::LABEL_GROSSANLASS,
        ]);
        if (!$period instanceof DepartmentCalendarPeriod) {
            return;
        }
        $period->setStartDate((clone $start)->setTime(0, 0, 0));
        $period->setEndDate((clone $end)->setTime(0, 0, 0));
    }

    private function requireConfig(Department $department): DepartmentGrossanlassConfig
    {
        $config = $department->getGrossanlassConfig();
        if (!$config instanceof DepartmentGrossanlassConfig) {
            throw new \InvalidArgumentException('Grossanlass-Konfiguration fehlt');
        }

        return $config;
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Planung');
        }
    }

    private function assertMember(Department $department, User $user): void
    {
        $membership = $this->entityManager->getRepository(\App\Entity\Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        if ($membership === null) {
            throw new \RuntimeException('Kein Zugriff auf diese Abteilung');
        }
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
