<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityVehicle;
use App\Entity\Department;
use App\Entity\DepartmentVehicle;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/vehicles', name: 'api_department_vehicles_')]
class DepartmentVehicleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $departmentIds = $this->resolveDepartmentIds($departmentId, $request);
        if ($departmentIds instanceof JsonResponse) {
            return $departmentIds;
        }

        $vehicles = $this->entityManager->getRepository(DepartmentVehicle::class)->createQueryBuilder('v')
            ->leftJoin('v.ownerAddress', 'oa')
            ->addSelect('oa')
            ->where('v.departmentId IN (:deptIds)')
            ->setParameter('deptIds', $departmentIds)
            ->andWhere('v.isActive = true')
            ->orderBy('v.name', 'ASC');

        $search = trim((string) $request->query->get('search', ''));
        if ($search !== '') {
            $vehicles->andWhere('LOWER(v.name) LIKE :search OR LOWER(v.plate) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        $result = $vehicles->getQuery()->getResult();

        return new JsonResponse(array_map(fn (DepartmentVehicle $v) => $this->serializeVehicle($v), $result));
    }

    #[Route('/recent', name: 'recent', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function recent(string $departmentId, Request $request): JsonResponse
    {
        $departmentIds = $this->resolveDepartmentIds($departmentId, $request);
        if ($departmentIds instanceof JsonResponse) {
            return $departmentIds;
        }

        $limit = min(20, max(1, (int) $request->query->get('limit', 5)));

        $rows = $this->entityManager->createQueryBuilder()
            ->select('av.vehicleId AS vehicleId', 'MAX(av.updatedAt) AS HIDDEN lastUsed')
            ->from(ActivityVehicle::class, 'av')
            ->join('av.activity', 'a')
            ->where('a.departmentId IN (:deptIds)')
            ->groupBy('av.vehicleId')
            ->orderBy('lastUsed', 'DESC')
            ->setParameter('deptIds', $departmentIds)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $vehicleIds = array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['vehicleId'] ?? ''),
            $rows,
        )));

        if ($vehicleIds === []) {
            return new JsonResponse([]);
        }

        $fetched = $this->entityManager->getRepository(DepartmentVehicle::class)->createQueryBuilder('v')
            ->leftJoin('v.ownerAddress', 'oa')
            ->addSelect('oa')
            ->where('v.id IN (:ids)')
            ->andWhere('v.isActive = true')
            ->setParameter('ids', $vehicleIds)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($fetched as $vehicle) {
            if ($vehicle instanceof DepartmentVehicle) {
                $byId[$vehicle->getId()] = $vehicle;
            }
        }

        $vehicles = [];
        foreach ($vehicleIds as $vehicleId) {
            if (isset($byId[$vehicleId])) {
                $vehicles[] = $byId[$vehicleId];
            }
        }

        return new JsonResponse(array_map(fn (DepartmentVehicle $v) => $this->serializeVehicle($v), $vehicles));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertCanManageFleet($departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name ist erforderlich'], 400);
        }

        $vehicle = new DepartmentVehicle();
        $vehicle->setId(IdGenerator::generate());
        $vehicle->setDepartment($department);
        $vehicle->setName($name);
        $this->applyVehicleFields($vehicle, $data, $departmentId);

        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeVehicle($vehicle), 201);
    }

    #[Route('/{vehicleId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $vehicleId, Request $request): JsonResponse
    {
        $deny = $this->assertCanManageFleet($departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $vehicle = $this->entityManager->getRepository(DepartmentVehicle::class)->find($vehicleId);
        if (!$vehicle || $vehicle->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Fahrzeug nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return new JsonResponse(['error' => 'name darf nicht leer sein'], 400);
            }
            $vehicle->setName($name);
        }
        $this->applyVehicleFields($vehicle, $data, $departmentId);
        $vehicle->touch();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeVehicle($vehicle));
    }

    /** @return list<string>|JsonResponse */
    private function resolveDepartmentIds(string $departmentId, Request $request): array|JsonResponse
    {
        $access = $this->assertDepartmentMember($departmentId);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $ids = [$departmentId];
        $activityId = trim((string) $request->query->get('activity_id', ''));
        if ($activityId === '') {
            return $ids;
        }

        $activity = $this->entityManager->getRepository(Activity::class)->find($activityId);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$this->activityAccess->canUserViewActivity($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        foreach ($activity->getInvitedDepartments() ?? [] as $inv) {
            $partnerId = is_array($inv) ? (string) ($inv['department_id'] ?? '') : '';
            if ($partnerId !== '' && !\in_array($partnerId, $ids, true)) {
                $ids[] = $partnerId;
            }
        }

        return $ids;
    }

    private function assertDepartmentMember(string $departmentId): true|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return true;
    }

    private function assertCanManageFleet(string $departmentId): true|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        $role = $membership ? strtolower(trim((string) ($membership->getRole() ?? ''))) : null;
        if ($role === null || !$this->activityAccess->isDepartmentWideManager($role)) {
            return new JsonResponse(['error' => 'Keine Berechtigung für Fuhrpark'], 403);
        }

        return true;
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
                $address = $this->entityManager->getRepository(\App\Entity\Address::class)->find($ownerId);
                if ($address && $address->getDepartmentId() === $vehicle->getDepartmentId()) {
                    $vehicle->setOwnerAddress($address);
                }
            }
        }
    }

    private function serializeVehicle(DepartmentVehicle $vehicle): array
    {
        $owner = $vehicle->getOwnerAddress();
        $ownerLabel = null;
        if ($owner) {
            $company = trim((string) ($owner->getCompany() ?? ''));
            $contact = trim($owner->getContactFullName());
            if ($company !== '' && $contact !== '') {
                $ownerLabel = $company . ' · ' . $contact;
            } elseif ($company !== '') {
                $ownerLabel = $company;
            } elseif ($contact !== '') {
                $ownerLabel = $contact;
            }
        }

        return [
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
            'owner_label' => $ownerLabel,
        ];
    }
}
