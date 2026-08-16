<?php

namespace App\Service\Onboarding;

use App\Entity\Activity;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityVehicle;
use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentVehicle;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\OnboardingSandboxState;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Hybrid-Sandbox für Aktivitäten-Onboarding-Touren.
 * Spec: docs/onboarding/sandboxtoolactivities/
 */
class OnboardingSandboxService
{
    public const VENUE_NAME = 'demo_venue';
    public const MATERIAL_BLACHE = 'Blache 64 (Onboarding)';
    public const MATERIAL_PACKPAPIER = 'Packpapier (Onboarding)';
    public const MATERIAL_STATIKSEIL = 'Statikseil (Onboarding)';
    public const VEHICLE_NAME = 'Demofahrzeug (Onboarding)';
    public const ACTIVITY_NAME = 'demo_activity';
    public const CAMP_NAME = 'demo_camp';

    public const KIT_STOCK_QTY = 500;

    /** @var list<string> */
    public const ACTIVITY_TOUR_IDS = [
        'activity-create',
        'activity-camp-create',
        'activity-approve',
        'issue-return',
        'issue-handoff',
        'activity-store',
        'activity-close',
        'workshop-overview',
    ];

    /** Create-Touren: kein Pre-Create — User legt Demo im Wizard an. */
    public const CREATE_TOUR_IDS = [
        'activity-create',
        'activity-camp-create',
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public static function isCreateTour(?string $forTourId): bool
    {
        return $forTourId !== null && in_array($forTourId, self::CREATE_TOUR_IDS, true);
    }

    /**
     * @return array{
     *   activityId: ?string,
     *   campId: ?string,
     *   venueId: ?string,
     *   materialIds: array{blache: ?string, packpapier: ?string, statikseil: ?string},
     *   vehicleId: ?string,
     *   statuses: array{activity: ?string, camp: ?string}
     * }
     */
    public function ensure(
        Department $department,
        User $user,
        ?string $forTourId = null,
        bool $reset = false,
    ): array {
        $venue = $this->ensureVenue($department);
        $blache = $this->ensureMaterial($department, self::MATERIAL_BLACHE, consumable: false, packUnit: null);
        $packpapier = $this->ensureMaterial($department, self::MATERIAL_PACKPAPIER, consumable: true, packUnit: 'm');
        $statikseil = $this->ensureMaterial($department, self::MATERIAL_STATIKSEIL, consumable: false, packUnit: null);
        $vehicle = $this->ensureVehicle($department);
        $materials = [$blache, $packpapier, $statikseil];

        $state = $this->findOrCreateState($department, $user);

        if ($reset) {
            $this->purgeUserSandboxCases($state, $venue);
        }

        // Create-Touren: nur Kit + State; Aktivität/Camp entstehen im Wizard
        if (self::isCreateTour($forTourId)) {
            if (!$state->getVenueId()) {
                $state->setVenue($venue);
            }
            $state->setLastForTour($forTourId);
            $state->touch();
            $this->em->flush();

            return $this->buildEnsureResponse(
                $state->getActivityId() ? $this->em->getRepository(Activity::class)->find($state->getActivityId()) : null,
                $state->getCampId() ? $this->em->getRepository(Activity::class)->find($state->getCampId()) : null,
                $this->resolveVenue($state, $venue),
                $blache,
                $packpapier,
                $statikseil,
                $vehicle,
            );
        }

        $activity = $this->ensureUserActivity($department, $user, $state, $materials);
        $camp = $this->ensureUserCamp($department, $user, $state, $this->resolveVenue($state, $venue), $vehicle, $materials);

        $this->resetPackState($activity);
        $this->resetPackState($camp);
        $this->applyTourStatuses($activity, $camp, $forTourId);
        $this->seedPackPipelineForTour($activity, $forTourId);
        $this->seedPackPipelineForTour($camp, $forTourId);

        $state->setActivity($activity);
        $state->setCamp($camp);
        if (!$state->getVenueId()) {
            $state->setVenue($this->resolveVenue($state, $venue));
        }
        $state->setLastForTour($forTourId);
        $state->touch();

        $this->em->flush();

        return $this->buildEnsureResponse($activity, $camp, $this->resolveVenue($state, $venue), $blache, $packpapier, $statikseil, $vehicle);
    }

    /**
     * Nach Tour-Create: Demo-Aktivität/Camp in der Registry verankern.
     */
    public function registerActivity(Activity $activity, User $user): void
    {
        if (!$activity->isOnboardingSandbox()) {
            return;
        }
        $department = $activity->getDepartment();
        $state = $this->findOrCreateState($department, $user);

        if ($activity->getType() === 'camp' || $activity->getType() === 'event') {
            $state->setCamp($activity);
        } else {
            $state->setActivity($activity);
        }

        $venue = $activity->getVenueAddress();
        if ($venue instanceof Address) {
            if (!$venue->isOnboardingSandbox()) {
                $venue->setOnboardingSandbox(true);
            }
            $state->setVenue($venue);
        }

        $state->touch();
        $this->em->flush();
    }

    /**
     * Nach Tour-Create eines Eventstandorts: Registry + Flag.
     */
    public function registerVenue(Address $address, User $user): void
    {
        if (!$address->isOnboardingSandbox()) {
            return;
        }
        $departmentId = $address->getDepartmentId();
        if ($departmentId === null || $departmentId === '') {
            return;
        }
        $department = $this->em->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return;
        }

        $state = $this->findOrCreateState($department, $user);
        $state->setVenue($address);
        $state->touch();
        $this->em->flush();
    }

