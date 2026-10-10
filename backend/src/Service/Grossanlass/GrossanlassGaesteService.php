<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityJsOrder;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassGuestShare;
use App\Entity\DepartmentGrossanlassParticipant;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\GroupAccessService;
use App\Service\JsLeihkatalogCatalogService;
use App\Service\MaterialPeriodAvailability;
use App\Util\GrossanlassIdGenerator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassGaesteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassCommitmentService $commitments,
        private GrossanlassCostService $costs,
        private GroupAccessService $groupAccess,
        private JsLeihkatalogCatalogService $jsCatalog,
        private GrossanlassAvailabilityService $availability,
        private GrossanlassChargeMovementService $movements,
        private MaterialPeriodAvailability $stock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Department $host, User $user): array
    {
        $this->assertSee($host, $user);
        $participants = $this->acceptedGuests($host);
        $shares = $this->entityManager->getRepository(DepartmentGrossanlassGuestShare::class)
            ->findBy(['hostDepartmentId' => $host->getId()], ['createdAt' => 'DESC']);

        $byGuestItem = [];
        foreach ($shares as $share) {
            if ($share->getKind() !== DepartmentGrossanlassGuestShare::KIND_OFFER || !$share->getMaterialItemId()) {
                continue;
            }
            $byGuestItem[$share->getGuestDepartmentId() . ':' . $share->getMaterialItemId()] = $share;
        }

        $departments = [];
        foreach ($participants as $row) {
            $guest = $row->getGuestDepartment();
            $items = [];
            foreach ($this->guestMaterials($guest->getId()) as $material) {
                $key = $guest->getId() . ':' . $material->getId();
                $share = $byGuestItem[$key] ?? null;
                $items[] = [
                    'id' => $material->getId(),
                    'name' => $material->getName(),
                    'qty' => max(0, $material->getTotalStock()),
                    'family' => DepartmentGrossanlassCommitment::FAMILY_MATERIAL,
                    'bookable' => false,
                    'share_id' => $share?->getId(),
                    'share_status' => $share?->getStatus(),
                ];
            }
            $departments[] = [
                'id' => $guest->getId(),
                'name' => $guest->getName(),
                'status' => $row->getStatus(),
                'items' => $items,
            ];
        }

        $offers = [];
        $sales = [];
        foreach ($shares as $share) {
            $payload = $this->serializeShare($share);
            if ($share->getKind() === DepartmentGrossanlassGuestShare::KIND_SALE) {
                $sales[] = $payload;
            } else {
                $offers[] = $payload;
            }
        }

        $saleStock = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findBy(['departmentId' => $host->getId()], ['name' => 'ASC']) as $row) {
            if (!in_array($row->getOrigin(), [
                DepartmentGrossanlassCommitment::ORIGIN_BUY,
                DepartmentGrossanlassCommitment::ORIGIN_BUY_RESALE,
            ], true)) {
                continue;
            }
            $saleStock[] = [
                'id' => $row->getId(),
                'name' => $row->getName(),
                'qty' => max(1, $row->getQuantity()),
                'origin' => $row->getOrigin(),
                'source' => $row->getSource(),
                'inquiry_id' => $row->getInquiryId(),
            ];
        }

        return [
            'departments' => $departments,
            'offers' => $offers,
            'sales' => $sales,
            'sale_stock' => $saleStock,
            'js' => $this->jsOverview($participants),
        ];
    }

    /**
     * Gast-Abteilung gibt eigenes Campmaterial für den Grossanlass frei.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function releaseAsGuest(Department $guest, User $user, string $hostId, array $data): array
    {
        $this->assertGuestManager($guest, $user);
        $host = $this->entityManager->getRepository(Department::class)->find($hostId);
        if (!$host instanceof Department || !$host->isGrossanlass()) {
            throw new \InvalidArgumentException('Grossanlass nicht gefunden');
        }
        $this->requireAcceptedGuest($host, $guest->getId());
        $materialId = trim((string) ($data['material_item_id'] ?? ''));
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
        if (!$material instanceof MaterialItem || $material->getDepartmentId() !== $guest->getId() || $material->getDeletedAt() !== null) {
            throw new \InvalidArgumentException('Artikel nicht im eigenen Campmaterial');
        }

        $from = $this->parseDate($data['from'] ?? null);
        $to = $this->parseDate($data['to'] ?? null);
        $this->assertWindow($from, $to);

        $this->availability->transactional(function () use ($host, $guest, $material, $data, $from, $to): void {
            $existing = $this->entityManager->getRepository(DepartmentGrossanlassGuestShare::class)->findOneBy([
                'hostDepartmentId' => $host->getId(),
                'guestDepartmentId' => $guest->getId(),
                'materialItemId' => $material->getId(),
                'kind' => DepartmentGrossanlassGuestShare::KIND_OFFER,
            ]);
            if ($existing instanceof DepartmentGrossanlassGuestShare && $existing->getStatus() !== DepartmentGrossanlassGuestShare::STATUS_DECLINED) {
                throw new \InvalidArgumentException('Dieser Artikel ist für den Anlass bereits freigegeben');
            }

            $qty = max(1, (int) ($data['qty'] ?? $material->getTotalStock() ?: 1));
            $this->assertGuestStock($material, $qty, $from, $to, $existing?->getId(), true);

            $row = $existing instanceof DepartmentGrossanlassGuestShare ? $existing : new DepartmentGrossanlassGuestShare();
            if ($existing === null) {
                $row->setId(GrossanlassIdGenerator::unique(
                    $this->entityManager,
                    GrossanlassIdGenerator::GUEST_SHARE,
                    DepartmentGrossanlassGuestShare::class,
                ));
                $this->entityManager->persist($row);
            }
            $row->setHostDepartment($host);
            $row->setGuestDepartment($guest);
            $row->setKind(DepartmentGrossanlassGuestShare::KIND_OFFER);
            $row->setStatus(DepartmentGrossanlassGuestShare::STATUS_OFFERED);
            $row->setName($material->getName());
            $row->setQty($qty);
            $row->setFamily(DepartmentGrossanlassCommitment::FAMILY_MATERIAL);
            $row->setMaterialItemId($material->getId());
            $row->setStartsAt($from);
            $row->setEndsAt($to);
            $this->entityManager->flush();
        });

        return $this->guestCatalog($guest, $user, $host->getId());
    }

    /**
     * @return array<string, mixed>
     */
    public function guestCatalog(Department $guest, User $user, string $hostId): array
    {
        $this->assertGuestManager($guest, $user);
        $host = $this->entityManager->getRepository(Department::class)->find($hostId);
        if (!$host instanceof Department || !$host->isGrossanlass()) {
            throw new \InvalidArgumentException('Grossanlass nicht gefunden');
        }
        $this->requireGuest($host, $guest->getId());
        $shares = $this->entityManager->getRepository(DepartmentGrossanlassGuestShare::class)->findBy([
            'hostDepartmentId' => $host->getId(),
            'guestDepartmentId' => $guest->getId(),
            'kind' => DepartmentGrossanlassGuestShare::KIND_OFFER,
        ]);
        $byItem = [];
        foreach ($shares as $share) {
            if ($share->getMaterialItemId()) {
                $byItem[$share->getMaterialItemId()] = $share;
            }
        }
        $items = [];
        $materials = $this->guestMaterials($guest->getId());
        $free = $this->stock->availableForIds(array_map(static fn (MaterialItem $m): string => $m->getId(), $materials));
        foreach ($materials as $material) {
            $share = $byItem[$material->getId()] ?? null;
            $items[] = [
                'id' => $material->getId(),
                'name' => $material->getName(),
                'qty' => max(0, $material->getTotalStock()),
                'free_qty' => max(0, $free[$material->getId()] ?? 0),
                'released' => $share !== null && $share->getStatus() !== DepartmentGrossanlassGuestShare::STATUS_DECLINED,
                'share_id' => $share?->getId(),
                'share_status' => $share?->getStatus(),
            ];
        }

        return [
            'host_id' => $host->getId(),
            'host_name' => $host->getName(),
            'items' => $items,
            'releases' => array_map(fn (DepartmentGrossanlassGuestShare $row) => $this->serializeShare($row), $shares),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(Department $host, User $user, string $id): array
    {
        $this->assertManage($host, $user);
        $row = $this->findShare($host, $id);
        if ($row->getKind() !== DepartmentGrossanlassGuestShare::KIND_OFFER) {
            throw new \InvalidArgumentException('Nur Leihe kann übernommen werden');
        }
        if ($row->getStatus() === DepartmentGrossanlassGuestShare::STATUS_DECLINED) {
            throw new \InvalidArgumentException('Die Freigabe wurde vom Gast-Department zurückgezogen');
        }
        if ($row->getStatus() === DepartmentGrossanlassGuestShare::STATUS_ACCEPTED && $row->getCommitment() !== null) {
            return $this->overview($host, $user);
        }

        $this->availability->transactional(function () use ($host, $user, $row): void {
            // Erst jetzt blockiert die Zusage das Material: unter der Sperre erneut gegen Aktivitäten und andere Anlässe prüfen.
            $material = $row->getMaterialItemId() !== null
                ? $this->entityManager->getRepository(MaterialItem::class)->find($row->getMaterialItemId())
                : null;
            if ($material instanceof MaterialItem) {
                if ($material->getDeletedAt() !== null) {
                    throw new \InvalidArgumentException('Der Artikel existiert im Gast-Department nicht mehr');
                }
                $this->assertGuestStock($material, $row->getQty(), $row->getStartsAt(), $row->getEndsAt(), $row->getId(), false);
            }
            $row->setStatus(DepartmentGrossanlassGuestShare::STATUS_ACCEPTED);
            if ($row->getCommitment() === null) {
                $commitment = $this->commitments->create($host, $user, [
                    'name' => $row->getName(),
                    'source' => $row->getGuestDepartment()->getName(),
                    'family' => $row->getFamily(),
                    'origin' => DepartmentGrossanlassCommitment::ORIGIN_LOAN,
                    'return_required' => true,
                    'owner_kind' => DepartmentGrossanlassCommitment::OWNER_DEPARTMENT,
                    'owner_department_id' => $row->getGuestDepartmentId(),
                    'quantity' => $row->getQty(),
                    'present_from' => $row->getStartsAt()?->format(\DateTimeInterface::ATOM),
                    'present_to' => $row->getEndsAt()?->format(\DateTimeInterface::ATOM),
                    'released' => true,
                ]);
                $entity = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->find($commitment['id']);
                if ($entity instanceof DepartmentGrossanlassCommitment) {
                    $row->setCommitment($entity);
                }
            }
            $this->entityManager->flush();
        });

        return $this->overview($host, $user);
    }

    /**
     * Das Gast-Department ändert seine eigene Freigabe (Menge, Zeitfenster). Nach der Annahme nur die Menge: Erhöhen
     * wird gegen den Bestand geprüft, Verringern nie unter erhaltene oder gebuchte Mengen.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateRelease(Department $guest, User $user, string $hostId, string $shareId, array $data): array
    {
        $this->assertGuestManager($guest, $user);
        $this->availability->transactional(function () use ($guest, $hostId, $shareId, $data): void {
            $row = $this->findOwnShare($guest, $hostId, $shareId);
            $material = $this->materialOf($row);
            $qty = array_key_exists('qty', $data) ? max(1, (int) $data['qty']) : $row->getQty();
            $accepted = $row->getStatus() === DepartmentGrossanlassGuestShare::STATUS_ACCEPTED;

            if ($accepted) {
                if (array_key_exists('from', $data) || array_key_exists('to', $data)) {
                    throw new \InvalidArgumentException('Das Zeitfenster einer angenommenen Freigabe lässt sich nicht ändern. Zurückziehen und neu freigeben.');
                }
                $commitment = $row->getCommitment();
                if ($qty > $row->getQty()) {
                    $this->assertGuestStock($material, $qty, $row->getStartsAt(), $row->getEndsAt(), $row->getId(), false);
                }
                if ($commitment instanceof DepartmentGrossanlassCommitment && $qty !== $commitment->getQuantity()) {
                    $this->availability->lock($commitment);
                    if ($qty < $commitment->getQuantity()) {
                        $received = $this->movements->receivedQuantity($commitment);
                        if ($qty < $received) {
                            throw new GrossanlassAvailabilityConflict(
                                sprintf('%s: %d bereits erhalten, die Menge kann nicht auf %d sinken. Die Rückgabe wird im Grossanlass gebucht.', $row->getName(), $received, $qty),
                                GrossanlassAvailabilityConflict::KIND_IN_USE,
                                ['commitment_id' => $commitment->getId(), 'received' => $received],
                            );
                        }
                        $this->availability->assertQuantityCoversBookings($commitment, $qty);
                    }
                    $commitment->setQuantity($qty);
                }
            } else {
                $from = array_key_exists('from', $data) ? $this->parseDate($data['from']) : $row->getStartsAt();
                $to = array_key_exists('to', $data) ? $this->parseDate($data['to']) : $row->getEndsAt();
                $this->assertWindow($from, $to);
                $this->assertGuestStock($material, $qty, $from, $to, $row->getId(), true);
                $row->setStartsAt($from);
                $row->setEndsAt($to);
            }
            $row->setQty($qty);
            $this->entityManager->flush();
        });

        return $this->guestCatalog($guest, $user, $hostId);
    }

    /**
     * Das Gast-Department zieht seine Freigabe zurück. Angenommen: nur solange die Charge im Grossanlass ungenutzt ist;
     * danach läuft die Rückgabe im Grossanlass. Das Material ist im Gast-Department sofort wieder frei.
     *
     * @return array<string, mixed>
     */
    public function withdrawRelease(Department $guest, User $user, string $hostId, string $shareId): array
    {
        $this->assertGuestManager($guest, $user);
        $this->availability->transactional(function () use ($guest, $hostId, $shareId): void {
            $row = $this->findOwnShare($guest, $hostId, $shareId);
            if ($row->getStatus() === DepartmentGrossanlassGuestShare::STATUS_DECLINED) {
                return; // idempotent
            }
            $commitment = $row->getCommitment();
            if ($row->getStatus() === DepartmentGrossanlassGuestShare::STATUS_ACCEPTED && $commitment instanceof DepartmentGrossanlassCommitment) {
                $row->setCommitment(null);
                $this->entityManager->flush();
                $this->commitments->removeUnusedGuestCharge($commitment);
            }
            $row->setStatus(DepartmentGrossanlassGuestShare::STATUS_DECLINED);
            $this->entityManager->flush();
        });

        return $this->guestCatalog($guest, $user, $hostId);
    }

    /**
     * Gast-Material nur zusagen, soweit es im Zeitfenster tatsächlich frei ist: Bestand minus Aktivitäten (Bestellung und
     * Pipeline), minus angenommene Zusagen für andere Anlässe. Bei neuen Angeboten zählen zusätzlich offene Angebote mit, damit
     * dasselbe Material nicht doppelt angeboten wird. Sperrt den Artikel (nur in einer Transaktion aufrufen).
     */
    private function assertGuestStock(
        MaterialItem $material,
        int $qty,
        ?\DateTime $from,
        ?\DateTime $to,
        ?string $excludeShareId,
        bool $countOffers,
    ): void {
        $this->entityManager->lock($material, LockMode::PESSIMISTIC_WRITE);
        // Ohne Fenster gilt das Material als unbegrenzt zugesagt: gegen jede Aktivität prüfen
        $windowFrom = $from ?? new \DateTime('1970-01-01');
        $windowTo = $to ?? new \DateTime('2100-01-01');
        $free = $this->stock->availableForIds([$material->getId()], $windowFrom, $windowTo, '', $excludeShareId ?? '')[$material->getId()] ?? 0;
        if ($countOffers) {
            foreach ($this->entityManager->getRepository(DepartmentGrossanlassGuestShare::class)->findBy([
                'materialItemId' => $material->getId(),
                'kind' => DepartmentGrossanlassGuestShare::KIND_OFFER,
                'status' => DepartmentGrossanlassGuestShare::STATUS_OFFERED,
            ]) as $other) {
                if ($other->getId() === $excludeShareId) {
                    continue;
                }
                $oFrom = $other->getStartsAt();
                $oTo = $other->getEndsAt();
                if (($oFrom === null || $oFrom < $windowTo) && ($oTo === null || $oTo > $windowFrom)) {
                    $free -= $other->getQty();
                }
            }
        }
        if ($qty <= max(0, $free)) {
            return;
        }

        throw new GrossanlassAvailabilityConflict(
            sprintf(
                '%s: im Zeitfenster nur %d verfügbar (Bestand abzüglich Aktivitäten und anderer Zusagen) — %d lassen sich nicht zusagen.',
                $material->getName(),
                max(0, $free),
                $qty,
            ),
            GrossanlassAvailabilityConflict::KIND_GUEST_STOCK,
            ['material_item_id' => $material->getId(), 'material_name' => $material->getName(), 'available' => max(0, $free), 'requested' => $qty],
        );
    }

    private function assertWindow(?\DateTime $from, ?\DateTime $to): void
    {
        if ($from !== null && $to !== null && $to <= $from) {
            throw new \InvalidArgumentException('Das Ende muss nach dem Beginn liegen');
        }
    }

    private function materialOf(DepartmentGrossanlassGuestShare $row): MaterialItem
    {
        $material = $row->getMaterialItemId() !== null
            ? $this->entityManager->getRepository(MaterialItem::class)->find($row->getMaterialItemId())
            : null;
        if (!$material instanceof MaterialItem || $material->getDeletedAt() !== null) {
            throw new \InvalidArgumentException('Artikel nicht im eigenen Campmaterial');
        }

        return $material;
    }

    /** Nur die eigene Freigabe dieses Gast-Departments für diesen Anlass. */
    private function findOwnShare(Department $guest, string $hostId, string $shareId): DepartmentGrossanlassGuestShare
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassGuestShare::class)->find($shareId);
        if (!$row instanceof DepartmentGrossanlassGuestShare
            || $row->getGuestDepartmentId() !== $guest->getId()
            || $row->getHostDepartmentId() !== $hostId
            || $row->getKind() !== DepartmentGrossanlassGuestShare::KIND_OFFER
        ) {
            throw new \InvalidArgumentException('Freigabe nicht gefunden');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sell(Department $host, User $user, array $data): array
    {
        $this->assertManage($host, $user);
        $guest = $this->requireGuest($host, trim((string) ($data['guest_department_id'] ?? '')));
        $commitmentId = trim((string) ($data['commitment_id'] ?? ''));
        $commitment = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->find($commitmentId);
        if (!$commitment instanceof DepartmentGrossanlassCommitment || $commitment->getDepartmentId() !== $host->getId()) {
            throw new \InvalidArgumentException('Zusage nicht gefunden');
        }
        if (!in_array($commitment->getOrigin(), [
            DepartmentGrossanlassCommitment::ORIGIN_BUY,
            DepartmentGrossanlassCommitment::ORIGIN_BUY_RESALE,
        ], true)) {
            throw new \InvalidArgumentException('Weiterverkauf nur aus Firmenanfragen oder selbst angeschafftem Material');
        }

        $row = new DepartmentGrossanlassGuestShare();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::GUEST_SHARE,
            DepartmentGrossanlassGuestShare::class,
        ));
        $row->setHostDepartment($host);
        $row->setGuestDepartment($guest);
        $row->setKind(DepartmentGrossanlassGuestShare::KIND_SALE);
        $row->setStatus(DepartmentGrossanlassGuestShare::STATUS_COMPLETED);
        $row->setName($commitment->getName());
        $row->setQty(max(1, (int) ($data['qty'] ?? 1)));
        $row->setFamily($commitment->getFamily());
        $row->setCommitment($commitment);
        $this->entityManager->persist($row);
        if (array_key_exists('amount_chf', $data)) {
            $this->costs->recordGuestSaleProceeds($commitment, $data['amount_chf']);
        }
        $this->entityManager->flush();

        return $this->overview($host, $user);
    }

    /**
     * J+S-Leihkatalog (dept_js00000 / Lagersport & Trekking) plus Mengen der Gast-Aktivitäten.
     *
     * @param list<DepartmentGrossanlassParticipant> $participants
     * @return array{catalog_name: string|null, articles: list<array<string, mixed>>}
     */
    private function jsOverview(array $participants): array
    {
        $category = $this->jsCatalog->findOrderFormCategory();
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MaterialItem::class, 'm');
        $this->jsCatalog->applyOrderFormFilters($qb, 'm');
        $this->jsCatalog->applyOrderFormSort($qb, 'm');

        $ordersByActivity = $this->guestJsOrdersByActivity($participants);

        $articles = [];
        foreach ($qb->getQuery()->getResult() as $material) {
            if (!$material instanceof MaterialItem) {
                continue;
            }
            $lines = [];
            foreach ($participants as $row) {
                $guest = $row->getGuestDepartment();
                $order = $ordersByActivity[$row->getGuestActivityId() ?? ''] ?? null;
                $qty = 0;
                if ($order instanceof ActivityJsOrder) {
                    foreach ($order->getItems() as $item) {
                        if ($item->getMaterialItemId() === $material->getId()) {
                            $qty = max(0, $item->getQuantityOrdered());
                            break;
                        }
                    }
                }
                $lines[] = [
                    'department_id' => $guest->getId(),
                    'department_name' => $guest->getName(),
                    'qty' => $qty,
                    'status' => $this->jsOrderSubmitted($order) ? 'submitted' : 'missing',
                ];
            }
            $articles[] = [
                'id' => $material->getId(),
                'name' => $material->getName(),
                'unit' => $material->getPackUnit() ?: 'Stk.',
                'catalog_hint' => $material->getDescription() ?: null,
                'pdf_line_no' => $material->getNo(),
                'lines' => $lines,
            ];
        }

        return [
            'catalog_name' => $category?->getName(),
            'articles' => $articles,
        ];
    }

    /**
     * @param list<DepartmentGrossanlassParticipant> $participants
     * @return array<string, ActivityJsOrder>
     */
    private function guestJsOrdersByActivity(array $participants): array
    {
        $activityIds = [];
        foreach ($participants as $row) {
            $id = $row->getGuestActivityId();
            if ($id !== null && $id !== '') {
                $activityIds[] = $id;
            }
        }
        if ($activityIds === []) {
            return [];
        }

        $found = $this->entityManager->createQueryBuilder()
            ->select('o', 'i')
            ->from(ActivityJsOrder::class, 'o')
            ->leftJoin('o.items', 'i')
            ->where('o.activityId IN (:ids)')
            ->andWhere('o.status != :cancelled')
            ->setParameter('ids', array_values(array_unique($activityIds)))
            ->setParameter('cancelled', ActivityJsOrder::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();

        $byActivity = [];
        foreach ($found as $order) {
            if ($order instanceof ActivityJsOrder) {
                $byActivity[$order->getActivityId()] = $order;
            }
        }

        return $byActivity;
    }

    private function jsOrderSubmitted(?ActivityJsOrder $order): bool
    {
        if (!$order instanceof ActivityJsOrder) {
            return false;
        }
        if ($order->getSubmittedToCoachAt() !== null) {
            return true;
        }

        return in_array($order->getStatus(), [
            ActivityJsOrder::STATUS_READY,
            ActivityJsOrder::STATUS_ORDERED,
            ActivityJsOrder::STATUS_FULFILLED,
        ], true);
    }

    /**
     * @return list<DepartmentGrossanlassParticipant>
     */
    private function acceptedGuests(Department $host): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassParticipant::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.guestDepartment', 'g')
            ->addSelect('g')
            ->leftJoin('p.guestActivity', 'a')
            ->addSelect('a')
            ->where('p.hostDepartmentId = :id')
            ->andWhere('p.status IN (:status)')
            ->setParameter('id', $host->getId())
            ->setParameter('status', [
                DepartmentGrossanlassParticipant::STATUS_ACCEPTED,
                DepartmentGrossanlassParticipant::STATUS_PENDING,
                DepartmentGrossanlassParticipant::STATUS_PLANNED,
            ])
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<MaterialItem>
     */
    private function guestMaterials(string $guestDepartmentId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MaterialItem::class, 'm')
            ->where('m.departmentId = :id')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.isJsMaterial = false')
            ->setParameter('id', $guestDepartmentId)
            ->orderBy('m.name', 'ASC')
            ->setMaxResults(120)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShare(DepartmentGrossanlassGuestShare $row): array
    {
        $from = $row->getStartsAt();
        $to = $row->getEndsAt();

        return [
            'id' => $row->getId(),
            'kind' => $row->getKind(),
            'status' => $row->getStatus(),
            'guest_department_id' => $row->getGuestDepartmentId(),
            'guest_name' => $row->getGuestDepartment()->getName(),
            'name' => $row->getName(),
            'qty' => $row->getQty(),
            'family' => $row->getFamily(),
            'material_item_id' => $row->getMaterialItemId(),
            'commitment_id' => $row->getCommitmentId(),
            'bookable' => $row->getKind() === DepartmentGrossanlassGuestShare::KIND_OFFER
                && $row->getStatus() === DepartmentGrossanlassGuestShare::STATUS_ACCEPTED
                && $row->getCommitmentId() !== null,
            'from' => $from?->format(\DateTimeInterface::ATOM),
            'to' => $to?->format(\DateTimeInterface::ATOM),
            'from_label' => $from?->format('d.m.y') ?? '',
            'to_label' => $to?->format('d.m.y') ?? '',
        ];
    }

    private function requireGuest(Department $host, string $guestId): Department
    {
        if ($guestId === '') {
            throw new \InvalidArgumentException('Gast-Abteilung ist erforderlich');
        }
        foreach ($this->acceptedGuests($host) as $row) {
            if ($row->getGuestDepartmentId() === $guestId) {
                return $row->getGuestDepartment();
            }
        }
        throw new \InvalidArgumentException('Abteilung ist kein Gast dieses Anlasses');
    }

    private function requireAcceptedGuest(Department $host, string $guestId): Department
    {
        foreach ($this->acceptedGuests($host) as $row) {
            if ($row->getGuestDepartmentId() === $guestId
                && $row->getStatus() === DepartmentGrossanlassParticipant::STATUS_ACCEPTED
            ) {
                return $row->getGuestDepartment();
            }
        }
        throw new \InvalidArgumentException('Einladung muss angenommen sein, bevor Campmaterial freigegeben wird');
    }

    private function findShare(Department $host, string $id): DepartmentGrossanlassGuestShare
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassGuestShare::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassGuestShare || $row->getHostDepartmentId() !== $host->getId()) {
            throw new \InvalidArgumentException('Eintrag nicht gefunden');
        }

        return $row;
    }

    private function parseDate(mixed $value): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTime((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültiges Datum');
        }
    }

    private function assertSee(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (
            !$this->access->canSeeMaterialUebersicht($user, $department)
            && !$this->access->canManagePlanung($user, $department)
        ) {
            throw new \RuntimeException('Keine Berechtigung für Gäste-Material');
        }
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Gäste-Material');
        }
    }

    private function assertGuestManager(Department $guest, User $user): void
    {
        if ($guest->isGrossanlass()) {
            throw new \InvalidArgumentException('Freigabe nur aus der Gast-Abteilung');
        }
        if (!$this->groupAccess->canFullyManageDepartmentGroups($user, $guest->getId())) {
            throw new \RuntimeException('Keine Berechtigung, Campmaterial freizugeben');
        }
    }
}
