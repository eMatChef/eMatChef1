<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\Activity;
use App\Entity\ActivityGrossanlassConfig;
use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassUserCard;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Clock\BusinessClock;
use App\Service\Demo\Legacy\LegacyDemoRename;
use App\Service\Grossanlass\GrossanlassDriveCategories;
use App\Service\Grossanlass\GrossanlassPackService;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use App\Util\GrossanlassIdGenerator;
use App\Util\DemoAccounts;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Dev-Demo: Grossanlass-Department mit PFF-inspirierter Struktur, Rollen-Usern und Fachdaten.
 */
final class DemoGrossanlassSeedService
{
    public const DEPARTMENT_NAME = 'Demo Grossanlass';

    /** @deprecated Legacy — wird durch {@see self::NAME_INFRASTRUKTUR} ersetzt */
    public const RESSORT_NAME = 'Demo-Ressort';

    public const NAME_INFRASTRUKTUR = 'Infrastruktur';
    public const NAME_LOGISTIK = 'Material & Logistik';
    public const NAME_BAUTEN = 'Bauten';

    /** @deprecated Legacy-Name vor Umbenennung zu {@see self::NAME_BAUTEN} */
    private const LEGACY_NAME_BAUTEN = 'BABP';

    public const NAME_SANITAER = 'Wasser & Sanitär';

    private const PLACE_BAUTEN = 'Demo · Bauten Lagerplatz';
    private const PLACE_SANITAER = 'Demo · Sanitär Lagerplatz';

    /** @deprecated Legacy-Platzname vor Umbenennung */
    private const LEGACY_PLACE_BAUTEN = 'Demo · BABP Lagerplatz';

    private const SEED_TAG_PICKUP = '[demo:v1:babp-pickup]';
    private const SEED_TAG_TRIP = '[demo:v1:babp-trip]';
    private const SEED_TAG_PENDING = '[demo:v1:babp-pending]';
    private const SEED_TAG_SANITAER = '[demo:v1:sanitaer]';
    private const SEED_TAG_PAST = '[demo:v1:timeline-past]';
    private const SEED_TAG_RUNNING = '[demo:v1:timeline-running]';
    private const SEED_TAG_SOON = '[demo:v1:timeline-soon]';
    private const SEED_TAG_TEARDOWN = '[demo:v1:timeline-teardown]';