    /**
     * Soft-Delete User-Übungsfälle (nicht Shared-Kit).
     */
    public function purgeUserSandboxCases(OnboardingSandboxState $state, ?Address $kitVenue = null): void
    {
        $kitVenueId = $kitVenue?->getId();

        foreach ([$state->getActivityId(), $state->getCampId()] as $activityId) {
            if (!$activityId) {
                continue;
            }
            $activity = $this->em->getRepository(Activity::class)->find($activityId);
            if ($activity instanceof Activity && $activity->isOnboardingSandbox() && !$activity->isDeleted()) {
                $this->resetPackState($activity);
                $activity->setDeletedAt(new \DateTime());
                $activity->setStatus(Activity::STATUS_CANCELLED);
            }
        }

        $venueId = $state->getVenueId();
        if ($venueId && $venueId !== $kitVenueId) {
            $venue = $this->em->getRepository(Address::class)->find($venueId);
            if ($venue instanceof Address && $venue->isOnboardingSandbox() && !$venue->isDeleted()) {
                // Kit-Fallback nicht löschen (Name demo_venue)
                if ($venue->getName() !== self::VENUE_NAME) {
                    $venue->setDeletedAt(new \DateTime());
                }
            }
        }

        $state->setActivity(null);
        $state->setCamp(null);
        $state->setVenue(null);
        $state->touch();
    }

    private function resolveVenue(OnboardingSandboxState $state, Address $kitVenue): Address
    {
        if ($state->getVenueId()) {
            $v = $this->em->getRepository(Address::class)->find($state->getVenueId());
            if ($v instanceof Address && !$v->isDeleted()) {
                return $v;
            }
        }

        return $kitVenue;
    }

    /**
     * @return array{
     *   activityId: ?string,
     *   campId: ?string,
     *   venueId: ?string,
     *   materialIds: array{blache: ?string, packpapier: ?string, statikseil: ?string},
     *   vehicleId: ?string,
     *   statuses: array{activity: ?string, camp: ?string}
     * }
     */
    private function buildEnsureResponse(
        ?Activity $activity,
        ?Activity $camp,
        ?Address $venue,
        MaterialItem $blache,
        MaterialItem $packpapier,
        MaterialItem $statikseil,
        DepartmentVehicle $vehicle,
    ): array {
        $activityOk = $activity instanceof Activity && !$activity->isDeleted() ? $activity : null;
        $campOk = $camp instanceof Activity && !$camp->isDeleted() ? $camp : null;

        return [
            'activityId' => $activityOk?->getId(),
            'campId' => $campOk?->getId(),
            'venueId' => $venue?->getId(),
            'materialIds' => [
                'blache' => $blache->getId(),
                'packpapier' => $packpapier->getId(),
                'statikseil' => $statikseil->getId(),
            ],
            'vehicleId' => $vehicle->getId(),
            'statuses' => [
                'activity' => $activityOk?->getStatus(),
                'camp' => $campOk?->getStatus(),
            ],
        ];
    }

