<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}', name: 'api_activity_pack_container_')]
class ActivityPackContainerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess
    ) {}

    #[Route('/pack-containers', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $activityId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $containers = $this->entityManager->getRepository(ActivityPackContainer::class)->createQueryBuilder('c')
            ->leftJoin('c.containerBatch', 'b')
            ->addSelect('b')
            ->where('c.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('c.label', 'ASC')
            ->getQuery()
            ->getResult();

        return new JsonResponse(array_map(fn (ActivityPackContainer $c) => $this->serializeContainer($c), $containers));
    }

    #[Route('/pack-containers', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $data = json_decode($request->getContent(), true) ?? [];
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') return new JsonResponse(['error' => 'label ist erforderlich'], 400);

        $container = new ActivityPackContainer();
        $container->setId(IdGenerator::generate13Unique($this->entityManager, ActivityPackContainer::class, 'pc'));
        $container->setActivity($activity);
        $container->setLabel($label);
        $container->setStatus((string) ($data['status'] ?? 'draft'));

        if (!empty($data['container_batch_id'])) {
            $batch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['container_batch_id']);
            if ($batch && $batch->getMaterialItem()->getDepartmentId() === $activity->getDepartmentId()) {
                $container->setContainerBatch($batch);
            }
        }

        $this->entityManager->persist($container);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeContainer($container), 201);
    }

    #[Route('/pack-containers/{containerId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $activityId, string $containerId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Container nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('label', $data)) $container->setLabel(trim((string) $data['label']));
        if (array_key_exists('status', $data)) $container->setStatus((string) $data['status']);
        if (array_key_exists('container_batch_id', $data)) {
            if ($data['container_batch_id']) {
                $batch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['container_batch_id']);
                if ($batch && $batch->getMaterialItem()->getDepartmentId() === $activity->getDepartmentId()) {
                    $container->setContainerBatch($batch);
                }
            } else {
                $container->setContainerBatch(null);
            }
        }
        $container->touch();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeContainer($container));
    }

    #[Route('/pack-containers/{containerId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $activityId, string $containerId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Container nicht gefunden'], 404);
        }

        $this->entityManager->remove($container);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/pack-containers/{containerId}/items', name: 'items_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listItems(string $activityId, string $containerId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Container nicht gefunden'], 404);
        }

        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)->createQueryBuilder('i')
            ->leftJoin('i.materialItem', 'm')
            ->leftJoin('i.materialBatch', 'b')
            ->addSelect('m', 'b')
            ->where('i.packContainerId = :containerId')
            ->setParameter('containerId', $containerId)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();

        return new JsonResponse(array_map(fn (ActivityPackContainerItem $item) => $this->serializeContainerItem($item), $items));
    }

    #[Route('/pack-containers/{containerId}/items', name: 'items_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createItem(string $activityId, string $containerId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Container nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['material_item_id'])) {
            return new JsonResponse(['error' => 'material_item_id ist erforderlich'], 400);
        }
        $material = $this->entityManager->getRepository(MaterialItem::class)->find((string) $data['material_item_id']);
        if (!$material || $material->getDepartmentId() !== $activity->getDepartmentId()) {
            return new JsonResponse(['error' => 'Material nicht gefunden oder falsches Department'], 400);
        }

        $item = new ActivityPackContainerItem();
        $item->setId(IdGenerator::generate13Unique($this->entityManager, ActivityPackContainerItem::class, 'pi'));
        $item->setPackContainer($container);
        $item->setMaterialItem($material);
        $item->setQuantityPacked(max(0, (int) ($data['quantity_packed'] ?? 0)));
        $item->setQuantityIssued(max(0, (int) ($data['quantity_issued'] ?? 0)));
        $item->setQuantityReturned(max(0, (int) ($data['quantity_returned'] ?? 0)));
        $item->setConditionOut((string) ($data['condition_out'] ?? 'ok'));
        if (array_key_exists('notes', $data)) $item->setNotes($data['notes']);

        if (!empty($data['material_batch_id'])) {
            $batch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['material_batch_id']);
            if ($batch && $batch->getMaterialItemId() === $material->getId()) {
                $item->setMaterialBatch($batch);
            }
        }

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeContainerItem($item), 201);
    }

    #[Route('/pack-containers/{containerId}/items/{itemId}', name: 'items_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateItem(string $activityId, string $containerId, string $itemId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Container nicht gefunden'], 404);
        }

        $item = $this->entityManager->getRepository(ActivityPackContainerItem::class)->find($itemId);
        if (!$item || $item->getPackContainerId() !== $containerId) {
            return new JsonResponse(['error' => 'Container-Item nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('quantity_packed', $data)) $item->setQuantityPacked(max(0, (int) $data['quantity_packed']));
        if (array_key_exists('quantity_issued', $data)) $item->setQuantityIssued(max(0, (int) $data['quantity_issued']));
        if (array_key_exists('quantity_returned', $data)) $item->setQuantityReturned(max(0, (int) $data['quantity_returned']));
        if (array_key_exists('condition_out', $data)) $item->setConditionOut((string) $data['condition_out']);
        if (array_key_exists('notes', $data)) $item->setNotes($data['notes']);
        if (array_key_exists('material_batch_id', $data)) {
            if ($data['material_batch_id']) {
                $batch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['material_batch_id']);
                if ($batch && $batch->getMaterialItemId() === $item->getMaterialItemId()) {
                    $item->setMaterialBatch($batch);
                }
            } else {
                $item->setMaterialBatch(null);
            }
        }
        $item->touch();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeContainerItem($item));
    }

    #[Route('/pack-containers/{containerId}/items/{itemId}', name: 'items_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteItem(string $activityId, string $containerId, string $itemId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) return $activity;

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Container nicht gefunden'], 404);
        }

        $item = $this->entityManager->getRepository(ActivityPackContainerItem::class)->find($itemId);
        if (!$item || $item->getPackContainerId() !== $containerId) {
            return new JsonResponse(['error' => 'Container-Item nicht gefunden'], 404);
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    private function serializeContainer(ActivityPackContainer $container): array
    {
        return [
            'id' => $container->getId(),
            'activity_id' => $container->getActivityId(),
            'container_batch_id' => $container->getContainerBatchId(),
            'label' => $container->getLabel(),
            'status' => $container->getStatus(),
            'created_at' => $container->getCreatedAt()->format('c'),
            'updated_at' => $container->getUpdatedAt()->format('c'),
        ];
    }

    private function serializeContainerItem(ActivityPackContainerItem $item): array
    {
        return [
            'id' => $item->getId(),
            'pack_container_id' => $item->getPackContainerId(),
            'material_item_id' => $item->getMaterialItemId(),
            'material_batch_id' => $item->getMaterialBatchId(),
            'quantity_packed' => $item->getQuantityPacked(),
            'quantity_issued' => $item->getQuantityIssued(),
            'quantity_returned' => $item->getQuantityReturned(),
            'condition_out' => $item->getConditionOut(),
            'notes' => $item->getNotes(),
            'material_name' => $item->getMaterialItem()->getName(),
            'serial_number' => $item->getMaterialBatch()?->getSerialNumber(),
            'batch_label' => $item->getMaterialBatch()?->getLabel(),
            'created_at' => $item->getCreatedAt()->format('c'),
            'updated_at' => $item->getUpdatedAt()->format('c'),
        ];
    }

    private function findActivityWithAccess(string $activityId): Activity|JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($activityId);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivitaet nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->activityAccess->canUserViewActivity($currentUser, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer diese Aktivitaet'], 403);
        }

        return $activity;
    }
}

