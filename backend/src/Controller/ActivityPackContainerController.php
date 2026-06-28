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
use App\Service\ActivityItemPipelineStatusService;
use App\Service\ActivityKisteMaterialLinker;
use App\Service\ActivityPackEventHistoryService;
use App\Service\PackPipelineService;
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
        private PackPipelineService $packPipeline,
        private ActivityItemPipelineStatusService $activityItemPipelineStatus,
        private ActivityPackEventHistoryService $packEventHistory,
    ) {}

    private function flushWithPipelineSync(Activity $activity): void
    {
        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();
    }

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
        $batchAssigned = $batch !== null;
        if ($batchAssigned) {
            $this->kisteMaterialLinker->linkKisteOnContainerBatchAssigned(
                $activity,
                $batch,
                $user,
                $container->getId(),
            );
        }
        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();
        if ($batchAssigned && $this->kisteMaterialLinker->reconcileShellPackItemsPackedFromContainers($activity, $user)) {
            $this->activityItemPipelineStatus->syncForActivity($activity);
            $this->entityManager->flush();
        }

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
        $batchAssigned = false;
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
                $this->kisteMaterialLinker->linkKisteOnContainerBatchAssigned(
                    $activity,
                    $batch,
                    $user,
                    $container->getId(),
                );
                $batchAssigned = true;
            } else {
                $container->setContainerBatch(null);
            }
        }
        $container->touch();
        $this->flushWithPipelineSync($activity);
        if ($batchAssigned && $this->kisteMaterialLinker->reconcileShellPackItemsPackedFromContainers($activity, $user)) {
            $this->activityItemPipelineStatus->syncForActivity($activity);
            $this->entityManager->flush();
        }

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

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten der Packliste'], 403);
        }

        $batch = $container->getContainerBatch();
        if ($batch !== null) {
            $this->kisteMaterialLinker->unlinkKisteOnContainerRemoved(
                $activity,
                $batch,
                $user,
                $container->getId(),
            );
        }
        $this->dissolveContainerPackQuantitiesBeforeDelete($activity, $container);
        $removedContainerId = $container->getId();
        $this->entityManager->remove($container);
        $this->kisteMaterialLinker->reconcileOrphanPackItemsWithoutMaterialLine(
            $activity,
            $removedContainerId,
        );
        $this->flushWithPipelineSync($activity);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Beim Löschen einer Kiste in «Bestätigt → Gepackt»: eingepackte Mengen aus dem Behälter
     * wieder auf die linke Spalte (quantity_packed reduzieren), nicht als lose «Gepackt» stehen lassen.
     */
    private function dissolveContainerPackQuantitiesBeforeDelete(Activity $activity, ActivityPackContainer $container): void
    {
        if (!in_array($activity->getStatus(), [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
        ], true)) {
            return;
        }

        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)
            ->findBy(['packContainerId' => $container->getId()]);

        $shellMaterialId = null;
        $batch = $container->getContainerBatch();
        if ($batch !== null) {
            $batchMaterial = $batch->getMaterialItem();
            $shellMaterialId = $batchMaterial->getId();
        }

        foreach ($items as $ci) {
            if (!$ci instanceof ActivityPackContainerItem) {
                continue;
            }
            if ($shellMaterialId !== null && $ci->getMaterialItemId() === $shellMaterialId) {
                continue;
            }
            $this->movePackItemBackForContainerLine($activity, $ci);
        }

        // Lager-Kiste: unlink entfernt Activity-/Pack-Zeile — kein applyBackward (sonst taucht die Kiste links wieder auf).
        if (
            $batch === null
            && $shellMaterialId !== null
            && $this->countOtherShellContainers($activity, $container, $shellMaterialId) === 0
        ) {
            $shellPack = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $shellMaterialId,
            ]);
            if ($shellPack instanceof ActivityPackItem) {
                $profile = $this->packPipeline->profileForActivityType($activity->getType());
                $max = $this->packPipeline->maxBackwardQty($shellPack, PackPipelineService::STAGE_PACKED, $profile);
                if ($max > 0) {
                    $this->packPipeline->applyBackward($shellPack, PackPipelineService::STAGE_PACKED, $max);
                    $shellPack->setUpdatedAt(new \DateTime());
                }
            }
        }
    }

    private function movePackItemBackForContainerLine(Activity $activity, ActivityPackContainerItem $ci): void
    {
        $qty = max(0, $ci->getQuantityPacked());
        if ($qty < 1) {
            return;
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
            'activityId' => $activity->getId(),
            'materialItemId' => $ci->getMaterialItemId(),
        ]);
        if (!$packItem instanceof ActivityPackItem) {
            return;
        }

        $profile = $this->packPipeline->profileForActivityType($activity->getType());
        $moveBack = min($qty, $this->packPipeline->maxBackwardQty($packItem, PackPipelineService::STAGE_PACKED, $profile));
        if ($moveBack < 1) {
            return;
        }

        $this->packPipeline->applyBackward($packItem, PackPipelineService::STAGE_PACKED, $moveBack);
        $packItem->setUpdatedAt(new \DateTime());
    }

    private function countOtherShellContainers(
        Activity $activity,
        ActivityPackContainer $exclude,
        string $shellMaterialId,
    ): int {
        $others = $this->entityManager->getRepository(ActivityPackContainer::class)->findBy([
            'activityId' => $activity->getId(),
        ]);
        $count = 0;
        foreach ($others as $pc) {
            if (!$pc instanceof ActivityPackContainer || $pc->getId() === $exclude->getId()) {
                continue;
            }
            $bid = $pc->getContainerBatchId();
            if ($bid === null || $bid === '') {
                continue;
            }
            $batch = $pc->getContainerBatch();
            if ($batch !== null && $batch->getMaterialItemId() === $shellMaterialId) {
                ++$count;
            }
        }

        return $count;
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
        $this->flushWithPipelineSync($activity);

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
        if (array_key_exists('quantity_stored', $data)) $item->setQuantityStored(max(0, (int) $data['quantity_stored']));
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
        $this->flushWithPipelineSync($activity);

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
        $this->flushWithPipelineSync($activity);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Alle noch nicht ausgegebenen Mengen in diesem Behälter «Am Event» buchen (wie Pack-Position issue, gebündelt).
     */
    #[Route('/pack-containers/{containerId}/issue-all', name: 'container_issue_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function issueAllInContainer(string $activityId, string $containerId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $stage = $this->packPipeline->normalizeStage(
            (string) ($data['stage'] ?? PackPipelineService::STAGE_AT_EVENT),
        );

        $source = is_array($data) ? ($data['source'] ?? null) : null;

        return $this->bulkWorkflowContainer(
            $activityId,
            $containerId,
            'issue_all',
            $stage,
            is_string($source) ? $source : null,
        );
    }

    /**
     * Alle noch nicht retournierten Mengen in diesem Behälter zur Retour erfassen.
     */
    #[Route('/pack-containers/{containerId}/return-all', name: 'container_return_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function returnAllInContainer(string $activityId, string $containerId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $source = is_array($data) ? ($data['source'] ?? null) : null;

        return $this->bulkWorkflowContainer(
            $activityId,
            $containerId,
            'return_all',
            PackPipelineService::STAGE_RETURNED,
            is_string($source) ? $source : null,
        );
    }

    /**
     * Ausgabe für den ganzen Behälter zurücknehmen (noch nicht retournierte Teile → wieder «Gepackt»).
     */
    #[Route('/pack-containers/{containerId}/unissue-all', name: 'container_unissue_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unissueAllInContainer(string $activityId, string $containerId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $stage = $this->packPipeline->normalizeStage(
            (string) ($data['stage'] ?? PackPipelineService::STAGE_AT_EVENT),
        );
        $source = is_array($data) ? ($data['source'] ?? null) : null;

        return $this->bulkWorkflowContainer(
            $activityId,
            $containerId,
            'unissue_all',
            $stage,
            is_string($source) ? $source : null,
        );
    }

    private function bulkWorkflowContainer(
        string $activityId,
        string $containerId,
        string $mode,
        string $pipelineStage = PackPipelineService::STAGE_AT_EVENT,
        ?string $source = null,
    ): JsonResponse
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

        $deny = $this->assertCanBulkPackContainerWorkflow($user, $activity, $mode);
        if ($deny !== null) {
            return $deny;
        }

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Behälter nicht gefunden'], 404);
        }

        $profile = $this->packPipeline->profileForActivityType($activity->getType());
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
                $apply = $this->bulkForwardApplyQty($ci, $packItem, $pipelineStage, $profile);
                if ($apply <= 0) {
                    continue;
                }
                $this->packPipeline->applyForwardContainer($ci, $pipelineStage, $apply, $profile);
                $this->packPipeline->applyForward($packItem, $pipelineStage, $apply, $profile);
            } elseif ($mode === 'return_all') {
                $delta = $ci->getQuantityIssued() - $ci->getQuantityReturned();
                if ($delta <= 0 && $ci->getQuantityIssued() === 0 && $ci->getQuantityPacked() > $ci->getQuantityReturned()) {
                    // In Kiste gepackt, nie lose ans Event — retourniert mit der Kiste
                    $delta = $ci->getQuantityPacked() - $ci->getQuantityReturned();
                }
                if ($delta <= 0) {
                    continue;
                }
                $maxPack = $packItem->getQuantityIssued() - $packItem->getQuantityReturned();
                if ($maxPack <= 0 && $ci->getQuantityIssued() === 0) {
                    $maxPack = $packItem->getQuantityPacked() - $packItem->getQuantityReturned();
                }
                if ($maxPack <= 0) {
                    continue;
                }
                $apply = min($delta, $maxPack);
                if ($apply <= 0) {
                    continue;
                }
                $ci->setQuantityReturned($ci->getQuantityReturned() + $apply);
                $packItem->setQuantityReturned($packItem->getQuantityReturned() + $apply);
            } elseif ($mode === 'unissue_all') {
                $shellMaterialId = $container->getContainerBatch()?->getMaterialItemId();
                if ($shellMaterialId !== null && $ci->getMaterialItemId() === $shellMaterialId) {
                    continue;
                }
                $apply = $this->bulkBackwardApplyQty($ci, $packItem, $pipelineStage, $profile);
                if ($apply <= 0) {
                    continue;
                }
                $this->packPipeline->applyBackward($packItem, $pipelineStage, $apply);
                $this->packPipeline->applyBackwardContainer($ci, $pipelineStage, $apply);
            } else {
                return new JsonResponse(['error' => 'Ungültiger Modus'], 400);
            }

            $ci->touch();
            $packItem->setUpdatedAt(new \DateTime());
            $updatedLines++;
            $appliedTotal += $apply;
        }

        $shell = $this->applyShellPackItemForBulkWorkflow($activityId, $container, $mode, $pipelineStage, $profile);
        $updatedLines += $shell['lines'];
        $appliedTotal += $shell['units'];

        $this->flushWithPipelineSync($activity);

        $this->packEventHistory->logContainerBulk(
            $activity,
            $container,
            $mode,
            $pipelineStage,
            $appliedTotal,
            $updatedLines,
            $user,
            $source,
        );

        return new JsonResponse([
            'success' => true,
            'updated_container_lines' => $updatedLines,
            'applied_units' => $appliedTotal,
        ]);
    }

    private function bulkForwardApplyQty(
        ActivityPackContainerItem $ci,
        ActivityPackItem $packItem,
        string $pipelineStage,
        string $profile,
    ): int {
        $maxLine = $this->packPipeline->maxForwardContainerQty($ci, $pipelineStage, $profile);
        $maxPack = $this->packPipeline->maxForwardQty($packItem, $pipelineStage, $profile);
        $apply = min($maxLine, $maxPack);

        if ($apply > 0) {
            return $apply;
        }

        if ($pipelineStage !== PackPipelineService::STAGE_AT_EVENT) {
            return 0;
        }

        $p = $ci->getQuantityPacked();
        $i = $ci->getQuantityIssued();
        $delta = $p - $i;
        if ($delta <= 0 && $maxPack > 0 && $p > 0) {
            $delta = min($p, $maxPack);
        }
        if ($delta <= 0) {
            return 0;
        }

        return min($delta, $maxPack > 0 ? $maxPack : $delta);
    }

    private function bulkBackwardApplyQty(
        ActivityPackContainerItem $ci,
        ActivityPackItem $packItem,
        string $pipelineStage,
        string $profile,
    ): int {
        $maxLine = $this->packPipeline->maxBackwardQty($packItem, $pipelineStage, $profile);
        if ($pipelineStage === PackPipelineService::STAGE_AT_EVENT) {
            $lineIssued = max(0, $ci->getQuantityIssued() - $ci->getQuantityReturned());

            return min($lineIssued, $maxLine);
        }
        if ($pipelineStage === PackPipelineService::STAGE_TRANSPORT_TO) {
            $lineTransported = max(0, $ci->getQuantityTransportTo() - $ci->getQuantityIssued());

            return min($lineTransported, $maxLine);
        }
        if ($pipelineStage === PackPipelineService::STAGE_TRANSPORT_BACK) {
            $lineBack = max(0, $ci->getQuantityTransportBack() - $ci->getQuantityReturned());

            return min($lineBack, $maxLine);
        }
        if ($pipelineStage === PackPipelineService::STAGE_RETURNED) {
            $lineReturned = max(0, $ci->getQuantityReturned() - $ci->getQuantityStored());

            return min($lineReturned, $maxLine);
        }

        return 0;
    }

    /**
     * Die zugeordnete Lager-Kiste (Material der Container-Charge) ist eine eigene Pack-Position — mit ausgeben/retournieren.
     */
    private function applyShellPackItemForBulkWorkflow(
        string $activityId,
        ActivityPackContainer $container,
        string $mode,
        string $pipelineStage = PackPipelineService::STAGE_AT_EVENT,
        string $profile = PackPipelineService::PROFILE_LOGISTICS,
    ): array
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
            $apply = $this->packPipeline->maxForwardQty($packItem, $pipelineStage, $profile);
            if ($apply <= 0 && $pipelineStage === PackPipelineService::STAGE_AT_EVENT) {
                $containerItems = $this->entityManager->getRepository(ActivityPackContainerItem::class)
                    ->findBy(['packContainerId' => $container->getId()]);
                $contentsIssued = false;
                foreach ($containerItems as $ci) {
                    if ($ci instanceof ActivityPackContainerItem && $ci->getQuantityIssued() > 0) {
                        $contentsIssued = true;
                        break;
                    }
                }
                if ($contentsIssued && $packItem->getQuantityIssued() < 1) {
                    if ($packItem->getQuantityPacked() < 1) {
                        $packItem->setQuantityPacked(1);
                    }
                    $apply = 1;
                } else {
                    return ['lines' => 0, 'units' => 0];
                }
            }
            if ($apply <= 0) {
                return ['lines' => 0, 'units' => 0];
            }
            // Eine Packkiste pro issue-all — Shell-Menge gilt für alle Kisten derselben Charge.
            $apply = min($apply, 1);
            $this->packPipeline->applyForward($packItem, $pipelineStage, $apply, $profile);
        } elseif ($mode === 'return_all') {
            if ($this->containerHasInnerReturnPending($container)) {
                return ['lines' => 0, 'units' => 0];
            }
            $delta = $packItem->getQuantityIssued() - $packItem->getQuantityReturned();
            if ($delta <= 0) {
                return ['lines' => 0, 'units' => 0];
            }
            $apply = min($delta, 1);
            $packItem->setQuantityReturned($packItem->getQuantityReturned() + $apply);
        } elseif ($mode === 'unissue_all') {
            $apply = $this->packPipeline->maxBackwardQty($packItem, $pipelineStage, $profile);
            if ($apply <= 0) {
                return ['lines' => 0, 'units' => 0];
            }
            $apply = min($apply, 1);
            $this->packPipeline->applyBackward($packItem, $pipelineStage, $apply);
        } else {
            return ['lines' => 0, 'units' => 0];
        }

        $packItem->setUpdatedAt(new \DateTime());

        return ['lines' => 1, 'units' => $apply];
    }

    /** Packinhalt noch nicht retourniert — Behälter (Shell) erst danach. */
    private function containerHasInnerReturnPending(ActivityPackContainer $container): bool
    {
        $shellMaterialId = $container->getContainerBatch()?->getMaterialItemId();
        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)
            ->findBy(['packContainerId' => $container->getId()]);
        foreach ($items as $ci) {
            if (!$ci instanceof ActivityPackContainerItem) {
                continue;
            }
            if ($shellMaterialId !== null && $ci->getMaterialItemId() === $shellMaterialId) {
                continue;
            }
            $delta = $ci->getQuantityIssued() - $ci->getQuantityReturned();
            if ($delta <= 0 && $ci->getQuantityIssued() === 0 && $ci->getQuantityPacked() > $ci->getQuantityReturned()) {
                $delta = $ci->getQuantityPacked() - $ci->getQuantityReturned();
            }
            if ($delta > 0) {
                return true;
            }
        }

        return false;
    }

    private function serializeContainer(ActivityPackContainer $container): array
    {
        $batch = $container->getContainerBatch();
        $rackName = null;
        $slotName = null;
        if ($batch !== null) {
            $rack = $batch->getRack();
            $slot = $batch->getSlot();
            $rackName = $rack?->getName();
            $slotName = $slot?->getName();
        }

        return [
            'id' => $container->getId(),
            'activity_id' => $container->getActivityId(),
            'container_batch_id' => $container->getContainerBatchId(),
            /** Stammdaten-Material der Kisten-Charge bzw. virtuelle Phys.-Kombi-Zeile */
            'container_material_item_id' => $this->kisteMaterialLinker->shellMaterialIdForPackContainer($container),
            'container_serial_number' => $batch?->getSerialNumber(),
            'container_batch_label' => $batch?->getLabel(),
            'container_storage_rack_name' => $rackName,
            'container_storage_slot_name' => $slotName,
            'label' => $container->getLabel(),
            'status' => $container->getStatus(),
            'source_activity_item_id' => $container->getSourceActivityItemId(),
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
            'quantity_stored' => $item->getQuantityStored(),
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
     * Kisten-Bulk (issue/unissue/return): Pack-Workflow — Gruppe/Ersteller ab «Gepackt», nicht MW-Materialliste.
     */
    private function assertCanBulkPackContainerWorkflow(User $user, Activity $activity, string $mode): ?JsonResponse
    {
        if ($this->activityAccess->isHostDepartmentMwOrDc($user, $activity)) {
            return null;
        }

        if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung für diese Pack-Buchung'], 403);
        }

        $stageForMode = match ($mode) {
            'issue_all', 'unissue_all' => PackPipelineService::STAGE_AT_EVENT,
            'return_all' => PackPipelineService::STAGE_RETURNED,
            default => null,
        };

        if ($stageForMode !== null) {
            $allowedStages = $this->activityAccess->allowedPackMoveStagesForUser($user, $activity);
            if ($allowedStages !== null && !\in_array($stageForMode, $allowedStages, true)) {
                return new JsonResponse(['error' => 'Keine Berechtigung für diese Pack-Stufe'], 403);
            }
        }

        return null;
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