    private function findOrCreateState(Department $department, User $user): OnboardingSandboxState
    {
        $existing = $this->em->getRepository(OnboardingSandboxState::class)->findOneBy([
            'departmentId' => $department->getId(),
            'userId' => $user->getId(),
        ]);
        if ($existing instanceof OnboardingSandboxState) {
            return $existing;
        }

        $state = new OnboardingSandboxState();
        $state->setId(IdGenerator::generateUnique($this->em, OnboardingSandboxState::class));
        $state->setDepartment($department);
        $state->setUser($user);
        $this->em->persist($state);

        return $state;
    }

    private function ensureVenue(Department $department): Address
    {
        $existing = $this->em->getRepository(Address::class)->findOneBy([
            'departmentId' => $department->getId(),
            'onboardingSandbox' => true,
            'type' => Address::TYPE_EVENT,
            'name' => self::VENUE_NAME,
        ]);
        if ($existing instanceof Address && !$existing->isDeleted()) {
            return $existing;
        }

        $venue = new Address();
        $venue->setId(IdGenerator::generateUnique($this->em, Address::class));
        $venue->setScope(Address::SCOPE_DEPARTMENT);
        $venue->setDepartment($department);
        $venue->setType(Address::TYPE_EVENT);
        $venue->setName(self::VENUE_NAME);
        $venue->setStreet('Übungsstrasse');
        $venue->setStreetNumber('1');
        $venue->setPostalCode('8000');
        $venue->setCity('Zürich');
        $venue->setCountry('Schweiz');
        $venue->setOnboardingSandbox(true);
        $this->em->persist($venue);

        return $venue;
    }

    private function ensureMaterial(
        Department $department,
        string $name,
        bool $consumable,
        ?string $packUnit,
    ): MaterialItem {
        $existing = $this->em->getRepository(MaterialItem::class)->findOneBy([
            'departmentId' => $department->getId(),
            'onboardingSandbox' => true,
            'name' => $name,
        ]);
        if ($existing instanceof MaterialItem && $existing->getDeletedAt() === null) {
            $this->ensureHighStock($existing);
            return $existing;
        }

        $material = new MaterialItem();
        $material->setId(IdGenerator::generateUnique($this->em, MaterialItem::class));
        $material->setDepartment($department);
        $material->setName($name);
        $material->setTrackingType('bulk');
        $material->setIsConsumable($consumable);
        if ($packUnit !== null) {
            $material->setPackUnit($packUnit);
        }
        $material->setOnboardingSandbox(true);
        $this->em->persist($material);

        $batch = new MaterialBatch();
        $batch->setId(IdGenerator::generate13('ba'));
        $batch->setMaterialItem($material);
        $batch->setAcquiredOn(new \DateTime('today'));
        $batch->setQty(self::KIT_STOCK_QTY);
        $batch->setIsInitial(true);
        $batch->setBatchType('initial');
        $batch->setLabel('Onboarding-Kit');
        $this->em->persist($batch);

        return $material;
    }

    private function ensureHighStock(MaterialItem $material): void
    {
        $batches = $this->em->getRepository(MaterialBatch::class)->findBy([
            'materialItemId' => $material->getId(),
            'status' => 'active',
        ]);
        $total = 0;
        foreach ($batches as $batch) {
            if ($batch instanceof MaterialBatch) {
                $total += $batch->getQty();
            }
        }
        if ($total >= self::KIT_STOCK_QTY) {
            return;
        }

        $batch = new MaterialBatch();
        $batch->setId(IdGenerator::generate13('ba'));
        $batch->setMaterialItem($material);
        $batch->setAcquiredOn(new \DateTime('today'));
        $batch->setQty(self::KIT_STOCK_QTY - $total);
        $batch->setIsInitial(false);
        $batch->setBatchType('adjustment');
        $batch->setLabel('Onboarding-Kit Nachfüllung');
        $this->em->persist($batch);
    }