    /**
     * Zentraler Seed-Anker: Anlassbeginn (E) liegt so viele Tage nach dem Seed-Tag.
     * Alle Demo-Termine werden relativ zu E erzeugt; der Demo-Ausgangspunkt der BusinessClock
     * ist E - {@see \App\Service\Grossanlass\GrossanlassClockOriginResolver::DAYS_BEFORE_EVENT} Tage 09:00 (Aufbauphase).
     */
    private const EVENT_START_DAYS_AFTER_SEED = 7;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
        private WorkshopSparePartsCategoryBootstrapService $workshopSparePartsCategoryBootstrap,
        private GrossanlassPackService $packs,
        private BusinessClock $clock,
    ) {
    }

    public function ensureDepartment(Organisation $organisation, User $createdBy, string $name = self::DEPARTMENT_NAME): Department
    {
        $existing = $this->entityManager->getRepository(Department::class)->findOneBy([
            'organisationId' => $organisation->getId(),
            'name' => $name,
        ]);
        if ($existing instanceof Department && !$existing->isDemoMode()) {
            // Gleichnamiges Department ohne Demo-Markierung gehört nicht dem Seed: nicht übernehmen, nicht markieren.
            throw new \RuntimeException(sprintf(
                'Department «%s» existiert, ist aber nicht als Demo (demo_mode) markiert. Abbruch ohne Änderung.',
                $name,
            ));
        }
        if ($existing instanceof Department && $existing->isGrossanlass()) {
            $this->ensureDemoClock($existing);

            return $existing;
        }

        $this->assertNameMayBeCreated($name);

        // Seed-Anker = echter Seed-Tag; die Fachzeit der Demo startet danach in der Aufbauphase.
        $start = (new \DateTime('today'))->modify('+' . self::EVENT_START_DAYS_AFTER_SEED . ' days');
        $end = (clone $start)->modify('+14 days')->setTime(23, 59, 59);

        $department = new Department();
        $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
        $department->setName($name);
        $department->setOrganisation($organisation);
        $department->setIsGrossanlass(true);
        $department->setDemoMode(true);
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
        $this->ensureDemoClock($department);
        $this->entityManager->flush();

        $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);
        $this->workshopSparePartsCategoryBootstrap->ensure($department);

        return $department;
    }

    /**
     * Legacy-Namen sind ausgemustert («old-»-Umbenennung): dieser Dienst legt unter ihnen nichts neu an.
     * Neue Demo-Departments entstehen über app:demo:sync.
     */
    private function assertNameMayBeCreated(string $name): void
    {
        if (LegacyDemoRename::isRetiredName($name)) {
            throw new \RuntimeException(sprintf('Der Name «%s» gehört zu den ausgemusterten Legacy-Demo-Strukturen; es wird nichts neu angelegt. Neue Demo-Daten: app:demo:sync.', $name));
        }
    }

    /** Vorabprüfung für Aufrufer mit eigenen Schreibzugriffen: wäre ensureDepartment($org) erlaubt? */
    public function assertLegacyDepartmentAvailable(Organisation $organisation, string $name = self::DEPARTMENT_NAME): void
    {
        $existing = $this->entityManager->getRepository(Department::class)->findOneBy(['organisationId' => $organisation->getId(), 'name' => $name]);
        if (!$existing instanceof Department) {
            $this->assertNameMayBeCreated($name);
        }
    }

    /**
     * PFF-inspiriertes Demo-Szenario: Infrastruktur-Baum, Zuordnungen, Einsätze.
     *
     * @param array<string, User> $gaUsers keyed by email (ga-mw@, ga-bereich@, ga-helfer@, …)
     */
    public function ensureDemoScenario(Department $department, array $gaUsers, User $actor): void
    {
        if (!$department->isGrossanlass()) {
            return;
        }

        $mw = $gaUsers[DemoAccounts::email('ga-mw')] ?? null;
        $bereich = $gaUsers[DemoAccounts::email('ga-bereich')] ?? null;
        $helfer = $gaUsers[DemoAccounts::email('ga-helfer')] ?? null;
        $ok = $gaUsers[DemoAccounts::email('ga-ok')] ?? null;

        if (!$mw instanceof User || !$bereich instanceof User || !$helfer instanceof User) {
            return;
        }

        $infrastruktur = $this->ensureGroup(
            $department,
            self::NAME_INFRASTRUKTUR,
            null,
            Group::GROSSANLASS_KIND_RESSORT,
            10,
        );
        $logistik = $this->ensureGroup(
            $department,
            self::NAME_LOGISTIK,
            $infrastruktur,
            Group::GROSSANLASS_KIND_RESSORT,
            20,
        );
        $bauten = $this->ensureGroup(
            $department,
            self::NAME_BAUTEN,
            $infrastruktur,
            Group::GROSSANLASS_KIND_TEILBEREICH,
            30,
            self::LEGACY_NAME_BAUTEN,
        );
        $sanitaer = $this->ensureGroup(
            $department,
            self::NAME_SANITAER,
            $infrastruktur,
            Group::GROSSANLASS_KIND_TEILBEREICH,
            40,
        );

        $config = $department->getGrossanlassConfig();
        if ($config instanceof DepartmentGrossanlassConfig) {
            $config->setLogisticsGroup($logistik);
        }
        $this->ensureDemoClock($department);

        $this->ensureGroupMembership($logistik, $mw, 'leader', true);
        $this->ensureGroupMembership($bauten, $bereich, 'leader', true);
        $this->ensureGroupMembership($bauten, $helfer, 'member', true);
        if ($ok instanceof User) {
            $this->ensureGroupMembership($infrastruktur, $ok, 'member', false);
        }

        $this->ensureHelperDriveCard($department, $helfer, $bauten->getName(), $mw);

        [$eventStart, $eventEnd] = $this->eventWindow($department);
        $place = $this->ensurePlace($department, self::PLACE_BAUTEN, $bauten, self::LEGACY_PLACE_BAUTEN);
        $this->ensurePlace($department, self::PLACE_SANITAER, $sanitaer);
        $tables = $this->ensureCommitment(
            $department,
            'Demo · Festtische',
            DepartmentGrossanlassCommitment::FAMILY_MATERIAL,
            40,
        );
        $transporter = $this->ensureCommitment(
            $department,
            'Demo · Transporter',
            DepartmentGrossanlassCommitment::FAMILY_VEHICLE,
            1,
        );

        $day1Start = (clone $eventStart)->modify('+1 day')->setTime(8, 0);
        $day1End = (clone $day1Start)->modify('+4 hours');
        $day2Start = (clone $eventStart)->modify('+2 days')->setTime(9, 0);
        $day2End = (clone $day2Start)->modify('+6 hours');

        $this->ensureEinsatz($department, self::SEED_TAG_PICKUP, [
            'commitment' => $tables,
            'group' => $bauten,
            'qty' => 10,
            'starts_at' => $day1Start,
            'ends_at' => $day1End,
            'status' => DepartmentGrossanlassEinsatz::STATUS_PLANNED,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_PICKUP,
            'who' => 'Demo · Festtische ' . self::SEED_TAG_PICKUP,
        ]);

        $this->ensureEinsatz($department, self::SEED_TAG_TRIP, [
            'commitment' => $transporter,
            'group' => $bauten,
            'qty' => 1,
            'starts_at' => $day2Start,
            'ends_at' => $day2End,
            'status' => DepartmentGrossanlassEinsatz::STATUS_PLANNED,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_TRIP,
            'chauffeur_user_id' => $helfer->getId(),
            'destination_place' => $place,
            'who' => 'Demo · Transporter ' . self::SEED_TAG_TRIP,
        ]);

        $this->ensureEinsatz($department, self::SEED_TAG_PENDING, [
            'commitment' => $tables,
            'group' => $bauten,
            'qty' => 4,
            'starts_at' => (clone $day1Start)->modify('+1 day'),
            'ends_at' => (clone $day1End)->modify('+1 day'),
            'status' => DepartmentGrossanlassEinsatz::STATUS_PENDING,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_PICKUP,
            'who' => 'Demo · Gerüst pending ' . self::SEED_TAG_PENDING,
        ]);

        $this->ensureEinsatz($department, self::SEED_TAG_SANITAER, [
            'commitment' => $tables,
            'group' => $sanitaer,
            'qty' => 2,
            'starts_at' => $day1Start,
            'ends_at' => $day1End,
            'status' => DepartmentGrossanlassEinsatz::STATUS_PLANNED,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_PICKUP,
            'who' => 'Demo · Sanitär ' . self::SEED_TAG_SANITAER,
        ]);

        $this->ensureTimeline($department, $eventStart, $tables, $transporter, $bauten, $sanitaer, $helfer, $place);

        $this->entityManager->flush();
    }

    /**
     * Demo-Modus markieren und – nur falls noch keine Demo-Zeit gesetzt – auf den Ausgangspunkt stellen.
     * Eine vom User verstellte Demo-Zeit wird beim erneuten Seeden nicht überschrieben.
     */
    private function ensureDemoClock(Department $department): void
    {
        $department->setDemoMode(true);
        if ($department->getDemoClockOffsetSeconds() === null) {
            $this->clock->reset($department);
        }
    }

    /**
     * Zusammenhängender Zeitstrahl relativ zum Anlassbeginn E, damit man ohne Re-Seed durchreisen kann:
     * Vergangenheit (E-7d/-6d), laufend am Demo-Ausgangspunkt (E-5d), kommende Stunden (E-5d 14:00),
     * kommende Tage (E+1d/+2d, bestehend), Rückbau/Nachbearbeitung (E+12d).
     */
    private function ensureTimeline(
        Department $department,
        \DateTime $eventStart,
        DepartmentGrossanlassCommitment $tables,
        DepartmentGrossanlassCommitment $transporter,
        Group $bauten,
        Group $sanitaer,
        User $helfer,
        DepartmentGrossanlassPlace $place,
    ): void {
        $at = static fn (int $days, int $hour): \DateTime => (clone $eventStart)->modify(sprintf('%+d days', $days))->setTime($hour, 0);

        $this->ensureEinsatz($department, self::SEED_TAG_PAST, [
            'commitment' => $tables,
            'group' => $bauten,
            'qty' => 6,
            'starts_at' => $at(-7, 8),
            'ends_at' => $at(-7, 12),
            'status' => DepartmentGrossanlassEinsatz::STATUS_RETURNED,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_PICKUP,
            'who' => 'Demo · Vorabholung erledigt ' . self::SEED_TAG_PAST,
        ]);
        $this->ensureEinsatz($department, self::SEED_TAG_RUNNING, [
            'commitment' => $tables,
            'group' => $bauten,
            'qty' => 8,
            'starts_at' => $at(-5, 8),
            'ends_at' => $at(-5, 17),
            'status' => DepartmentGrossanlassEinsatz::STATUS_ISSUED,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_PICKUP,
            'who' => 'Demo · Aufbau läuft ' . self::SEED_TAG_RUNNING,
        ]);
        $this->ensureEinsatz($department, self::SEED_TAG_SOON, [
            'commitment' => $transporter,
            'group' => $sanitaer,
            'qty' => 1,
            'starts_at' => $at(-5, 14),
            'ends_at' => $at(-5, 16),
            'status' => DepartmentGrossanlassEinsatz::STATUS_PLANNED,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_TRIP,
            'chauffeur_user_id' => $helfer->getId(),
            'destination_place' => $place,
            'who' => 'Demo · Fahrt heute Nachmittag ' . self::SEED_TAG_SOON,
        ]);
        $this->ensureEinsatz($department, self::SEED_TAG_TEARDOWN, [
            'commitment' => $tables,
            'group' => $bauten,
            'qty' => 14,
            'starts_at' => $at(12, 8),
            'ends_at' => $at(12, 16),
            'status' => DepartmentGrossanlassEinsatz::STATUS_PLANNED,
            'delivery' => DepartmentGrossanlassEinsatz::DELIVERY_PICKUP,
            'who' => 'Demo · Rückbau ' . self::SEED_TAG_TEARDOWN,
        ]);
    }

    /**
     * @deprecated Nutze {@see ensureDemoScenario}
     */
    public function ensureDemoRessort(Department $department, User $leader, User $member): Group
    {
        $this->ensureDemoScenario($department, [
            DemoAccounts::email('ga-mw') => $leader,
            DemoAccounts::email('ga-bereich') => $leader,
            DemoAccounts::email('ga-helfer') => $member,
        ], $leader);

        $bauten = $this->entityManager->getRepository(Group::class)->findOneBy([
            'departmentId' => $department->getId(),
            'name' => self::NAME_BAUTEN,
        ]);
        if (!$bauten instanceof Group) {
            throw new \RuntimeException('Demo-Ressort Bauten fehlt nach Seed');
        }

        return $bauten;
    }

    private function ensureGroup(
        Department $department,
        string $name,
        ?Group $parent,
        string $kind,
        int $sortOrder,
        ?string $legacyName = null,
    ): Group {
        $criteria = [
            'departmentId' => $department->getId(),
            'name' => $name,
        ];
        if ($parent !== null) {
            $criteria['parentId'] = $parent->getId();
        }

        $group = $this->entityManager->getRepository(Group::class)->findOneBy($criteria);
        if (!$group instanceof Group && $legacyName !== null && $legacyName !== $name) {
            $legacyCriteria = [
                'departmentId' => $department->getId(),
                'name' => $legacyName,
            ];
            if ($parent !== null) {
                $legacyCriteria['parentId'] = $parent->getId();
            }
            $legacy = $this->entityManager->getRepository(Group::class)->findOneBy($legacyCriteria);
            if ($legacy instanceof Group) {
                $legacy->setName($name);
                $group = $legacy;
            }
        }
        if (!$group instanceof Group) {
            $group = new Group();
            $group->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::GROUP, Group::class));
            $group->setDepartment($department);
            $group->setName($name);
            $group->setGrossanlassKind($kind);
            $group->setSortOrder($sortOrder);
            if ($parent !== null) {
                $group->setParent($parent);
            }
            $this->entityManager->persist($group);
        } else {
            $group->setGrossanlassKind($kind);
            $group->setSortOrder($sortOrder);
        }

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
            if ($isPrimary) {
                $existing->setIsPrimary(true);
            }

            return;
        }

        $row = new GroupMembership();
        $row->setUser($user);
        $row->setGroup($group);
        $row->setRole($role);
        $row->setIsPrimary($isPrimary);
        $this->entityManager->persist($row);
    }

    private function ensureHelperDriveCard(Department $department, User $helfer, string $ressortName, User $verifiedBy): void
    {
        $card = $this->entityManager->getRepository(DepartmentGrossanlassUserCard::class)->find([
            'departmentId' => $department->getId(),
            'userId' => $helfer->getId(),
        ]);
        if (!$card instanceof DepartmentGrossanlassUserCard) {
            $card = new DepartmentGrossanlassUserCard();
            $card->setDepartment($department);
            $card->setUser($helfer);
            $card->setPublicCode(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::USER_CARD,
                DepartmentGrossanlassUserCard::class,
                'publicCode',
            ));
            $this->entityManager->persist($card);
        }

        $card->setDriveClasses(GrossanlassDriveCategories::sanitize(['B']));
        $card->setDriveProofKind(GrossanlassDriveCategories::PROOF_IN_PERSON);
        $card->setDriveVerified(true);
        $card->setDriveVerifiedAt(new \DateTime());
        $card->setDriveVerifiedById($verifiedBy->getId());
        $card->setMayDrive(true);
    }

    private function ensureCommitment(
        Department $department,
        string $name,
        string $family,
        int $quantity,
    ): DepartmentGrossanlassCommitment {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findOneBy([
            'departmentId' => $department->getId(),
            'name' => $name,
        ]);
        if (!$row instanceof DepartmentGrossanlassCommitment) {
            $row = new DepartmentGrossanlassCommitment();
            $row->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::COMMITMENT,
                DepartmentGrossanlassCommitment::class,
            ));
            $row->setDepartment($department);
            $row->setName($name);
            $row->setSource('Demo-Seed');
            $row->setFamily($family);
            $row->setOrigin(DepartmentGrossanlassCommitment::ORIGIN_LOAN);
            $row->setReturnRequired(true);
            $row->setOwnerKind(DepartmentGrossanlassCommitment::OWNER_EXTERNAL);
            $row->setReleased(true);
            $this->entityManager->persist($row);
        }
        $row->setQuantity($quantity);

        return $row;
    }

    private function ensurePlace(
        Department $department,
        string $name,
        Group $group,
        ?string $legacyName = null,
    ): DepartmentGrossanlassPlace {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findOneBy([
            'departmentId' => $department->getId(),
            'name' => $name,
        ]);
        if (!$row instanceof DepartmentGrossanlassPlace && $legacyName !== null && $legacyName !== $name) {
            $row = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findOneBy([
                'departmentId' => $department->getId(),
                'name' => $legacyName,
            ]);
            if ($row instanceof DepartmentGrossanlassPlace) {
                $row->setName($name);
            }
        }
        if (!$row instanceof DepartmentGrossanlassPlace) {
            $row = new DepartmentGrossanlassPlace();
            $row->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::PLACE,
                DepartmentGrossanlassPlace::class,
            ));
            $row->setDepartment($department);
            $row->setName($name);
            $row->setPublicCode(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::PLACE_PUBLIC,
                DepartmentGrossanlassPlace::class,
                'publicCode',
            ));
            $row->setKind('bauprojekt');
            $this->entityManager->persist($row);
        }
        $row->setGroupId($group->getId());

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureEinsatz(Department $department, string $seedTag, array $data): DepartmentGrossanlassEinsatz
    {
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->createQueryBuilder('e')
            ->where('e.departmentId = :dept')
            ->andWhere('e.who LIKE :tag')
            ->setParameter('dept', $department->getId())
            ->setParameter('tag', '%' . $seedTag . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$existing instanceof DepartmentGrossanlassEinsatz) {
            $existing = new DepartmentGrossanlassEinsatz();
            $existing->setId(GrossanlassIdGenerator::unique(
                $this->entityManager,
                GrossanlassIdGenerator::EINSATZ,
                DepartmentGrossanlassEinsatz::class,
            ));
            $existing->setDepartment($department);
            $this->entityManager->persist($existing);
        }

        /** @var DepartmentGrossanlassCommitment $commitment */
        $commitment = $data['commitment'];
        /** @var Group $group */
        $group = $data['group'];
        /** @var \DateTime $startsAt */
        $startsAt = $data['starts_at'];
        /** @var \DateTime $endsAt */
        $endsAt = $data['ends_at'];

        $existing->setCommitment($commitment);
        $existing->setGroup($group);
        $existing->setQty((int) ($data['qty'] ?? 1));
        $existing->setStartsAt($startsAt);
        $existing->setEndsAt($endsAt);
        $existing->setStatus((string) ($data['status'] ?? DepartmentGrossanlassEinsatz::STATUS_PLANNED));
        $existing->setDelivery((string) ($data['delivery'] ?? DepartmentGrossanlassEinsatz::DELIVERY_PICKUP));
        $existing->setWho((string) ($data['who'] ?? 'Demo'));
        $existing->setKind(DepartmentGrossanlassEinsatz::KIND_EINSATZ);
        $existing->setPlace(DepartmentGrossanlassEinsatz::PLACE_ASSIGNED);
        $existing->setChauffeurUserId(isset($data['chauffeur_user_id']) ? (string) $data['chauffeur_user_id'] : null);
        if (isset($data['destination_place']) && $data['destination_place'] instanceof DepartmentGrossanlassPlace) {
            $existing->setDestinationPlaceId($data['destination_place']->getId());
        }
        $existing->setTripReleasedAt(null);
        $existing->setPacked(false);

        $this->packs->ensureDefaultPack($existing);

        return $existing;
    }

    /**
     * @return array{0: \DateTime, 1: \DateTime}
     */
    private function eventWindow(Department $department): array
    {
        $config = $department->getGrossanlassConfig();
        $start = $config?->getPlannedEventStart() ?? new \DateTime('today');
        $end = $config?->getPlannedEventEnd() ?? (clone $start)->modify('+14 days');

        return [$start, $end];
    }
}
