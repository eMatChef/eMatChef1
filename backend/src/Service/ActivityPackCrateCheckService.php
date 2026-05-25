<?php

namespace App\Service;

use App\Controller\WorkshopController;
use App\Entity\Activity;
use App\Entity\ActivityHistory;
use App\Entity\ActivityIssueReport;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sichtkontrolle Phys.-Kombi-Kiste vor Weiterbuchen: History, Nachlegen, Meldungen.
 */
class ActivityPackCrateCheckService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BatchStorageMoveService $batchStorageMoveService,
        private InboxMessageService $inboxMessages,
    ) {}

    /** Kistencheck: Phys.-Kombi oder physische Kiste mit Pack-Behälter. */
    public function isPackItemEligibleForCrateCheck(Activity $activity, ActivityPackItem $packItem): bool
    {
        $mi = $packItem->getMaterialItem();
        if ($mi === null) {
            return false;
        }
        if ($mi->getMaterialType() === 'physical_combo') {
            return true;
        }
        if ($mi->getMaterialType() !== 'physical') {
            return false;
        }

        $materialItemId = $packItem->getMaterialItemId();
        $linkBatch = trim((string) ($mi->getLinkedContainerBatchId() ?? ''));

        $containers = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->findBy(['activityId' => $activity->getId()]);
        foreach ($containers as $container) {
            if (!$container instanceof ActivityPackContainer) {
                continue;
            }
            $batch = $container->getContainerBatch();
            if ($batch !== null && $batch->getMaterialItemId() === $materialItemId) {
                return true;
            }
            if ($linkBatch !== '' && $container->getContainerBatchId() === $linkBatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{
     *   container_batch_id?: string|null,
     *   result: string,
     *   lines: list<array{
     *     line_key: string,
     *     material_item_id?: string|null,
     *     material_name?: string|null,
     *     expected_qty?: int,
     *     status: string,
     *     missing_qty?: int|null,
     *     note?: string|null,
     *     replenish_qty?: int|null,
     *     create_inspection_task?: bool|null
     *   }>
     * } $payload
     *
     * @return array<string, mixed>
     */
    public function apply(Activity $activity, ActivityPackItem $shellPackItem, array $payload, ?User $user): array
    {
        $departmentId = $activity->getDepartmentId();
        $containerBatchId = isset($payload['container_batch_id']) && $payload['container_batch_id'] !== ''
            ? (string) $payload['container_batch_id']
            : null;
        if ($containerBatchId === null) {
            $mi = $shellPackItem->getMaterialItem();
            $containerBatchId = $mi?->getLinkedContainerBatchId();
        }

        $lines = $payload['lines'] ?? [];
        $result = (string) ($payload['result'] ?? 'incomplete');
        $actionsApplied = [];
        $errors = [];

        foreach ($lines as $line) {
            $status = (string) ($line['status'] ?? '');
            $materialItemId = isset($line['material_item_id']) && $line['material_item_id'] !== ''
                ? (string) $line['material_item_id']
                : null;
            $materialName = (string) ($line['material_name'] ?? 'Material');
            $note = trim((string) ($line['note'] ?? ''));
            $missingQty = isset($line['missing_qty']) ? max(0, (int) $line['missing_qty']) : 0;
            $replenishQty = isset($line['replenish_qty']) ? max(0, (int) $line['replenish_qty']) : 0;
            $createInspection = !empty($line['create_inspection_task']);

            if ($status === 'ok') {
                continue;
            }

            if ($materialItemId === null || $materialItemId === '') {
                $actionsApplied[] = [
                    'line_key' => $line['line_key'] ?? '',
                    'status' => $status,
                    'skipped' => true,
                    'reason' => 'no_material_id',
                ];
                continue;
            }

            try {
                if ($status === 'replenish' && $containerBatchId !== null) {
                    $qty = $replenishQty > 0 ? $replenishQty : ($missingQty > 0 ? $missingQty : 1);
                    $move = $this->batchStorageMoveService->moveLooseQtyToContainer(
                        $materialItemId,
                        $departmentId,
                        $containerBatchId,
                        $qty,
                    );
                    $actionsApplied[] = [
                        'line_key' => $line['line_key'] ?? '',
                        'status' => 'replenish',
                        'material_item_id' => $materialItemId,
                        'qty_moved' => $move['qty_moved'],
                    ];
                    continue;
                }

                if ($status === 'loss' || $status === 'repair') {
                    $lossQty = $missingQty > 0 ? $missingQty : 1;
                    $report = $this->createIssueReport(
                        $activity,
                        $materialItemId,
                        $status === 'loss' ? ActivityIssueReport::TYPE_LOSS : ActivityIssueReport::TYPE_REPAIR,
                        $lossQty,
                        $note !== '' ? $note : sprintf(
                            'Kistencheck %s: %s',
                            $shellPackItem->getMaterialItem()?->getName() ?? 'Kiste',
                            $materialName
                        ),
                        $user,
                    );
                    $actionsApplied[] = [
                        'line_key' => $line['line_key'] ?? '',
                        'status' => $status,
                        'material_item_id' => $materialItemId,
                        'issue_report_id' => $report['id'],
                        'workshop_ticket_id' => $report['workshop_ticket_id'] ?? null,
                        'missing_qty' => $lossQty,
                    ];
                    if ($status === 'loss' && $containerBatchId !== null && $replenishQty > 0) {
                        $move = $this->batchStorageMoveService->moveLooseQtyToContainer(
                            $materialItemId,
                            $departmentId,
                            $containerBatchId,
                            $replenishQty,
                        );
                        $actionsApplied[] = [
                            'line_key' => $line['line_key'] ?? '',
                            'status' => 'replenish_after_loss',
                            'material_item_id' => $materialItemId,
                            'qty_moved' => $move['qty_moved'],
                        ];
                    }
                    continue;
                }

                if ($status === 'not_taken') {
                    $qty = $missingQty > 0 ? $missingQty : 1;
                    $report = $this->createIssueReport(
                        $activity,
                        $materialItemId,
                        ActivityIssueReport::TYPE_NOT_TAKEN,
                        $qty,
                        $note !== '' ? $note : sprintf(
                            'Nicht mitgegeben — Kistencheck %s: %s (%d Stk.)',
                            $shellPackItem->getMaterialItem()?->getName() ?? 'Kiste',
                            $materialName,
                            $qty
                        ),
                        $user,
                    );
                    $actionsApplied[] = [
                        'line_key' => $line['line_key'] ?? '',
                        'status' => 'not_taken',
                        'material_item_id' => $materialItemId,
                        'missing_qty' => $missingQty,
                        'note' => $note,
                        'issue_report_id' => $report['id'],
                        'workshop_ticket_id' => $report['workshop_ticket_id'] ?? null,
                    ];
                    continue;
                }

                if ($status === 'extra') {
                    $actionsApplied[] = [
                        'line_key' => $line['line_key'] ?? '',
                        'status' => 'extra',
                        'material_item_id' => $materialItemId,
                        'note' => $note,
                    ];
                    continue;
                }

                if ($status === 'return_surplus' && $containerBatchId !== null) {
                    $returnQty = $replenishQty > 0 ? $replenishQty : ($missingQty > 0 ? $missingQty : 1);
                    $move = $this->batchStorageMoveService->moveContainerQtyToLoose(
                        $materialItemId,
                        $departmentId,
                        $containerBatchId,
                        $returnQty,
                    );
                    $workshopTicketId = null;
                    $materialItem = $this->entityManager->getRepository(MaterialItem::class)->find($materialItemId);
                    if ($createInspection && $materialItem instanceof MaterialItem) {
                        $ticket = WorkshopController::autoCreateInspectionForCrateCheckSurplus(
                            $this->entityManager,
                            $activity,
                            $materialItem,
                            $returnQty,
                            $shellPackItem->getMaterialItem()?->getName() ?? 'Kiste',
                            $user,
                        );
                        if ($ticket) {
                            $workshopTicketId = $ticket->getId();
                        }
                    }
                    $actionsApplied[] = [
                        'line_key' => $line['line_key'] ?? '',
                        'status' => 'return_surplus',
                        'material_item_id' => $materialItemId,
                        'qty_moved' => $move['qty_moved'],
                        'note' => $note,
                        'workshop_ticket_id' => $workshopTicketId,
                    ];
                    continue;
                }

                // problem / other: nur dokumentieren
                $actionsApplied[] = [
                    'line_key' => $line['line_key'] ?? '',
                    'status' => $status,
                    'material_item_id' => $materialItemId,
                    'missing_qty' => $missingQty,
                    'note' => $note,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'line_key' => $line['line_key'] ?? '',
                    'material_item_id' => $materialItemId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->syncPackContainerQuantitiesFromCheck($activity->getId(), $containerBatchId, $lines);

        $checkLeg = (string) ($payload['check_leg'] ?? 'outbound');
        if (!\in_array($checkLeg, ['outbound', 'return', 'warehouse_store'], true)) {
            $checkLeg = 'outbound';
        }

        $this->createActivityHistory($activity, 'pack_crate_check', [
            'pack_item_id' => $shellPackItem->getId(),
            'shell_material_item_id' => $shellPackItem->getMaterialItemId(),
            'check_leg' => $checkLeg,
            'container_batch_id' => $containerBatchId,
            'result' => $result,
            'lines' => $lines,
            'actions_applied' => $actionsApplied,
            'errors' => $errors,
        ], $user);

        if ($result === 'incomplete' && $user !== null && $errors === []) {
            $this->inboxMessages->notifyActivityPackCrateCheckIncomplete(
                $activity,
                $user,
                $shellPackItem,
                $lines,
                $actionsApplied,
            );
        }

        if ($errors !== []) {
            $this->entityManager->flush();

            return [
                'ok' => false,
                'errors' => $errors,
                'actions_applied' => $actionsApplied,
            ];
        }

        $this->entityManager->flush();

        return [
            'ok' => true,
            'actions_applied' => $actionsApplied,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function looseStockByMaterialIds(string $departmentId, array $materialItemIds): array
    {
        $out = [];
        foreach ($materialItemIds as $mid) {
            $mid = trim((string) $mid);
            if ($mid === '') {
                continue;
            }
            $out[$mid] = $this->batchStorageMoveService->sumLooseQty($mid, $departmentId);
        }

        return $out;
    }

    /**
     * @return array{id: string, workshop_ticket_id?: string}
     */
    private function createIssueReport(
        Activity $activity,
        string $materialItemId,
        string $type,
        int $quantity,
        string $description,
        ?User $user,
    ): array {
        $materialItem = $this->entityManager->getRepository(MaterialItem::class)->find($materialItemId);

        $report = new ActivityIssueReport();
        $report->setId(IdGenerator::generate13('ir'));
        $report->setActivity($activity);
        $report->setType($type);
        $report->setQuantity($quantity);
        $report->setDescription($description);
        if ($materialItem) {
            $report->setMaterialItem($materialItem);
        }
        if ($user) {
            $report->setReportedByUser($user);
        }

        $this->entityManager->persist($report);

        if ($type === ActivityIssueReport::TYPE_LOSS) {
            $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $materialItemId,
            ]);
            if ($packItem) {
                $newIssued = max(0, $packItem->getQuantityIssued() - $quantity);
                $packItem->setQuantityIssued($newIssued);
                if ($packItem->getQuantityReturned() > $newIssued) {
                    $packItem->setQuantityReturned($newIssued);
                }
            }
        }

        $workshopTicketId = null;
        if (in_array($type, [
            ActivityIssueReport::TYPE_REPAIR,
            ActivityIssueReport::TYPE_DAMAGE,
            ActivityIssueReport::TYPE_LOSS,
            ActivityIssueReport::TYPE_NOT_TAKEN,
        ], true)) {
            $ticket = WorkshopController::autoCreateFromIssueReport(
                $this->entityManager,
                $report,
                $activity,
                $user,
            );
            if ($ticket) {
                $workshopTicketId = $ticket->getId();
            }
        }

        return [
            'id' => $report->getId(),
            'workshop_ticket_id' => $workshopTicketId,
        ];
    }

    /**
     * Kistencheck-Ist → quantity_packed in der Pack-Kiste (z. B. 4 statt 5 nach «nicht mitgenommen»).
     *
     * @param list<array<string, mixed>> $lines
     */
    private function syncPackContainerQuantitiesFromCheck(
        string $activityId,
        ?string $containerBatchId,
        array $lines,
    ): void {
        if ($containerBatchId === null || $containerBatchId === '') {
            return;
        }

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->findOneBy(['activityId' => $activityId, 'containerBatchId' => $containerBatchId]);
        if (!$container instanceof ActivityPackContainer) {
            return;
        }

        foreach ($lines as $line) {
            $materialItemId = trim((string) ($line['material_item_id'] ?? ''));
            if ($materialItemId === '') {
                continue;
            }

            $counted = null;
            if (isset($line['counted_qty']) && is_numeric($line['counted_qty'])) {
                $counted = max(0, (int) $line['counted_qty']);
            } elseif (($line['status'] ?? '') === 'ok' && isset($line['expected_qty'])) {
                $counted = max(0, (int) $line['expected_qty']);
            }
            if ($counted === null) {
                continue;
            }

            $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)
                ->findBy(['packContainerId' => $container->getId(), 'materialItemId' => $materialItemId]);
            foreach ($items as $ci) {
                if (!$ci instanceof ActivityPackContainerItem) {
                    continue;
                }
                $ci->setQuantityPacked(max($ci->getQuantityIssued(), $counted));
                $ci->touch();
            }
        }
    }

    private function createActivityHistory(Activity $activity, string $action, array $changes, ?User $user): void
    {
        $history = new ActivityHistory();
        $history->setId(IdGenerator::generate13('ah'));
        $history->setActivity($activity);
        $history->setAction($action);
        $history->setSnapshot([]);
        $history->setChanges($changes);
        if ($user) {
            $history->setUser($user);
        }
        $this->entityManager->persist($history);
    }
}