    private function ensureVehicle(Department $department): DepartmentVehicle
    {
        $existing = $this->em->getRepository(DepartmentVehicle::class)->findOneBy([
            'departmentId' => $department->getId(),
            'onboardingSandbox' => true,
            'name' => self::VEHICLE_NAME,
        ]);
        if ($existing instanceof DepartmentVehicle) {
            $existing->setIsActive(true);
            return $existing;
        }

        $vehicle = new DepartmentVehicle();
        $vehicle->setId(IdGenerator::generateUnique($this->em, DepartmentVehicle::class));
        $vehicle->setDepartment($department);
        $vehicle->setName(self::VEHICLE_NAME);
        $vehicle->setPlate('ONB-DEMO');
        $vehicle->setIsActive(true);
        $vehicle->setOnboardingSandbox(true);
        $vehicle->setNotes('Onboarding-Demofahrzeug — nur in Touren sichtbar');
        $this->em->persist($vehicle);

        return $vehicle;
    }

    /**
     * @param list<MaterialItem> $materials
     */
    private function ensureUserActivity(
        Department $department,
        User $user,
        OnboardingSandboxState $state,
        array $materials,
    ): Activity {
        $activity = null;
        if ($state->getActivityId()) {
            $activity = $this->em->getRepository(Activity::class)->find($state->getActivityId());
        }
        if (!$activity instanceof Activity || $activity->isDeleted() || !$activity->isOnboardingSandbox()) {
            $activity = $this->em->getRepository(Activity::class)->findOneBy([
                'departmentId' => $department->getId(),
                'createdByUserId' => $user->getId(),
                'onboardingSandbox' => true,
                'type' => 'activity',
                'name' => self::ACTIVITY_NAME,
            ]);
        }
        if (!$activity instanceof Activity || $activity->isDeleted()) {
            $activity = new Activity();
            $activity->setId(IdGenerator::generateUnique($this->em, Activity::class));
            $activity->setDepartment($department);
            $activity->setName(self::ACTIVITY_NAME);
            $activity->setType('activity');
            $activity->setStatus(Activity::STATUS_DRAFT);
            $activity->setCreateWizardCompleted(true);
            $activity->setCreatedByUser($user);
            $activity->setResponsibleUser($user);
            $activity->setOnboardingSandbox(true);
            $activity->setNotes('demo_activity — Onboarding-Sandbox, ausserhalb der Tour ausgeblendet');
            $this->em->persist($activity);
        }

        $this->ensurePeriod($activity);
        $this->syncMaterialLines($activity, $materials);

        return $activity;
    }

    /**
     * @param list<MaterialItem> $materials
     */
    private function ensureUserCamp(
        Department $department,
        User $user,
        OnboardingSandboxState $state,
        Address $venue,
        DepartmentVehicle $vehicle,
        array $materials,
    ): Activity {
        $camp = null;
        if ($state->getCampId()) {
            $camp = $this->em->getRepository(Activity::class)->find($state->getCampId());
        }
        if (!$camp instanceof Activity || $camp->isDeleted() || !$camp->isOnboardingSandbox()) {
            $camp = $this->em->getRepository(Activity::class)->findOneBy([
                'departmentId' => $department->getId(),
                'createdByUserId' => $user->getId(),
                'onboardingSandbox' => true,
                'type' => 'camp',
                'name' => self::CAMP_NAME,
            ]);
        }
        if (!$camp instanceof Activity || $camp->isDeleted()) {
            $camp = new Activity();
            $camp->setId(IdGenerator::generateUnique($this->em, Activity::class));
            $camp->setDepartment($department);
            $camp->setName(self::CAMP_NAME);
            $camp->setType('camp');
            $camp->setStatus(Activity::STATUS_DRAFT);
            $camp->setCreateWizardCompleted(true);
            $camp->setCreatedByUser($user);
            $camp->setResponsibleUser($user);
            $camp->setOnboardingSandbox(true);
            $camp->setNotes('demo_camp — Onboarding-Sandbox, Freigabe am Demo-Lager automatisch');
            $this->em->persist($camp);
        }

        $camp->setVenueAddress($venue);
        $this->ensurePeriod($camp, days: 3);
        $this->syncMaterialLines($camp, $materials);
        $this->ensureCampVehicle($camp, $vehicle);

        return $camp;
    }

