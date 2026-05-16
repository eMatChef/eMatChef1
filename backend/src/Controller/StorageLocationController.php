<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Address;
use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\Membership;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_storage_')]
class StorageLocationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Lagerort-zentrierte Übersicht: Gestelle → Slots → Inhalt pro Platz.
     * Enthält Allokationen UND Batches mit direktem rack_id/slot_id.
     */
    #[Route('/storage-overview', name: 'storage_overview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function storageOverview(Request $request): JsonResponse
    {
        $departmentId = (string) $request->query->get('department_id', '');
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) return $access;

        $racks = $this->entityManager->getRepository(StorageRack::class)->createQueryBuilder('r')
            ->where('r.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->andWhere('r.isActive = true')
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        $conn = $this->entityManager->getConnection();

        // Inhalt aus Allokationen (rack_id + slot_id oder container_batch_id → effektiver Standort aus Kiste)
        $allocSql = "
            SELECT a.id AS allocation_id, a.batch_id, a.container_batch_id,
                   COALESCE(cb.rack_id, a.rack_id) AS rack_id,
                   COALESCE(cb.slot_id, a.slot_id) AS slot_id,
                   a.qty,
                   mi.id AS material_id, mi.name AS material_name, mi.tracking_type,
                   cb.serial_number AS container_serial,
                   cb.label AS container_label,
                   cb.rack_id AS container_rack_id,
                   cb.slot_id AS container_slot_id
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            LEFT JOIN material_batch cb ON a.container_batch_id = cb.id
            WHERE a.department_id = :departmentId
              AND (mi.deleted_at IS NULL)
              AND b.status = 'active'
        ";
        $allocRows = $conn->executeQuery($allocSql, ['departmentId' => $departmentId])->fetchAllAssociative();

        // Inhalt aus Batches mit direktem rack_id/slot_id (ohne Allokationen)
        $directSql = "
            SELECT b.id AS batch_id, b.rack_id, b.slot_id, b.qty, b.label AS batch_label,
                   mi.id AS material_id, mi.name AS material_name, mi.tracking_type
            FROM material_batch b
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            WHERE mi.department_id = :departmentId
              AND b.rack_id IS NOT NULL
              AND (mi.deleted_at IS NULL)
              AND b.status = 'active'
              AND NOT EXISTS (
                SELECT 1 FROM batch_storage_allocation a2 WHERE a2.batch_id = b.id
              )
        ";
        $directRows = $conn->executeQuery($directSql, ['departmentId' => $departmentId])->fetchAllAssociative();

        $rackKeyToContents = [];
        foreach ($allocRows as $row) {
            $rackId = $row['rack_id'];
            $slotId = $row['slot_id'] ?? '';
            $key = $rackId . '|' . $slotId;
            if (!isset($rackKeyToContents[$rackId])) $rackKeyToContents[$rackId] = [];
            if (!isset($rackKeyToContents[$rackId][$slotId])) $rackKeyToContents[$rackId][$slotId] = [];
            $rackKeyToContents[$rackId][$slotId][] = [
                'material_id' => $row['material_id'],
                'material_name' => $row['material_name'],
                'batch_id' => $row['batch_id'],
                'allocation_id' => $row['allocation_id'],
                'container_batch_id' => $row['container_batch_id'] ?? null,
                'container_label' => $row['container_label'] ?? $row['container_serial'] ?? null,
                'qty' => (int) $row['qty'],
                'tracking_type' => $row['tracking_type'] ?? 'bulk',
            ];
        }
        foreach ($directRows as $row) {
            $rackId = $row['rack_id'];
            $slotId = $row['slot_id'] ?? '';
            $key = $rackId . '|' . $slotId;
            if (!isset($rackKeyToContents[$rackId])) $rackKeyToContents[$rackId] = [];
            if (!isset($rackKeyToContents[$rackId][$slotId])) $rackKeyToContents[$rackId][$slotId] = [];
            $rackKeyToContents[$rackId][$slotId][] = [
                'material_id' => $row['material_id'],
                'material_name' => $row['material_name'],
                'batch_id' => $row['batch_id'],
                'allocation_id' => null,
                'container_label' => $row['batch_label'] ?? null,
                'qty' => (int) $row['qty'],
                'tracking_type' => $row['tracking_type'] ?? 'bulk',
            ];
        }

        $result = [];
        foreach ($racks as $rack) {
            $rackId = $rack->getId();
            $storageAddress = $rack->getStorageAddress();
            $storageAddressName = $storageAddress
                ? trim((string) ($storageAddress->getName() ?: $storageAddress->getFullAddress()))
                : '';
            $slots = $this->entityManager->getRepository(StorageSlot::class)->createQueryBuilder('s')
                ->where('s.rackId = :rackId')
                ->setParameter('rackId', $rackId)
                ->andWhere('s.isActive = true')
                ->orderBy('s.sortOrder', 'ASC')
                ->addOrderBy('s.name', 'ASC')
                ->getQuery()
                ->getResult();

            $slotList = [];
            $slotContents = $rackKeyToContents[$rackId] ?? [];

            foreach ($slots as $slot) {
                $slotId = $slot->getId();
                $contents = $slotContents[$slotId] ?? [];
                $slotList[] = [
                    'id' => $slot->getId(),
                    'name' => $slot->getName(),
                    'contents' => $contents,
                ];
            }
            $rackLevelContents = $slotContents[''] ?? $slotContents[null] ?? [];
            if (!empty($rackLevelContents)) {
                $slotList[] = [
                    'id' => null,
                    'name' => '(ohne Platz)',
                    'contents' => $rackLevelContents,
                ];
            }

            $result[] = [
                'id' => $rack->getId(),
                'name' => $rack->getName(),
                'storage_address_id' => $rack->getStorageAddressId(),
                'storage_address_name' => $storageAddressName,
                'slots' => $slotList,
            ];
        }

        return new JsonResponse(['racks' => $result]);
    }

    #[Route('/storage-racks', name: 'racks_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listRacks(Request $request): JsonResponse
    {
        $departmentId = (string) $request->query->get('department_id', '');
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) return $access;

        $addressId = (string) $request->query->get('storage_address_id', '');
        $qb = $this->entityManager->getRepository(StorageRack::class)->createQueryBuilder('r')
            ->where('r.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.name', 'ASC');
        if ($addressId !== '') {
            $qb->andWhere('r.storageAddressId = :addressId')->setParameter('addressId', $addressId);
        }
        $racks = $qb->getQuery()->getResult();

        return new JsonResponse(array_map(fn (StorageRack $rack) => $this->serializeRack($rack), $racks));
    }

    #[Route('/storage-racks', name: 'racks_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createRack(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $departmentId = (string) ($data['department_id'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        if (!$departmentId || $name === '') {
            return new JsonResponse(['error' => 'department_id und name sind erforderlich'], 400);
        }
        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) return $access;

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) return new JsonResponse(['error' => 'Department nicht gefunden'], 404);

        $rack = new StorageRack();
        $rack->setId(IdGenerator::generate());
        $rack->setDepartment($department);
        $rack->setName($name);
        $rack->setSortOrder((int) ($data['sort_order'] ?? 0));
        $rack->setIsActive(array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true);
        $storageAddressResult = $this->resolveStorageAddressForDepartment($departmentId, $data['storage_address_id'] ?? null);
        if ($storageAddressResult instanceof JsonResponse) return $storageAddressResult;
        $rack->setStorageAddress($storageAddressResult);
        $initialSlotName = trim((string) ($data['initial_slot_name'] ?? 'A'));
        if ($initialSlotName === '') {
            return new JsonResponse(['error' => 'initial_slot_name darf nicht leer sein'], 400);
        }

        // Neues Regal bekommt standardmäßig direkt ein erstes Fach.
        $slot = new StorageSlot();
        $slot->setId(IdGenerator::generate());
        $slot->setRack($rack);
        $slot->setName($initialSlotName);
        $slot->setSortOrder(0);
        $slot->setIsActive(true);

        try {
            $this->entityManager->persist($rack);
            $this->entityManager->persist($slot);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['error' => 'Regal oder Standard-Fach konnte nicht angelegt werden (Namenskonflikt).'], 409);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Fehler beim Anlegen des Regals.'], 500);
        }

        return new JsonResponse($this->serializeRack($rack), 201);
    }

    /**
     * Inhalt eines Lagerplatzes (aggregiert nach Material) – für "Combo aus Lagerplatz erstellen"
     */
    #[Route('/storage-racks/{id}/contents', name: 'racks_contents', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function rackContents(string $id): JsonResponse
    {
        $rack = $this->entityManager->getRepository(StorageRack::class)->find($id);
        if (!$rack) return new JsonResponse(['error' => 'Lagerplatz nicht gefunden'], 404);
        $access = $this->assertDepartmentAccess($rack->getDepartmentId());
        if ($access instanceof JsonResponse) return $access;

        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT mi.id AS material_id, mi.name AS material_name, mi.tracking_type,
                   SUM(a.qty) AS qty
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            LEFT JOIN material_batch cb ON a.container_batch_id = cb.id
            WHERE (a.rack_id = :rackId OR (a.container_batch_id IS NOT NULL AND cb.rack_id = :rackId))
              AND (mi.deleted_at IS NULL)
              AND b.status = 'active'
            GROUP BY mi.id, mi.name, mi.tracking_type
            ORDER BY mi.name
        ";
        $rows = $conn->executeQuery($sql, ['rackId' => $id])->fetchAllAssociative();

        $contents = [];
        foreach ($rows as $row) {
            $contents[] = [
                'material_id' => $row['material_id'],
                'material_name' => $row['material_name'],
                'tracking_type' => $row['tracking_type'] ?? 'bulk',
                'qty' => (int) $row['qty'],
            ];
        }

        return new JsonResponse([
            'rack_id' => $id,
            'rack_name' => $rack->getName(),
            'contents' => $contents,
        ]);
    }

    #[Route('/storage-racks/{id}', name: 'racks_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateRack(string $id, Request $request): JsonResponse
    {
        $rack = $this->entityManager->getRepository(StorageRack::class)->find($id);
        if (!$rack) return new JsonResponse(['error' => 'Rack nicht gefunden'], 404);
        $access = $this->assertDepartmentAccess($rack->getDepartmentId());
        if ($access instanceof JsonResponse) return $access;

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('name', $data)) $rack->setName(trim((string) $data['name']));
        if (array_key_exists('sort_order', $data)) $rack->setSortOrder((int) $data['sort_order']);
        if (array_key_exists('is_active', $data)) $rack->setIsActive((bool) $data['is_active']);
        if (array_key_exists('storage_address_id', $data)) {
            $storageAddressResult = $this->resolveStorageAddressForDepartment($rack->getDepartmentId(), $data['storage_address_id']);
            if ($storageAddressResult instanceof JsonResponse) return $storageAddressResult;
            $rack->setStorageAddress($storageAddressResult);
        }
        $rack->touch();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeRack($rack));
    }

    #[Route('/storage-racks/{id}', name: 'racks_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteRack(string $id): JsonResponse
    {
        $rack = $this->entityManager->getRepository(StorageRack::class)->find($id);
        if (!$rack) return new JsonResponse(['error' => 'Rack nicht gefunden'], 404);
        $access = $this->assertDepartmentAccess($rack->getDepartmentId());
        if ($access instanceof JsonResponse) return $access;

        $this->entityManager->remove($rack);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * Batches mit Lagerort (rack_id), die als Behälter gelten.
     * Stammdaten «Behälter» (material_item.is_container) oder serialisierte Instanz (material_batch.is_container);
     * keine physischen Kombis;
     * keine Batches, die bereits als Referenz-Kiste einer physischen Kombi verknüpft sind.
     *
     * Optional: activity_id=… → für Packliste: nur leere Kisten (storage_empty), nicht schon dieser Aktivität zugeordnet;
     * mit Planungs-/Nutzungszeitraum: zusätzlich keine, die parallel anderer Aktivität gebucht sind (kein «leer oder frei»).
     * Feld storage_empty: true, wenn die Kiste keinen relevanten Lagerinhalt hat (Vorschau leer).
     */
    #[Route('/container-batches', name: 'container_batches_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listContainerBatches(Request $request): JsonResponse
    {
        $departmentId = (string) $request->query->get('department_id', '');
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) return $access;

        $activityIdForPack = trim((string) $request->query->get('activity_id', ''));
        $busyBatchIds = [];
        $assignedOnActivityBatchIds = [];
        $filterForPack = false;
        $rangeStart = null;
        $rangeEnd = null;

        if ($activityIdForPack !== '') {
            $activity = $this->entityManager->getRepository(Activity::class)->find($activityIdForPack);
            if (!$activity || $activity->isDeleted()) {
                return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
            }
            if ($activity->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'department_id passt nicht zur Aktivität'], 400);
            }
            $filterForPack = true;
            $conn = $this->entityManager->getConnection();
            $assignedOnActivityBatchIds = $this->fetchContainerBatchIdsAssignedToActivity($conn, $activityIdForPack);
            $rangeStart = $activity->getPlanningStart() ?? $activity->getUsageStart();
            $rangeEnd = $activity->getPlanningEnd() ?? $activity->getUsageEnd();
            if ($rangeStart !== null && $rangeEnd !== null) {
                $busyBatchIds = $this->fetchContainerBatchIdsBusyInOverlappingActivities(
                    $conn,
                    $activityIdForPack,
                    $departmentId,
                    $rangeStart,
                    $rangeEnd
                );
            }
        }

        $batches = $this->entityManager->getRepository(MaterialBatch::class)
            ->createQueryBuilder('b')
            ->innerJoin('b.materialItem', 'mi')
            ->leftJoin('b.rack', 'r')
            ->leftJoin('b.slot', 's')
            ->addSelect('mi', 'r', 's')
            ->where('mi.departmentId = :departmentId')
            ->andWhere('b.rackId IS NOT NULL')
            ->andWhere('b.status = :status')
            ->andWhere('mi.deletedAt IS NULL')
            ->andWhere('(mi.isContainer = true OR b.isContainer = true)')
            ->andWhere('mi.materialType <> :physicalCombo')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM ' . MaterialItem::class . ' micb
                WHERE micb.linkedContainerBatchId = b.id
            )')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('status', 'active')
            ->setParameter('physicalCombo', 'physical_combo')
            ->orderBy('mi.name', 'ASC')
            ->addOrderBy('b.serialNumber', 'ASC')
            ->addOrderBy('b.label', 'ASC')
            ->getQuery()
            ->getResult();

        $batchIds = array_map(static fn (MaterialBatch $b) => $b->getId(), $batches);
        $conn = $this->entityManager->getConnection();
        $previewById = $this->buildContainerBatchContentPreviewMap($conn, $batchIds);

        $result = [];
        foreach ($batches as $b) {
            $bid = $b->getId();
            $pv = $previewById[$bid] ?? ['content_preview' => [], 'content_preview_more' => 0];
            $storageEmpty = $this->isContainerBatchStorageEmpty($pv);

            if ($filterForPack) {
                if (in_array($bid, $assignedOnActivityBatchIds, true)) {
                    continue;
                }
                if (!$storageEmpty) {
                    continue;
                }
                $periodKnown = $rangeStart !== null && $rangeEnd !== null;
                if ($periodKnown && $busyBatchIds !== [] && in_array($bid, $busyBatchIds, true)) {
                    continue;
                }
            }

            $result[] = [
                'id' => $bid,
                'material_id' => $b->getMaterialItem()->getId(),
                'serial_number' => $b->getSerialNumber(),
                'label' => $b->getLabel(),
                'material_name' => $b->getMaterialItem()->getName(),
                'display_label' => trim(($b->getSerialNumber() ?: '') . ' – ' . ($b->getLabel() ?: $b->getMaterialItem()->getName())),
                'rack_id' => $b->getRackId(),
                'slot_id' => $b->getSlotId(),
                'rack' => $b->getRack() ? ['id' => $b->getRack()->getId(), 'name' => $b->getRack()->getName()] : null,
                'slot' => $b->getSlot() ? ['id' => $b->getSlot()->getId(), 'name' => $b->getSlot()->getName()] : null,
                'content_preview' => $pv['content_preview'],
                'content_preview_more' => $pv['content_preview_more'],
                'storage_empty' => $storageEmpty,
            ];
        }

        if ($filterForPack && $result !== []) {
            usort($result, static function (array $a, array $b): int {
                $ea = $a['storage_empty'] ?? false;
                $eb = $b['storage_empty'] ?? false;
                if ($ea === $eb) {
                    return strcmp((string) ($a['display_label'] ?? ''), (string) ($b['display_label'] ?? ''));
                }

                return $ea ? -1 : 1;
            });
        }

        return new JsonResponse($result);
    }

    /**
     * Inhalt einer Kiste (Material-Batches, die dieser Kiste zugeordnet sind) – für Combo aus Kiste.
     */
    #[Route('/container-batches/{id}/contents', name: 'container_batch_contents', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function containerBatchContents(string $id): JsonResponse
    {
        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($id);
        if (!$batch) {
            return new JsonResponse(['error' => 'Kiste/Batch nicht gefunden'], 404);
        }

        $departmentId = $batch->getMaterialItem()->getDepartmentId();
        $access = $this->assertDepartmentAccess($departmentId);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT mi.id AS material_id, mi.name AS material_name, mi.tracking_type,
                   SUM(a.qty) AS qty
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            WHERE a.container_batch_id = :containerBatchId
              AND (mi.deleted_at IS NULL)
              AND b.status = 'active'
            GROUP BY mi.id, mi.name, mi.tracking_type
            ORDER BY mi.name
        ";
        $rows = $conn->executeQuery($sql, ['containerBatchId' => $id])->fetchAllAssociative();

        $contents = [];
        foreach ($rows as $row) {
            $contents[] = [
                'material_id' => $row['material_id'],
                'material_name' => $row['material_name'],
                'tracking_type' => $row['tracking_type'] ?? 'bulk',
                'qty' => (int) $row['qty'],
            ];
        }

        $mi = $batch->getMaterialItem();
        $label = $batch->getLabel() ?: $batch->getSerialNumber() ?: $mi->getName();

        return new JsonResponse([
            'container_batch_id' => $id,
            'container_label' => $label,
            'contents' => $contents,
        ]);
    }

    #[Route('/storage-slots', name: 'slots_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listSlots(Request $request): JsonResponse
    {
        $rackId = (string) $request->query->get('rack_id', '');
        if (!$rackId) return new JsonResponse(['error' => 'rack_id ist erforderlich'], 400);

        $rack = $this->entityManager->getRepository(StorageRack::class)->find($rackId);
        if (!$rack) return new JsonResponse(['error' => 'Rack nicht gefunden'], 404);
        $access = $this->assertDepartmentAccess($rack->getDepartmentId());
        if ($access instanceof JsonResponse) return $access;

        $slots = $this->entityManager->getRepository(StorageSlot::class)->createQueryBuilder('s')
            ->where('s.rackId = :rackId')
            ->setParameter('rackId', $rackId)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return new JsonResponse(array_map(fn (StorageSlot $slot) => $this->serializeSlot($slot), $slots));
    }

    #[Route('/storage-slots', name: 'slots_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createSlot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $rackId = (string) ($data['rack_id'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        if (!$rackId || $name === '') return new JsonResponse(['error' => 'rack_id und name sind erforderlich'], 400);

        $rack = $this->entityManager->getRepository(StorageRack::class)->find($rackId);
        if (!$rack) return new JsonResponse(['error' => 'Rack nicht gefunden'], 404);
        $access = $this->assertDepartmentAccess($rack->getDepartmentId());
        if ($access instanceof JsonResponse) return $access;

        $slot = new StorageSlot();
        $slot->setId(IdGenerator::generate());
        $slot->setRack($rack);
        $slot->setName($name);
        $slot->setSortOrder((int) ($data['sort_order'] ?? 0));
        $slot->setIsActive(array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true);
        try {
            $this->entityManager->persist($slot);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['error' => 'Ein Platz mit diesem Namen existiert bereits in diesem Gestell.'], 409);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Fehler beim Anlegen des Lagerplatzes.'], 500);
        }

        return new JsonResponse($this->serializeSlot($slot), 201);
    }

    #[Route('/storage-slots/{id}', name: 'slots_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateSlot(string $id, Request $request): JsonResponse
    {
        $slot = $this->entityManager->getRepository(StorageSlot::class)->find($id);
        if (!$slot) return new JsonResponse(['error' => 'Slot nicht gefunden'], 404);
        $access = $this->assertDepartmentAccess($slot->getRack()->getDepartmentId());
        if ($access instanceof JsonResponse) return $access;

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('name', $data)) $slot->setName(trim((string) $data['name']));
        if (array_key_exists('sort_order', $data)) $slot->setSortOrder((int) $data['sort_order']);
        if (array_key_exists('is_active', $data)) $slot->setIsActive((bool) $data['is_active']);
        $slot->touch();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeSlot($slot));
    }

    #[Route('/storage-slots/{id}', name: 'slots_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteSlot(string $id): JsonResponse
    {
        $slot = $this->entityManager->getRepository(StorageSlot::class)->find($id);
        if (!$slot) return new JsonResponse(['error' => 'Slot nicht gefunden'], 404);
        $access = $this->assertDepartmentAccess($slot->getRack()->getDepartmentId());
        if ($access instanceof JsonResponse) return $access;

        $this->entityManager->remove($slot);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    private function serializeRack(StorageRack $rack): array
    {
        $storageAddress = $rack->getStorageAddress();
        $storageAddressName = $storageAddress
            ? trim((string) ($storageAddress->getName() ?: $storageAddress->getFullAddress()))
            : '';
        return [
            'id' => $rack->getId(),
            'department_id' => $rack->getDepartmentId(),
            'storage_address_id' => $rack->getStorageAddressId(),
            'storage_address_name' => $storageAddressName,
            'name' => $rack->getName(),
            'sort_order' => $rack->getSortOrder(),
            'is_active' => $rack->getIsActive(),
            'created_at' => $rack->getCreatedAt()->format('c'),
            'updated_at' => $rack->getUpdatedAt()->format('c'),
        ];
    }

    private function resolveStorageAddressForDepartment(string $departmentId, mixed $storageAddressId): Address|JsonResponse
    {
        $storageAddressId = trim((string) ($storageAddressId ?? ''));
        if ($storageAddressId === '') {
            return new JsonResponse(['error' => 'storage_address_id ist erforderlich'], 400);
        }

        $address = $this->entityManager->getRepository(Address::class)->find($storageAddressId);
        if (!$address) {
            return new JsonResponse(['error' => 'Lagerstandort nicht gefunden'], 404);
        }

        if ($address->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Lagerstandort gehört nicht zu diesem Department'], 400);
        }

        if ($address->getType() !== 'storage') {
            return new JsonResponse(['error' => 'Adresse ist kein Lagerstandort'], 400);
        }

        return $address;
    }

    private function serializeSlot(StorageSlot $slot): array
    {
        return [
            'id' => $slot->getId(),
            'rack_id' => $slot->getRackId(),
            'name' => $slot->getName(),
            'sort_order' => $slot->getSortOrder(),
            'is_active' => $slot->getIsActive(),
            'created_at' => $slot->getCreatedAt()->format('c'),
            'updated_at' => $slot->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * @param array{content_preview: list<array{material_name: string, qty: int}>, content_preview_more: int} $pv
     */
    private function isContainerBatchStorageEmpty(array $pv): bool
    {
        if (($pv['content_preview_more'] ?? 0) > 0) {
            return false;
        }
        foreach ($pv['content_preview'] ?? [] as $line) {
            if (($line['qty'] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function fetchContainerBatchIdsAssignedToActivity(Connection $conn, string $activityId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT container_batch_id
            FROM activity_pack_container
            WHERE activity_id = ?
              AND container_batch_id IS NOT NULL
        SQL;
        $rows = $conn->executeQuery($sql, [$activityId])->fetchFirstColumn();

        return array_values(array_filter(array_map(static fn ($id) => (string) $id, $rows)));
    }

    /**
     * Kisten-Batches, die in einer anderen Aktivität derselben Abteilung gebucht sind und deren
     * Zeitraum (Planung oder Nutzung) sich mit dem gegebenen Intervall überschneidet.
     *
     * @return list<string>
     */
    private function fetchContainerBatchIdsBusyInOverlappingActivities(
        Connection $conn,
        string $excludeActivityId,
        string $departmentId,
        \DateTimeInterface $rangeStart,
        \DateTimeInterface $rangeEnd,
    ): array {
        $sql = <<<'SQL'
            SELECT DISTINCT apc.container_batch_id
            FROM activity_pack_container apc
            INNER JOIN activity a ON a.id = apc.activity_id
            WHERE apc.container_batch_id IS NOT NULL
              AND apc.activity_id <> :excludeId
              AND a.department_id = :dept
              AND a.deleted_at IS NULL
              AND a.status <> :cancelled
              AND COALESCE(a.planning_start, a.usage_start) IS NOT NULL
              AND COALESCE(a.planning_end, a.usage_end) IS NOT NULL
              AND :cStart <= COALESCE(a.planning_end, a.usage_end)
              AND COALESCE(a.planning_start, a.usage_start) <= :cEnd
        SQL;

        $rows = $conn->executeQuery($sql, [
            'excludeId' => $excludeActivityId,
            'dept' => $departmentId,
            'cancelled' => Activity::STATUS_CANCELLED,
            'cStart' => $rangeStart,
            'cEnd' => $rangeEnd,
        ], [
            'excludeId' => ParameterType::STRING,
            'dept' => ParameterType::STRING,
            'cancelled' => ParameterType::STRING,
            'cStart' => $rangeStart instanceof \DateTimeImmutable
                ? Types::DATETIME_IMMUTABLE
                : Types::DATETIME_MUTABLE,
            'cEnd' => $rangeEnd instanceof \DateTimeImmutable
                ? Types::DATETIME_IMMUTABLE
                : Types::DATETIME_MUTABLE,
        ])->fetchFirstColumn();

        return array_values(array_filter(array_map(static fn ($id) => (string) $id, $rows)));
    }

    /**
     * Pro Kiste: bis zu 2 Artikelpositionen (Name + Menge) und Anzahl weiterer Artikel – eine Abfrage für alle Kisten-IDs.
     *
     * @param array<int, string> $batchIds
     *
     * @return array<string, array{content_preview: list<array{material_name: string, qty: int}>, content_preview_more: int}>
     */
    private function buildContainerBatchContentPreviewMap(\Doctrine\DBAL\Connection $conn, array $batchIds): array
    {
        if ($batchIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $sql = "
            SELECT a.container_batch_id AS cb_id, mi.name AS material_name, SUM(a.qty) AS qty
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            WHERE a.container_batch_id IN ($placeholders)
              AND (mi.deleted_at IS NULL)
              AND b.status = 'active'
            GROUP BY a.container_batch_id, mi.id, mi.name
            ORDER BY a.container_batch_id, mi.name
        ";
        $rows = $conn->executeQuery($sql, $batchIds)->fetchAllAssociative();

        $byCb = [];
        foreach ($rows as $row) {
            $cbId = (string) $row['cb_id'];
            if (!isset($byCb[$cbId])) {
                $byCb[$cbId] = [];
            }
            $byCb[$cbId][] = [
                'material_name' => (string) $row['material_name'],
                'qty' => (int) $row['qty'],
            ];
        }
        $out = [];
        foreach ($byCb as $cbId => $lines) {
            $out[$cbId] = [
                'content_preview' => array_slice($lines, 0, 2),
                'content_preview_more' => max(0, count($lines) - 2),
            ];
        }

        return $out;
    }

    private function assertDepartmentAccess(string $departmentId): true|JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);

        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer dieses Department'], 403);
        }

        return true;
    }
}

