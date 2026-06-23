<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityTransportTour;
use App\Entity\ActivityTransportTourItem;
use App\Entity\DepartmentVehicle;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\ActivityTransportTourService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}/transport-tours', name: 'api_activity_transport_tours_')]
class ActivityTransportTourController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private ActivityTransportTourService $tourService,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $direction = trim((string) $request->query->get('direction', ''));
        $qb = $this->entityManager->getRepository(ActivityTransportTour::class)->createQueryBuilder('t')
            ->leftJoin('t.vehicle', 'v')
            ->addSelect('v')
            ->where('t.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC');

        if ($direction !== '') {
            if (!\in_array($direction, [ActivityTransportTour::DIRECTION_OUTBOUND, ActivityTransportTour::DIRECTION_INBOUND], true)) {
                return new JsonResponse(['error' => 'Ungültige direction'], 400);
            }
            $qb->andWhere('t.direction = :direction')->setParameter('direction', $direction);
        }

        $tours = $qb->getQuery()->getResult();

        return new JsonResponse(array_map(fn (ActivityTransportTour $t) => $this->serializeTour($t), $tours));
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
        if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $direction = trim((string) ($data['direction'] ?? ''));
        if (!\in_array($direction, [ActivityTransportTour::DIRECTION_OUTBOUND, ActivityTransportTour::DIRECTION_INBOUND], true)) {
            return new JsonResponse(['error' => 'direction muss outbound oder inbound sein'], 400);
        }

        $vehicleId = trim((string) ($data['vehicle_id'] ?? ''));
        if ($vehicleId === '') {
            return new JsonResponse(['error' => 'vehicle_id ist erforderlich'], 400);
        }

        $vehicle = $this->entityManager->getRepository(DepartmentVehicle::class)->find($vehicleId);
        if (!$vehicle || !$vehicle->getIsActive()) {
            return new JsonResponse(['error' => 'Fahrzeug nicht gefunden'], 404);
        }
        if (!$this->activityAccess->isVehicleAssignedToActivity($activityId, $vehicleId)) {
            return new JsonResponse(['error' => 'Fahrzeug ist dieser Aktivität nicht zugeordnet — bitte im Tab Fahrzeuge hinzufügen'], 400);
        }

        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = $this->tourService->suggestTourLabel($activity, $direction, $vehicle);
        }

        $existingCount = (int) $this->entityManager->getRepository(ActivityTransportTour::class)->count([
            'activityId' => $activityId,
            'direction' => $direction,
        ]);

        $tour = new ActivityTransportTour();
        $tour->setId(IdGenerator::generate13Unique($this->entityManager, ActivityTransportTour::class, 'tt'));
        $tour->setActivity($activity);
        $tour->setLabel($label);
        $tour->setVehicle($vehicle);
        $tour->setDirection($direction);
        $tour->setSortOrder((int) ($data['sort_order'] ?? $existingCount));
        if (array_key_exists('notes', $data)) {
            $tour->setNotes(trim((string) $data['notes']) ?: null);
        }
        $lendingDept = trim((string) ($data['lending_department_id'] ?? ''));
        $tour->setLendingDepartmentId($lendingDept !== '' ? $lendingDept : $vehicle->getDepartmentId());
        $tour->setCreatedByUserId($user->getId());
        $tour->setStatus(ActivityTransportTour::STATUS_PLANNED);

        $this->entityManager->persist($tour);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeTour($tour), 201);
    }

    #[Route('/arrive-all', name: 'arrive_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function arriveAll(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        if (!$activity->isPackListEditable()) {
            return new JsonResponse(['error' => 'Packliste kann in diesem Status nicht bearbeitet werden'], 422);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $direction = trim((string) ($data['direction'] ?? $request->query->get('direction', '')));
        if (!\in_array($direction, [ActivityTransportTour::DIRECTION_OUTBOUND, ActivityTransportTour::DIRECTION_INBOUND], true)) {
            return new JsonResponse(['error' => 'direction muss outbound oder inbound sein'], 400);
        }

        $result = $this->tourService->arriveAllForDirection($activity, $direction, $user);

        return new JsonResponse([
            'success' => true,
            ...$result,
        ]);
    }

    #[Route('/{tourId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $activityId, string $tourId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $tour = $this->findTour($activityId, $tourId);
        if ($tour instanceof JsonResponse) {
            return $tour;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            if ($label !== '') {
                $tour->setLabel($label);
            }
        }
        if (array_key_exists('notes', $data)) {
            $tour->setNotes(trim((string) $data['notes']) ?: null);
        }
        if (array_key_exists('sort_order', $data)) {
            $tour->setSortOrder((int) $data['sort_order']);
        }
        if (array_key_exists('status', $data)) {
            $status = trim((string) $data['status']);
            if (!$this->tourService->isValidStatus($status)) {
                return new JsonResponse(['error' => 'Ungültiger status'], 400);
            }
            if ($status === ActivityTransportTour::STATUS_ARRIVED) {
                return new JsonResponse(['error' => 'Ankunft bitte über POST …/arrive buchen'], 400);
            }
            $tour->setStatus($status);
        }

        if (array_key_exists('items', $data) && \is_array($data['items'])) {
            $this->replaceTourItems($tour, $activityId, $data['items']);
        }

        $tour->touch();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeTour($tour));
    }

    #[Route('/{tourId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $activityId, string $tourId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $tour = $this->findTour($activityId, $tourId);
        if ($tour instanceof JsonResponse) {
            return $tour;
        }

        $this->entityManager->remove($tour);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{tourId}/arrive', name: 'arrive', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function arrive(string $activityId, string $tourId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        if (!$activity->isPackListEditable()) {
            return new JsonResponse(['error' => 'Packliste kann in diesem Status nicht bearbeitet werden'], 422);
        }

        $tour = $this->findTour($activityId, $tourId);
        if ($tour instanceof JsonResponse) {
            return $tour;
        }

        $result = $this->tourService->arriveTour($tour, $activity, $user);

        return new JsonResponse([
            'success' => true,
            'tour' => $this->serializeTour($tour),
            ...$result,
        ]);
    }

    private function replaceTourItems(ActivityTransportTour $tour, string $activityId, array $rows): void
    {
        $existing = $this->entityManager->getRepository(ActivityTransportTourItem::class)->findBy([
            'tourId' => $tour->getId(),
        ]);
        foreach ($existing as $row) {
            $this->entityManager->remove($row);
        }
        $this->entityManager->flush();

        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $containerId = trim((string) ($row['pack_container_id'] ?? ''));
            $packItemId = trim((string) ($row['pack_item_id'] ?? ''));
            if ($containerId === '' && $packItemId === '') {
                continue;
            }

            if ($containerId !== '') {
                $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
                if (!$container || $container->getActivityId() !== $activityId) {
                    continue;
                }
            }
            if ($packItemId !== '') {
                $pi = $this->entityManager->getRepository(ActivityPackItem::class)->find($packItemId);
                if (!$pi || $pi->getActivityId() !== $activityId) {
                    continue;
                }
            }

            $item = new ActivityTransportTourItem();
            $item->setId(IdGenerator::generate13Unique($this->entityManager, ActivityTransportTourItem::class, 'ti'));
            $item->setTour($tour);
            $item->setPackContainerId($containerId !== '' ? $containerId : null);
            $item->setPackItemId($packItemId !== '' ? $packItemId : null);
            if (array_key_exists('quantity', $row)) {
                $item->setQuantity(max(1, (int) $row['quantity']));
            }
            $this->entityManager->persist($item);
        }
    }

    private function serializeTour(ActivityTransportTour $tour): array
    {
        $items = $this->entityManager->getRepository(ActivityTransportTourItem::class)->findBy([
            'tourId' => $tour->getId(),
        ]);
        $vehicle = $tour->getVehicle();
        $load = $this->tourService->computeLoadSummary($tour, $items);

        return [
            'id' => $tour->getId(),
            'activity_id' => $tour->getActivityId(),
            'label' => $tour->getLabel(),
            'direction' => $tour->getDirection(),
            'sort_order' => $tour->getSortOrder(),
            'notes' => $tour->getNotes(),
            'vehicle_id' => $tour->getVehicleId(),
            'vehicle_name' => $vehicle->getName(),
            'vehicle_plate' => $vehicle->getPlate(),
            'lending_department_id' => $tour->getLendingDepartmentId(),
            'status' => $tour->getStatus(),
            'items' => array_map(fn (ActivityTransportTourItem $i) => [
                'id' => $i->getId(),
                'pack_container_id' => $i->getPackContainerId(),
                'pack_item_id' => $i->getPackItemId(),
                'quantity' => $i->getQuantity(),
            ], $items),
            'load_summary' => $load,
        ];
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

    private function findTour(string $activityId, string $tourId): ActivityTransportTour|JsonResponse
    {
        $tour = $this->entityManager->getRepository(ActivityTransportTour::class)->find($tourId);
        if (!$tour || $tour->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Tour nicht gefunden'], 404);
        }

        return $tour;
    }
}