    private function ensurePeriod(Activity $activity, int $days = 1): void
    {
        $start = new \DateTime('today');
        $start->setTime(10, 0);
        $end = (clone $start)->modify(sprintf('+%d days', max(1, $days)));
        $end->setTime(18, 0);
        $activity->setUsageStart($start);
        $activity->setUsageEnd($end);
        if (method_exists($activity, 'setPlanningStart')) {
            $activity->setPlanningStart((clone $start)->modify('-1 day'));
            $activity->setPlanningEnd((clone $end)->modify('+1 day'));
        }
    }

    /**
     * @param list<MaterialItem> $materials
     */
    private function syncMaterialLines(Activity $activity, array $materials): void
    {
        $existing = $this->em->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activity->getId(),
        ]);
        $byMaterial = [];
        foreach ($existing as $item) {
            if ($item instanceof ActivityItem) {
                $byMaterial[$item->getMaterialItemId()] = $item;
            }
        }

        $qty = 2;
        foreach ($materials as $material) {
            $mid = (string) $material->getId();
            if (isset($byMaterial[$mid])) {
                $byMaterial[$mid]->setQuantity($qty);
                continue;
            }
            $line = new ActivityItem();
            $line->setId(IdGenerator::generate13('ai'));
            $line->setActivity($activity);
            $line->setMaterialItem($material);
            $line->setQuantity($qty);
            $this->em->persist($line);
        }

        $activity->setItemCount(count($materials));
    }

    private function ensureCampVehicle(Activity $camp, DepartmentVehicle $vehicle): void
    {
        $existing = $this->em->getRepository(ActivityVehicle::class)->findOneBy([
            'activityId' => $camp->getId(),
            'vehicleId' => $vehicle->getId(),
        ]);
        if ($existing instanceof ActivityVehicle) {
            return;
        }

        $av = new ActivityVehicle();
        $av->setId(IdGenerator::generate13('av'));
        $av->setActivity($camp);
        $av->setVehicle($vehicle);
        $this->em->persist($av);
    }

    private function resetPackState(Activity $activity): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement(
            'DELETE FROM activity_pack_item WHERE activity_id = :id',
            ['id' => $activity->getId()],
        );
        // Soft-reset timestamps that would block re-running pack/store tours
        $activity->setIssuedAt(null);
        $activity->setReturnedAt(null);
        $activity->setCompletedAt(null);
        $activity->setRejectionComment(null);
    }

    /**
     * Packliste für Folge-Touren (Icons Schäden/Verlust/Retour brauchen ausgegebene Mengen).
     * issue-return legt die Packliste während der Tour an — hier nicht vorbefüllen.
     *
     * @return 'issued'|'returned'|null
     */
    public static function packSeedStageForTour(?string $forTourId): ?string
    {
        return match ($forTourId) {
            'issue-handoff' => 'issued',
            'activity-store', 'activity-close', 'workshop-overview' => 'returned',
            default => null,
        };
    }

    private function seedPackPipelineForTour(Activity $activity, ?string $forTourId): void
    {
        $stage = self::packSeedStageForTour($forTourId);
        if ($stage === null || !$activity->getId()) {
            return;
        }

        $activityItems = $this->em->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activity->getId(),
        ]);
        if ($activityItems === []) {
            return;
        }

        $now = new \DateTime();
        foreach ($activityItems as $ai) {
            if (!$ai instanceof ActivityItem) {
                continue;
            }
            $material = $ai->getMaterialItem();
            if (!$material instanceof MaterialItem) {
                continue;
            }
            $qty = max(1, $ai->getQuantity());
            $pack = new ActivityPackItem();
            $pack->setId(IdGenerator::generate13('pk'));
            $pack->setActivity($activity);
            $pack->setMaterialItem($material);
            $pack->setQuantityOrdered($qty);
            $pack->setConditionOut('ok');
            $pack->setQuantityPacked($qty);
            $pack->setQuantityTransportTo($qty);
            $pack->setQuantityIssued($qty);
            $pack->setPackedAt($now);
            if ($stage === 'returned') {
                $pack->setQuantityTransportBack($qty);
                $pack->setQuantityReturned($qty);
                // Eine nasse Einheit für Retour-/Einlager-Demo (nicht Verbrauch)
                if (!$material->getIsConsumable() && $qty >= 1) {
                    $pack->setQuantityWet(1);
                    $pack->setWetHung(false);
                }
            }
            $this->em->persist($pack);
        }

        $activity->setIssuedAt($activity->getIssuedAt() ?? $now);
        if ($stage === 'returned') {
            $activity->setReturnedAt($activity->getReturnedAt() ?? $now);
        }
    }

    private function applyTourStatuses(Activity $activity, Activity $camp, ?string $forTourId): void
    {
        $now = new \DateTime();
        $statuses = self::statusesForTour($forTourId);
        $activityStatus = $statuses['activity'];
        $campStatus = $statuses['camp'];

        if ($activityStatus === Activity::STATUS_SUBMITTED || $campStatus === Activity::STATUS_SUBMITTED) {
            $activity->setSubmittedAt($now);
            $camp->setSubmittedAt($now);
        }
        if ($activityStatus === Activity::STATUS_APPROVED || $campStatus === Activity::STATUS_APPROVED
            || in_array($activityStatus, [
                Activity::STATUS_PACKED,
                Activity::STATUS_AT_EVENT,
                Activity::STATUS_RETURNED,
                Activity::STATUS_STORING,
            ], true)) {
            $activity->setApprovedAt($activity->getApprovedAt() ?? $now);
            $camp->setApprovedAt($camp->getApprovedAt() ?? $now);
            $camp->setSubmittedAt($camp->getSubmittedAt() ?? $now);
        }
        if ($activityStatus === Activity::STATUS_RETURNED || $activityStatus === Activity::STATUS_STORING) {
            $activity->setReturnedAt($now);
            $camp->setReturnedAt($now);
        }

        $activity->setStatus($activityStatus);
        $camp->setStatus($campStatus);
    }

    /**
     * @return array{activity: string, camp: string}
     */
    public static function statusesForTour(?string $forTourId): array
    {
        return match ($forTourId) {
            'activity-create', 'activity-camp-create' => [
                'activity' => Activity::STATUS_DRAFT,
                'camp' => Activity::STATUS_DRAFT,
            ],
            'activity-approve' => [
                'activity' => Activity::STATUS_SUBMITTED,
                'camp' => Activity::STATUS_SUBMITTED,
            ],
            'issue-return' => [
                'activity' => Activity::STATUS_APPROVED,
                'camp' => Activity::STATUS_APPROVED,
            ],
            'issue-handoff' => [
                // Am Event + Pack-Seed mit quantity_issued → Schaden-/Verlust-Icons sichtbar
                'activity' => Activity::STATUS_AT_EVENT,
                'camp' => Activity::STATUS_AT_EVENT,
            ],
            'activity-store' => [
                'activity' => Activity::STATUS_RETURNED,
                'camp' => Activity::STATUS_RETURNED,
            ],
            'activity-close', 'workshop-overview' => [
                'activity' => Activity::STATUS_STORING,
                'camp' => Activity::STATUS_STORING,
            ],
            default => [
                'activity' => Activity::STATUS_APPROVED,
                'camp' => Activity::STATUS_APPROVED,
            ],
        };
    }
}
