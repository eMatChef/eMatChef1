<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\ActivityKisteMaterialLinker;
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
        private ActivityAccessService $activityAccess,
        private ActivityKisteMaterialLinker $kisteMaterialLinker,
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

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') return new JsonResponse(['error' => 'label ist erforderlich'], 400);

        $batch = null;
        if (!empty($data['container_batch_id'])) {
            $rawBatchId = trim((string) $data['container_batch_id']);
            $b = $this->entityManager->getRepository(MaterialBatch::class)->find($rawBatchId);
            if (!$b) {
                return new JsonResponse(['error' => 'Kiste (Batch) nicht gefunden'], 400);
            }
            if ($b->getMaterialItem()->getDepartmentId() !== $activity->getDepartmentId()) {
                return new JsonResponse(['error' => 'Kiste gehört nicht zur Abteilung dieser Aktivität'], 400);
            }
            $deny = $this->assertCanModifyActivityMaterialItems($user, $activity);
            if ($deny !== null) {
                return $deny;
            }
            $batch = $b;
        }

        $container = new ActivityPackContainer();
        $container->setId(IdGenerator::generate13Unique($this->entityManager, ActivityPackContainer::class, 'pc'));
        $container->setActivity($activity);
        $container->setLabel($label);
        $container->setStatus((string) ($data['status'] ?? 'draft'));
        if ($batch !== null) {
            $container->setContainerBatch($batch);
        }

        $this->entityManager->persist($container);
        if ($batch !== null) {
            $this->kisteMaterialLinker->linkKisteOnContainerBatchAssigned($activity, $batch, $user);
        }
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

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('label', $data)) $container->setLabel(trim((string) $data['label']));
        if (array_key_exists('status', $data)) $container->setStatus((string) $data['status']);
        if (array_key_exists('container_batch_id', $data)) {
            if ($data['container_batch_id']) {
                $rawBatchId = trim((string) $data['container_batch_id']);
                $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($rawBatchId);
                if (!$batch) {
                    return new JsonResponse(['error' => 'Kiste (Batch) nicht gefunden'], 400);
                }
                if ($batch->getMaterialItem()->getDepartmentId() !== $activity->getDepartmentId()) {
                    return new JsonResponse(['error' => 'Kiste gehört nicht zur Abteilung dieser Aktivität'], 400);
                }
                $deny = $this->assertCanModifyActivityMaterialItems($user, $activity);
                if ($deny !== null) {
                    return $deny;
                }
                $container->setContainerBatch($batch);
                $this->kisteMaterialLinker->linkKisteOnContainerBatchAssigned($activity, $batch, $user);
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

    /**
     * Alle noch nicht ausgegebenen Mengen in diesem Behälter «Am Event» buchen (wie Pack-Position issue, gebündelt).
     */
    #[Route('/pack-containers/{containerId}/issue-all', name: 'container_issue_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function issueAllInContainer(string $activityId, string $containerId): JsonResponse
    {
        return $this->bulkWorkflowContainer($activityId, $containerId, 'issue_all');
    }

    /**
     * Alle noch nicht retournierten Mengen in diesem Behälter zur Retour erfassen.
     */
    #[Route('/pack-containers/{containerId}/return-all', name: 'container_return_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function returnAllInContainer(string $activityId, string $containerId): JsonResponse
    {
        return $this->bulkWorkflowContainer($activityId, $containerId, 'return_all');
    }

    /**
     * Ausgabe für den ganzen Behälter zurücknehmen (noch nicht retournierte Teile → wieder «Gepackt»).
     */
    #[Route('/pack-containers/{containerId}/unissue-all', name: 'container_unissue_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unissueAllInContainer(string $activityId, string $containerId): JsonResponse
    {
        return $this->bulkWorkflowContainer($activityId, $containerId, 'unissue_all');
    }

    private function bulkWorkflowContainer(string $activityId, string $containerId, string $mode): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        if (!$activity->isPackListEditable()) {
            return new JsonResponse(['error' => 'Packliste kann in diesem Status nicht bearbeitet werden'], 422);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $deny = $this->assertCanModifyActivityMaterialItems($user, $activity);
        if ($deny !== null) {
            return $deny;
        }

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Behälter nicht gefunden'], 404);
        }

        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)->findBy(['packContainerId' => $containerId]);
        $updatedLines = 0;
        $appliedTotal = 0;

        foreach ($items as $ci) {
            if (!$ci instanceof ActivityPackContainerItem) {
                continue;
            }
            $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
                'activityId' => $activityId,
                'materialItemId' => $ci->getMaterialItemId(),
            ]);
            if ($packItem === null) {
                continue;
            }

            if ($mode === 'issue_all') {
                $p = $ci->getQuantityPacked();
                $i = $ci->getQuantityIssued();
                $delta = $p - $i;
                $maxPack = $packItem->getQuantityPacked() - $packItem->getQuantityIssued();
                if ($delta <= 0 && $maxPack > 0 && $p > 0) {
                    // Drift: Zeile wirkt voll ausgegeben, Packliste hat noch Rest — wie Einzelbuchung
                    $delta = min($p, $maxPack);
                }
                if ($delta <= 0) {
                    continue;
                }
                $apply = min($delta, $maxPack);
                if ($apply <= 0) {
                    continue;
                }
                $ci->setQuantityIssued(min($p, $i + $apply));
                $packItem->setQuantityIssued($packItem->getQuantityIssued() + $apply);
            } elseif ($mode === 'return_all') {
                $delta = $ci->getQuantityIssued() - $ci->getQuantityReturned();
                if ($delta <= 0) {
                    continue;
                }
                $maxPack = $packItem->getQuantityIssued() - $packItem->getQuantityReturned();
                $apply = min($delta, $maxPack);
                if ($apply <= 0) {
                    continue;
                }
                $ci->setQuantityReturned($ci->getQuantityReturned() + $apply);
                $packItem->setQuantityReturned($packItem->getQuantityReturned() + $apply);
            } elseif ($mode === 'unissue_all') {
                $delta = $ci->getQuantityIssued() - $ci->getQuantityReturned();
                if ($delta <= 0) {
                    continue;
                }
                $maxPack = $packItem->getQuantityIssued() - $packItem->getQuantityReturned();
                $apply = min($delta, $maxPack);
                if ($apply <= 0) {
                    continue;
                }
                $ci->setQuantityIssued($ci->getQuantityIssued() - $apply);
                $packItem->setQuantityIssued($packItem->getQuantityIssued() - $apply);
            } else {
                return new JsonResponse(['error' => 'Ungültiger Modus'], 400);
            }

            $ci->touch();
            $packItem->setUpdatedAt(new \DateTime());
            $updatedLines++;
            $appliedTotal += $apply;
        }

        $shell = $this->applyShellPackItemForBulkWorkflow($activityId, $container, $mode);
        $updatedLines += $shell['lines'];
        $appliedTotal += $shell['units'];

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'updated_container_lines' => $updatedLines,
            'applied_units' => $appliedTotal,
        ]);
    }

    /**
     * Die zugeordnete Lager-Kiste (Material der Container-Charge) ist eine eigene Pack-Position — mit ausgeben/retournieren.
     */
    private function applyShellPackItemForBulkWorkflow(string $activityId, ActivityPackContainer $container, string $mode): array
    {
        $batch = $container->getContainerBatch();
        if ($batch === null) {
            return ['lines' => 0, 'units' => 0];
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
            'activityId' => $activityId,
            'materialItemId' => $batch->getMaterialItemId(),
        ]);
        if ($packItem === null) {
            return ['lines' => 0, 'units' => 0];
        }

        $apply = 0;

        if ($mode === 'issue_all') {
            $delta = $packItem->getQuantityPacked() - $packItem->getQuantityIssued();
            if ($delta <= 0) {
                return ['lines' => 0, 'units' => 0];
            }
            $apply = $delta;
            $packItem->setQuantityIssued($packItem->getQuantityIssued() + $apply);
        } elseif ($mode === 'return_all') {
            $delta = $packItem->getQuantityIssued() - $packItem->getQuantityReturned();
            if ($delta <= 0) {
                return ['lines' => 0, 'units' => 0];
            }
            $apply = $delta;
            $packItem->setQuantityReturned($packItem->getQuantityReturned() + $apply);
        } elseif ($mode === 'unissue_all') {
            $delta = $packItem->getQuantityIssued() - $packItem->getQuantityReturned();
            if ($delta <= 0) {
                return ['lines' => 0, 'units' => 0];
            }
            $apply = $delta;
            $packItem->setQuantityIssued($packItem->getQuantityIssued() - $apply);
        } else {
            return ['lines' => 0, 'units' => 0];
        }

        $packItem->setUpdatedAt(new \DateTime());

        return ['lines' => 1, 'units' => $apply];
    }

    private function serializeContainer(ActivityPackContainer $container): array
    {
        $batch = $container->getContainerBatch();

        return [
            'id' => $container->getId(),
            'activity_id' => $container->getActivityId(),
            'container_batch_id' => $container->getContainerBatchId(),
            /** Stammdaten-Material der Kisten-Charge — für Packliste: keine doppelte Zeile «noch zu packen» */
            'container_material_item_id' => $batch !== null ? $batch->getMaterialItemId() : null,
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
            'quantity_transport_to' => $item->getQuantityTransportTo(),
            'quantity_issued' => $item->getQuantityIssued(),
            'quantity_transport_back' => $item->getQuantityTransportBack(),
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

    /**
     * Wie ActivityController::assertCanModifyActivityMaterialItems — Kisten-Material zur Aktivität hinzufügen.
     */
    private function assertCanModifyActivityMaterialItems(User $user, Activity $activity): ?JsonResponse
    {
        if ($activity->isDraft()) {
            if (!$activity->isMaterialEditable()) {
                return new JsonResponse(['error' => 'Material kann nur im Entwurf bearbeitet werden'], 422);
            }
            if (!$this->activityAccess->canUserEditDraftActivityMaterial($user, $activity)) {
                return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten der Materialliste'], 403);
            }

            return null;
        }

        if (!$this->activityAccess->canHostMwOrDcEditActivityMaterialAfterDraft($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten der Materialliste'], 403);
        }

        return null;
    }

}

