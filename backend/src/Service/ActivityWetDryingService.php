<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Entity\WorkshopTicketHistory;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Nass/feucht bei Retour: quantity_wet, Trocknungsort, MW-Meldung, Werkstatt-Tickets.
 */
class ActivityWetDryingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InboxMessageService $inboxMessageService,
        private readonly PackPipelineService $packPipeline,
    ) {}

    /**
     * @param array{
     *   quantity_wet?: int,
     *   wet_hung?: bool|null,
     *   wet_drying_storage_address_id?: string|null,
     *   wet_drying_rack_id?: string|null,
     *   wet_drying_slot_id?: string|null,
     *   wet_drying_location_label?: string|null,
     * } $data
     */
    public function applyWetDispositionToPackItem(
        Activity $activity,
        ActivityPackItem $item,
        array $data,
        ?User $actor,
        bool $notify = true,
    ): void {
        $prevWet = $item->getQuantityWet();
        $prevHung = $item->getWetHung();
        $this->applyWetFields($item, $data, $item->getQuantityReturned() - $item->getQuantityStored());
        $this->assertHungRequiresDryingLocation($item);
        if ($notify) {
            $this->maybeNotifyMw(
                $activity,
                $item->getMaterialItem()?->getName() ?? 'Material',
                $item->getQuantityWet(),
                $item->getWetHung(),
                $actor,
                $prevWet,
                $prevHung,
            );
        }
    }

    /**
     * @param array{
     *   quantity_wet?: int,
     *   wet_hung?: bool|null,
     *   wet_drying_storage_address_id?: string|null,
     *   wet_drying_rack_id?: string|null,
     *   wet_drying_slot_id?: string|null,
     *   wet_drying_location_label?: string|null,
     * } $data
     */
    public function applyWetDispositionToContainerItem(
        Activity $activity,
        ActivityPackContainerItem $item,
        array $data,
        ?User $actor,
        bool $notify = true,
    ): void {
        $prevWet = $item->getQuantityWet();
        $prevHung = $item->getWetHung();
        $this->applyWetFields($item, $data, $item->getQuantityReturned() - $item->getQuantityStored());
        $this->assertHungRequiresDryingLocation($item);
        if ($notify) {
            $this->maybeNotifyMw(
                $activity,
                $item->getMaterialItem()?->getName() ?? 'Material',
                $item->getQuantityWet(),
                $item->getWetHung(),
                $actor,
                $prevWet,
                $prevHung,
            );
        }
    }

    /**
     * @param ActivityPackItem|ActivityPackContainerItem $item
     * @param array<string, mixed> $data
     */
    private function applyWetFields(object $item, array $data, int $maxWetCap): void
    {
        $maxWetCap = max(0, $maxWetCap);
        if (array_key_exists('quantity_wet', $data)) {
            $wet = max(0, min($maxWetCap, (int) $data['quantity_wet']));
            $item->setQuantityWet($wet);
        }
        if (array_key_exists('wet_hung', $data)) {
            $hung = $data['wet_hung'];
            $item->setWetHung($hung === null ? null : (bool) $hung);
        }
        if (array_key_exists('wet_drying_storage_address_id', $data)) {
            $v = $data['wet_drying_storage_address_id'];
            $item->setWetDryingStorageAddressId($v !== null && $v !== '' ? (string) $v : null);
        }
        if (array_key_exists('wet_drying_rack_id', $data)) {
            $v = $data['wet_drying_rack_id'];
            $item->setWetDryingRackId($v !== null && $v !== '' ? (string) $v : null);
        }
        if (array_key_exists('wet_drying_slot_id', $data)) {
            $v = $data['wet_drying_slot_id'];
            $item->setWetDryingSlotId($v !== null && $v !== '' ? (string) $v : null);
        }
        if (array_key_exists('wet_drying_location_label', $data)) {
            $v = $data['wet_drying_location_label'];
            $item->setWetDryingLocationLabel($v !== null && $v !== '' ? (string) $v : null);
        }

        if ($item->getQuantityWet() === 0) {
            $item->setWetHung(null);
            $item->setWetDryingStorageAddressId(null);
            $item->setWetDryingRackId(null);
            $item->setWetDryingSlotId(null);
            $item->setWetDryingLocationLabel(null);
        } elseif ($item->getWetHung() === null) {
            // Default: noch nicht aufgehängt → MW-Meldung
            $item->setWetHung(false);
        }

        if ($item->getWetHung() !== true) {
            $item->setWetDryingStorageAddressId(null);
            $item->setWetDryingRackId(null);
            $item->setWetDryingSlotId(null);
            // Label optional behalten oder leeren — leeren wenn nicht aufgehängt
            $item->setWetDryingLocationLabel(null);
        }
    }

    /** A4: bei «schon aufgehängt» mindestens Adresse oder Label. */
    private function assertHungRequiresDryingLocation(ActivityPackItem|ActivityPackContainerItem $item): void
    {
        if ($item->getQuantityWet() < 1 || $item->getWetHung() !== true) {
            return;
        }
        $addressId = trim((string) ($item->getWetDryingStorageAddressId() ?? ''));
        $label = trim((string) ($item->getWetDryingLocationLabel() ?? ''));
        if ($addressId === '' && $label === '') {
            throw new \InvalidArgumentException(
                'Trocknungsort erforderlich, wenn nasses Material bereits aufgehängt ist.',
            );
        }
    }

    private function maybeNotifyMw(
        Activity $activity,
        string $materialName,
        int $qtyWet,
        ?bool $hung,
        ?User $actor,
        int $prevWet = 0,
        ?bool $prevHung = null,
    ): void {
        if ($qtyWet < 1 || $hung === true || !$actor instanceof User) {
            return;
        }
        // Nur bei Übergang zu «nass + nicht aufgehängt» oder Mengenänderung — keine Spam-Duplikate.
        $wasNotifying = $prevWet >= 1 && $prevHung !== true;
        if ($wasNotifying && $prevWet === $qtyWet) {
            return;
        }
        $this->inboxMessageService->notifyMaterialWetNotHung($activity, $actor, $materialName, $qtyWet);
    }

    /**
     * Beim Abschluss: Cleaning-Tickets für alle Positionen mit quantity_wet > 0 ohne Ticket.
     *
     * @return list<WorkshopTicket>
     */
    public function ensureDryingTicketsOnComplete(Activity $activity, ?User $actor): array
    {
        $created = [];
        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        foreach ($packItems as $pi) {
            if (!$pi instanceof ActivityPackItem || $pi->getQuantityWet() < 1) {
                continue;
            }
            if ($pi->getWetWorkshopTicketId()) {
                continue;
            }
            $ticket = $this->createDryingTicket(
                $activity,
                $pi->getMaterialItem(),
                $pi->getQuantityWet(),
                $pi->getWetHung(),
                $pi->getWetDryingLocationLabel(),
                $actor,
                'pack_item',
                $pi->getId(),
            );
            if ($ticket) {
                $pi->setWetWorkshopTicketId($ticket->getId());
                $created[] = $ticket;
            }
        }

        $containers = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->findBy(['activityId' => $activity->getId()]);
        foreach ($containers as $container) {
            if (!$container instanceof ActivityPackContainer) {
                continue;
            }
            $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)
                ->findBy(['packContainerId' => $container->getId()]);
            foreach ($items as $ci) {
                if (!$ci instanceof ActivityPackContainerItem || $ci->getQuantityWet() < 1) {
                    continue;
                }
                if ($ci->getWetWorkshopTicketId()) {
                    continue;
                }
                $ticket = $this->createDryingTicket(
                    $activity,
                    $ci->getMaterialItem(),
                    $ci->getQuantityWet(),
                    $ci->getWetHung(),
                    $ci->getWetDryingLocationLabel(),
                    $actor,
                    'pack_container_item',
                    $ci->getId(),
                );
                if ($ticket) {
                    $ci->setWetWorkshopTicketId($ticket->getId());
                    $created[] = $ticket;
                }
            }
        }

        return $created;
    }

    private function createDryingTicket(
        Activity $activity,
        mixed $materialItem,
        int $qty,
        ?bool $hung,
        ?string $locationLabel,
        ?User $actor,
        string $sourceKind,
        string $sourceId,
    ): ?WorkshopTicket {
        if ($materialItem === null) {
            return null;
        }

        $ticket = new WorkshopTicket();
        $ticket->setId(IdGenerator::generate13('wt'));
        $ticket->setDepartment($activity->getDepartment());
        $ticket->setMaterialItem($materialItem);
        $ticket->setActivity($activity);
        $ticket->setType(WorkshopTicket::TYPE_CLEANING);
        $ticket->setPriority(WorkshopTicket::PRIORITY_NORMAL);
        $ticket->setAffectedQuantity($qty);
        $ticket->setTitle(sprintf('Trocknen / Einlagern: %s', $materialItem->getName()));
        $hungText = $hung === true
            ? ('bereits aufgehängt' . ($locationLabel ? ' — ' . $locationLabel : ''))
            : 'noch nicht aufgehängt — Trocknungsplatz wählen und danach einlagern';
        $ticket->setDescription(sprintf(
            "Aktivität «%s»: %d Stk. nass/feucht.\n%s\nQuelle: %s:%s",
            $activity->getName(),
            $qty,
            $hungText,
            $sourceKind,
            $sourceId,
        ));
        if ($actor instanceof User) {
            $ticket->setCreatedByUser($actor);
        }
        $this->entityManager->persist($ticket);

        $history = new WorkshopTicketHistory();
        $history->setId(IdGenerator::generate13('wh'));
        $history->setWorkshopTicket($ticket);
        $history->setAction(WorkshopTicketHistory::ACTION_AUTO_CREATED_ISSUE);
        $history->setSnapshot([
            'status' => $ticket->getStatus(),
            'type' => $ticket->getType(),
            'priority' => $ticket->getPriority(),
        ]);
        $history->setChanges([
            'source' => 'wet_drying',
            'activity_id' => $activity->getId(),
            'source_kind' => $sourceKind,
            'source_id' => $sourceId,
            'quantity_wet' => $qty,
            'wet_hung' => $hung,
        ]);
        if ($actor instanceof User) {
            $history->setUser($actor);
        }
        $this->entityManager->persist($history);

        return $ticket;
    }

    /** Noch offene nasse Menge für ein Cleaning-Ticket (über wet_workshop_ticket_id). */
    public function remainingWetForTicket(WorkshopTicket $ticket): int
    {
        if ($ticket->getType() !== WorkshopTicket::TYPE_CLEANING || !$ticket->getActivityId()) {
            return 0;
        }
        $sum = 0;
        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['wetWorkshopTicketId' => $ticket->getId()]);
        foreach ($packItems as $pi) {
            if ($pi instanceof ActivityPackItem) {
                $sum += $pi->getQuantityWet();
            }
        }
        $containerItems = $this->entityManager->getRepository(ActivityPackContainerItem::class)
            ->findBy(['wetWorkshopTicketId' => $ticket->getId()]);
        foreach ($containerItems as $ci) {
            if ($ci instanceof ActivityPackContainerItem) {
                $sum += $ci->getQuantityWet();
            }
        }

        return $sum;
    }

    public function assertCleaningTicketCompletable(WorkshopTicket $ticket): ?string
    {
        if ($ticket->getType() !== WorkshopTicket::TYPE_CLEANING) {
            return null;
        }
        $remaining = $this->remainingWetForTicket($ticket);
        if ($remaining > 0) {
            return sprintf(
                'Noch %d Stk. nass — zuerst aus der Trocknungs-Warteschlange einlagern, bevor das Ticket erledigt werden kann.',
                $remaining,
            );
        }

        return null;
    }

    public function storeFromWetPackItem(ActivityPackItem $item, int $qty): void
    {
        $this->packPipeline->applyStoreFromWet($item, $qty);
        $item->setUpdatedAt(new \DateTime());
    }

    public function storeFromWetContainerItem(ActivityPackContainerItem $item, int $qty): void
    {
        $this->packPipeline->applyStoreFromWetContainer($item, $qty);
        $item->touch();
    }

    /** @return array<string, mixed> */
    public static function serializeWetFields(ActivityPackItem|ActivityPackContainerItem $item): array
    {
        return [
            'quantity_wet' => $item->getQuantityWet(),
            'wet_hung' => $item->getWetHung(),
            'wet_drying_storage_address_id' => $item->getWetDryingStorageAddressId(),
            'wet_drying_rack_id' => $item->getWetDryingRackId(),
            'wet_drying_slot_id' => $item->getWetDryingSlotId(),
            'wet_drying_location_label' => $item->getWetDryingLocationLabel(),
            'wet_workshop_ticket_id' => $item->getWetWorkshopTicketId(),
        ];
    }
}
