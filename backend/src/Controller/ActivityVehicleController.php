<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityVehicle;
use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentVehicle;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}/vehicles', name: 'api_activity_vehicles_')]
class ActivityVehicleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $activityId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $rows = $this->entityManager->getRepository(ActivityVehicle::class)->createQueryBuilder('av')
            ->leftJoin('av.vehicle', 'v')
            ->addSelect('v')
            ->leftJoin('v.ownerAddress', 'oa')
            ->addSelect('oa')
            ->where('av.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('av.sortOrder', 'ASC')
            ->addOrderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();

        return new JsonResponse(array_map(fn (ActivityVehicle $av) => $this->serializeAssignment($av), $rows));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserManageActivityVehicles($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $vehicleId = trim((string) ($data['vehicle_id'] ?? ''));

        if ($vehicleId !== '') {
            $vehicle = $this->entityManager->getRepository(DepartmentVehicle::class)->find($vehicleId);
            if (!$vehicle || !$vehicle->getIsActive()) {
                return new JsonResponse(['error' => 'Fahrzeug nicht gefunden'], 404);
            }
        } else {
            $vehicleData = \is_array($data['vehicle'] ?? null) ? $data['vehicle'] : $data;
            $name = trim((string) ($vehicleData['name'] ?? ''));
            if ($name === '') {
                return new JsonResponse(['error' => 'name ist erforderlich'], 400);
            }

            $department = $this->entityManager->getRepository(Department::class)->find($activity->getDepartmentId());
            if (!$department) {
                return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
            }

            $vehicle = new DepartmentVehicle();
            $vehicle->setId(IdGenerator::generate());
            $vehicle->setDepartment($department);
            $vehicle->setName($name);
            $this->applyVehicleFields($vehicle, $vehicleData, $activity->getDepartmentId());
            $this->entityManager->persist($vehicle);
        }

        $existing = $this->entityManager->getRepository(ActivityVehicle::class)->findOneBy([
            'activityId' => $activityId,
            'vehicleId' => $vehicle->getId(),
        ]);
        if ($existing) {
            return new JsonResponse($this->serializeAssignment($existing));
        }

        $count = (int) $this->entityManager->getRepository(ActivityVehicle::class)->count([
            'activityId' => $activityId,
        ]);

        $assignment = new ActivityVehicle();
        $assignment->setId(IdGenerator::generate13Unique($this->entityManager, ActivityVehicle::class, 'av'));
        $assignment->setActivity($activity);
        $assignment->setVehicle($vehicle);
        $assignment->setSortOrder((int) ($data['sort_order'] ?? $count));
        if (array_key_exists('notes', $data)) {
            $assignment->setNotes(trim((string) $data['notes']) ?: null);
        }

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeAssignment($assignment), 201);
    }

    #[Route('/{assignmentId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $activityId, string $assignmentId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserManageActivityVehicles($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $assignment = $this->findAssignment($activityId, $assignmentId);
        if ($assignment instanceof JsonResponse) {
            return $assignment;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $vehicle = $assignment->getVehicle();

        if (array_key_exists('notes', $data)) {
            $assignment->setNotes(trim((string) $data['notes']) ?: null);
        }
        if (array_key_exists('sort_order', $data)) {
            $assignment->setSortOrder((int) $data['sort_order']);
        }

        $vehicleData = \is_array($data['vehicle'] ?? null) ? $data['vehicle'] : null;
        if ($vehicleData !== null) {
            if (array_key_exists('name', $data['vehicle'])) {
                $name = trim((string) $data['vehicle']['name']);
                if ($name === '') {
                    return new JsonResponse(['error' => 'name darf nicht leer sein'], 400);
                }
                $vehicle->setName($name);
            }
            $this->applyVehicleFields($vehicle, $vehicleData, $activity->getDepartmentId());
            $vehicle->touch();
        }

        $assignment->touch();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeAssignment($assignment));
    }

    #[Route('/{assignmentId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $activityId, string $assignmentId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$this->activityAccess->canUserManageActivityVehicles($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $assignment = $this->findAssignment($activityId, $assignmentId);
        if ($assignment instanceof JsonResponse) {
            return $assignment;
        }

        $this->entityManager->remove($assignment);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    private function applyVehicleFields(DepartmentVehicle $vehicle, array $data, string $departmentId): void
    {
        if (array_key_exists('plate', $data)) {
            $vehicle->setPlate(trim((string) $data['plate']) ?: null);
        }
        foreach (['length_m', 'width_m', 'height_m', 'max_payload_kg', 'max_volume_m3'] as $field) {
            if (\array_key_exists($field, $data)) {
                $raw = $data[$field];
                $setter = match ($field) {
                    'length_m' => 'setLengthM',
                    'width_m' => 'setWidthM',
                    'height_m' => 'setHeightM',
                    'max_payload_kg' => 'setMaxPayloadKg',
                    'max_volume_m3' => 'setMaxVolumeM3',
                };
                $vehicle->$setter($raw === null || $raw === '' ? null : (string) $raw);
            }
        }
        if (array_key_exists('is_active', $data)) {
            $vehicle->setIsActive((bool) $data['is_active']);
        }
        if (array_key_exists('notes', $data)) {
            $vehicle->setNotes(trim((string) $data['notes']) ?: null);
        }
        if (array_key_exists('owner_address_id', $data)) {
            $ownerId = trim((string) $data['owner_address_id']);
            if ($ownerId === '') {
                $vehicle->setOwnerAddress(null);
            } else {
                $address = $this->entityManager->getRepository(Address::class)->find($ownerId);
                if (!$address || $address->getDepartmentId() !== $departmentId) {
                    return;
                }
                $vehicle->setOwnerAddress($address);
            }
        }
    }

    private function serializeAssignment(ActivityVehicle $assignment): array
    {
        $vehicle = $assignment->getVehicle();
        $owner = $vehicle->getOwnerAddress();

        return [
            'id' => $assignment->getId(),
            'activity_id' => $assignment->getActivityId(),
            'vehicle_id' => $assignment->getVehicleId(),
            'sort_order' => $assignment->getSortOrder(),
            'notes' => $assignment->getNotes(),
            'vehicle' => [
                'id' => $vehicle->getId(),
                'department_id' => $vehicle->getDepartmentId(),
                'name' => $vehicle->getName(),
                'plate' => $vehicle->getPlate(),
                'length_m' => $vehicle->getLengthM() !== null ? (float) $vehicle->getLengthM() : null,
                'width_m' => $vehicle->getWidthM() !== null ? (float) $vehicle->getWidthM() : null,
                'height_m' => $vehicle->getHeightM() !== null ? (float) $vehicle->getHeightM() : null,
                'max_payload_kg' => $vehicle->getMaxPayloadKg() !== null ? (float) $vehicle->getMaxPayloadKg() : null,
                'max_volume_m3' => $vehicle->getMaxVolumeM3() !== null ? (float) $vehicle->getMaxVolumeM3() : null,
                'is_active' => $vehicle->getIsActive(),
                'notes' => $vehicle->getNotes(),
                'owner_address_id' => $vehicle->getOwnerAddressId(),
                'owner_label' => $this->ownerLabel($owner),
                'owner_contact' => $owner ? [
                    'company' => $owner->getCompany(),
                    'contact_full_name' => $owner->getContactFullName() ?: null,
                    'phone' => $owner->getPhone(),
                    'email' => $owner->getEmail(),
                ] : null,
            ],
        ];
    }

    private function ownerLabel(?Address $owner): ?string
    {
        if (!$owner) {
            return null;
        }
        $company = trim((string) ($owner->getCompany() ?? ''));
        $contact = trim($owner->getContactFullName());
        if ($company !== '' && $contact !== '') {
            return $company . ' · ' . $contact;
        }
        if ($company !== '') {
            return $company;
        }
        if ($contact !== '') {
            return $contact;
        }

        return trim((string) ($owner->getName() ?? '')) ?: null;
    }

    private function findActivityWithAccess(string $activityId): Activity|JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($activityId);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivitaet nicht gefunden'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserViewActivity($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return $activity;
    }

    private function findAssignment(string $activityId, string $assignmentId): ActivityVehicle|JsonResponse
    {
        $assignment = $this->entityManager->getRepository(ActivityVehicle::class)->find($assignmentId);
        if (!$assignment || $assignment->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Zuordnung nicht gefunden'], 404);
        }

        return $assignment;
    }
}
