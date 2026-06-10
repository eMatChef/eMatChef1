<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\AccountingCostCenter;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\MaterialItem;
use App\Service\Accounting\AccountingAcquisitionFollowUpReceiptService;
use App\Service\Accounting\AccountingBookingReceiptStorageService;
use App\Service\Accounting\AccountingBookingSourceService;
use App\Service\InboxMessageService;
use App\Service\Media\MediaPhotoNormalizer;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\DBAL\ParameterType;

#[Route('/api/departments/{departmentId}/accounting/bookings', name: 'api_accounting_bookings_')]
class AccountingBookingController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
        private AccountingBookingSourceService $bookingSource,
        private AccountingBookingReceiptStorageService $receiptStorage,
        private AccountingAcquisitionFollowUpReceiptService $followUpReceiptService,
        private MediaPhotoNormalizer $photoNormalizer,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $deptRef = $this->entityManager->getReference(Department::class, $departmentId);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(AccountingBooking::class, 'b')
            ->innerJoin('b.costCenter', 'cc')
            ->leftJoin('b.group', 'g')
            ->where('b.department = :d')
            ->setParameter('d', $deptRef)
            ->orderBy('b.bookedAt', 'DESC')
            ->addOrderBy('b.id', 'DESC');

        $year = trim((string) $request->query->get('year', ''));
        if ($year !== '' && preg_match('/^\d{4}$/', $year)) {
            $qb->andWhere('b.bookedAt >= :yStart AND b.bookedAt <= :yEnd')
                ->setParameter('yStart', new \DateTimeImmutable($year.'-01-01'))
                ->setParameter('yEnd', new \DateTimeImmutable($year.'-12-31'));
        }

        $costCenterId = trim((string) $request->query->get('cost_center_id', ''));
        if ($costCenterId !== '') {
            $qb->andWhere('cc.id = :ccId')
                ->setParameter('ccId', $costCenterId);
        }

        $rows = $qb->getQuery()->getResult();

        $sourceMap = $this->bookingSource->sourceMapForBookings($rows);

        $out = [];
        foreach ($rows as $b) {
            $out[] = $this->serialize($b, $sourceMap[$b->getId() ?? ''] ?? null);
        }

        return new JsonResponse($out);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(string $departmentId, Request $request): Response
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $yearParam = trim((string) $request->query->get('year', ''));
        if ($yearParam === '' || !preg_match('/^\d{4}$/', $yearParam)) {
            return new JsonResponse(['error' => 'Query year (YYYY) erforderlich'], 400);
        }
        $year = (int) $yearParam;

        $deptRef = $this->entityManager->getReference(Department::class, $departmentId);
        $qb = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(AccountingBooking::class, 'b')
            ->innerJoin('b.costCenter', 'cc')
            ->leftJoin('b.group', 'g')
            ->where('b.department = :d')
            ->andWhere('b.bookedAt >= :yStart AND b.bookedAt <= :yEnd')
            ->setParameter('d', $deptRef)
            ->setParameter('yStart', new \DateTimeImmutable($year.'-01-01'))
            ->setParameter('yEnd', new \DateTimeImmutable($year.'-12-31'))
            ->orderBy('b.bookedAt', 'ASC')
            ->addOrderBy('b.id', 'ASC');

        $rows = $qb->getQuery()->getResult();
        $sourceMap = $this->bookingSource->sourceMapForBookings($rows);

        $filename = sprintf('buchungen-%s-%d.csv', $departmentId, $year);

        return new StreamedResponse(function () use ($rows, $sourceMap, $year): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID', 'Datum', 'Betrag CHF', 'Typ', 'Zahlungsart', 'Zahlungsstatus',
                'Kostenstelle', 'Kontocode', 'Gruppe', 'Material', 'Beleg', 'Notizen',
                'Quelle', 'Aktivität-ID', 'Batch-ID',
            ], ';');
            foreach ($rows as $b) {
                if (!$b instanceof AccountingBooking) {
                    continue;
                }
                $src = $sourceMap[$b->getId() ?? ''] ?? null;
                $cc = $b->getCostCenter();
                $g = $b->getGroup();
                $mi = $b->getMaterialItem();
                fputcsv($out, [
                    $b->getId(),
                    $b->getBookedAt()->format('Y-m-d'),
                    $b->getAmount(),
                    $b->getEntryType(),
                    $b->getPaymentMethod() ?? '',
                    $b->getPaymentStatus(),
                    $cc->getName(),
                    $cc->getAccountCode() ?? '',
                    $g?->getName() ?? '',
                    $mi?->getName() ?? '',
                    $b->getReceiptLabel() ?? '',
                    $b->getNotes() ?? '',
                    is_array($src) ? ($src['source_kind'] ?? '') : '',
                    is_array($src) ? ($src['activity_id'] ?? '') : '',
                    is_array($src) ? ($src['material_batch_id'] ?? '') : '',
                ], ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Kalenderjahre, in denen für dieses Department mindestens eine Buchung existiert (absteigend).
     */
    #[Route('/years', name: 'years', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function bookingYears(string $departmentId): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $conn = $this->entityManager->getConnection();
        $sql = <<<'SQL'
            SELECT DISTINCT EXTRACT(YEAR FROM booked_at)::int AS y
            FROM accounting_booking
            WHERE department_id = :d
            ORDER BY y DESC
            SQL;
        $rows = $conn->executeQuery($sql, ['d' => $departmentId], ['d' => ParameterType::STRING])
            ->fetchFirstColumn();

        $years = array_map(static fn ($v) => (int) $v, $rows);

        return new JsonResponse(['years' => $years]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $followUpId = trim((string) ($data['acquisition_follow_up_id'] ?? ''));
        $followUp = null;
        if ($followUpId !== '') {
            try {
                $followUp = $this->entityManager->find(AccountingAcquisitionFollowUp::class, $followUpId);
            } catch (\Throwable) {
                return new JsonResponse([
                    'error' => 'Anschaffungs-Aufträge sind in der Datenbank noch nicht eingerichtet (Migration accounting_acquisition_follow_up).',
                ], 503);
            }
            if (!$followUp || $followUp->getDepartment()->getId() !== $departmentId) {
                return new JsonResponse(['error' => 'Anschaffungs-Auftrag nicht gefunden'], 404);
            }
            if ($followUp->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
                return new JsonResponse(['error' => 'Anschaffungs-Auftrag ist bereits erfasst'], 400);
            }
        }

        $parse = $this->parseCreatePayload($data, $departmentId);
        if ($parse instanceof JsonResponse) {
            return $parse;
        }

        $bookedAt = $parse['bookedAt'];
        $booking = new AccountingBooking();
        $booking->setId(IdGenerator::generate13UniqueForYear(
            $this->entityManager,
            AccountingBooking::class,
            'kb',
            $bookedAt->format('Y')
        ));
        $booking->setDepartment($dept);
        $booking->setCostCenter($parse['costCenter']);
        $booking->setAmount($parse['amount']);
        $booking->setBookedAt($bookedAt);
        $booking->setEntryType($parse['entryType']);
        $booking->setPaymentMethod($parse['paymentMethod']);
        $paymentStatus = $parse['paymentStatus'];
        if (($data['payment_status'] ?? '') === '' && $followUp !== null) {
            $paymentStatus = $this->defaultPaymentStatusForFollowUp($followUp);
        }
        $booking->setPaymentStatus($paymentStatus);
        $booking->setGroup($parse['group']);
        $booking->setReceiptLabel($parse['receiptLabel']);
        $booking->setNotes($parse['notes']);

        $materialItem = $this->resolveMaterialItemFromRequest($data, $departmentId);
        if ($materialItem instanceof JsonResponse) {
            return $materialItem;
        }
        if ($materialItem !== null) {
            $booking->setMaterialItem($materialItem);
        } elseif ($followUp !== null && $followUp->getMaterialItem() !== null) {
            $booking->setMaterialItem($followUp->getMaterialItem());
        } elseif ($followUp !== null && $followUp->getMaterialBatch() !== null) {
            $booking->setMaterialItem($followUp->getMaterialBatch()->getMaterialItem());
        }

        $this->entityManager->persist($booking);

        if ($followUp !== null) {
            try {
                $this->followUpReceiptService->transferReceiptsToBooking($followUp, $booking);
                $followUp->setAccountingBooking($booking);
                $followUp->setStatus(AccountingAcquisitionFollowUp::STATUS_RECORDED);
                $followUp->touchUpdatedAt();
            } catch (\Throwable) {
                // Buchung ist gespeichert; Verknüpfung/Belege fehlgeschlagen (z. B. Schema)
            }
        }

        $this->entityManager->flush();

        if ($followUp !== null) {
            try {
                $this->inboxMessages->removeAccountingFollowUpInbox($followUp->getId());
            } catch (\Throwable) {
            }
        }

        return new JsonResponse($this->serialize($booking, $this->sourceForBooking($booking)), 201);
    }

    private function defaultPaymentStatusForFollowUp(?AccountingAcquisitionFollowUp $followUp): string
    {
        if ($followUp === null) {
            return AccountingBooking::PAYMENT_STATUS_PAID;
        }
        $activity = $followUp->getActivity();
        if ($activity !== null && $activity->getType() === 'external') {
            return AccountingBooking::PAYMENT_STATUS_OPEN;
        }
        if ($followUp->getSourceKind() === AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL) {
            return AccountingBooking::PAYMENT_STATUS_OPEN;
        }

        return AccountingBooking::PAYMENT_STATUS_PAID;
    }

    /**
     * Buchung bearbeiten (booked_at ist NICHT änderbar – Jahr steckt in der ID, wie bei Material-Batches).
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $id, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $booking = $this->entityManager->find(AccountingBooking::class, $id);
        if (!$booking || $booking->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Buchung nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('amount', $data)) {
            $amt = $this->normalizeAmount($data['amount']);
            if ($amt === null) {
                return new JsonResponse(['error' => 'Ungültiger Betrag'], 400);
            }
            $booking->setAmount($amt);
        }
        if (array_key_exists('booked_at', $data)) {
            $incoming = trim((string) $data['booked_at']);
            if ($incoming !== '' && $incoming !== $booking->getBookedAt()->format('Y-m-d')) {
                return new JsonResponse(['error' => 'Buchungsdatum ist nach dem Erfassen nicht änderbar (Jahr steckt in der ID).'], 400);
            }
        }
        if (array_key_exists('cost_center_id', $data)) {
            $cc = $this->resolveCostCenter((string) $data['cost_center_id'], $departmentId);
            if ($cc instanceof JsonResponse) {
                return $cc;
            }
            $booking->setCostCenter($cc);
        }
        if (array_key_exists('entry_type', $data)) {
            $t = (string) $data['entry_type'];
            if (!in_array($t, AccountingBooking::ENTRY_TYPES, true)) {
                return new JsonResponse(['error' => 'Ungültiger Buchungstyp'], 400);
            }
            $booking->setEntryType($t);
        }
        if (array_key_exists('payment_method', $data)) {
            $pmRaw = $data['payment_method'];
            $pm = $this->normalizePaymentMethod($pmRaw);
            if ($pmRaw !== null && $pmRaw !== '' && $pm === null) {
                return new JsonResponse(['error' => 'Ungültige Zahlungsart'], 400);
            }
            $booking->setPaymentMethod($pm);
        }
        if (array_key_exists('payment_status', $data)) {
            $ps = $this->normalizePaymentStatus($data['payment_status']);
            if ($ps === null) {
                return new JsonResponse(['error' => 'Ungültiger Zahlungsstatus'], 400);
            }
            $booking->setPaymentStatus($ps);
        }
        if (array_key_exists('group_id', $data)) {
            $g = $this->resolveGroup($data['group_id'], $departmentId);
            if ($g instanceof JsonResponse) {
                return $g;
            }
            $booking->setGroup($g);
        }
        if (array_key_exists('receipt_label', $data)) {
            $v = trim((string) $data['receipt_label']);
            $booking->setReceiptLabel($v === '' ? null : mb_substr($v, 0, 255));
        }
        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $booking->setNotes($v === '' ? null : $v);
        }
        if (array_key_exists('material_item_id', $data)) {
            $raw = $data['material_item_id'];
            if ($raw === null || $raw === '') {
                $booking->setMaterialItem(null);
            } else {
                $mi = $this->resolveMaterialItemFromRequest(['material_item_id' => $raw], $departmentId);
                if ($mi instanceof JsonResponse) {
                    return $mi;
                }
                $booking->setMaterialItem($mi);
            }
        }

        $booking->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($booking, $this->sourceForBooking($booking)));
    }

    private function sourceForBooking(AccountingBooking $booking): ?array
    {
        $map = $this->bookingSource->sourceMapForBookings([$booking]);
        $id = $booking->getId();

        return ($id !== null && isset($map[$id])) ? $map[$id] : null;
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $id): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $booking = $this->entityManager->find(AccountingBooking::class, $id);
        if (!$booking || $booking->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Buchung nicht gefunden'], 404);
        }

        $linkedFollowUps = $this->entityManager->getRepository(AccountingAcquisitionFollowUp::class)
            ->findBy(['accountingBooking' => $booking]);
        foreach ($linkedFollowUps as $followUp) {
            if (!$followUp instanceof AccountingAcquisitionFollowUp) {
                continue;
            }
            $followUp->setStatus(AccountingAcquisitionFollowUp::STATUS_PENDING);
            $followUp->setAccountingBooking(null);
            $followUp->touchUpdatedAt();
            $this->inboxMessages->syncAccountingFollowUp($followUp);
        }

        $this->receiptStorage->deleteAllForBooking($booking);
        $this->entityManager->remove($booking);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function parseCreatePayload(array $data, string $departmentId): array|JsonResponse
    {
        $amount = $this->normalizeAmount($data['amount'] ?? null);
        if ($amount === null) {
            return new JsonResponse(['error' => 'Betrag erforderlich (CHF)'], 400);
        }

        $bookedAt = $this->parseDate((string) ($data['booked_at'] ?? ''));
        if ($bookedAt === null) {
            return new JsonResponse(['error' => 'Buchungsdatum erforderlich (YYYY-MM-DD)'], 400);
        }

        $cc = $this->resolveCostCenter((string) ($data['cost_center_id'] ?? ''), $departmentId);
        if ($cc instanceof JsonResponse) {
            return $cc;
        }

        $entryType = (string) ($data['entry_type'] ?? '');
        if (!in_array($entryType, AccountingBooking::ENTRY_TYPES, true)) {
            return new JsonResponse(['error' => 'Ungültiger oder fehlender Buchungstyp'], 400);
        }

        $group = $this->resolveGroup($data['group_id'] ?? null, $departmentId);
        if ($group instanceof JsonResponse) {
            return $group;
        }

        $pmRaw = $data['payment_method'] ?? null;
        $paymentMethod = $this->normalizePaymentMethod($pmRaw);
        if ($pmRaw !== null && $pmRaw !== '' && $paymentMethod === null) {
            return new JsonResponse(['error' => 'Ungültige Zahlungsart'], 400);
        }

        $receipt = isset($data['receipt_label']) ? trim((string) $data['receipt_label']) : '';
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';

        $paymentStatusRaw = $data['payment_status'] ?? null;
        $paymentStatus = $this->normalizePaymentStatus($paymentStatusRaw);
        if ($paymentStatusRaw !== null && $paymentStatusRaw !== '' && $paymentStatus === null) {
            return new JsonResponse(['error' => 'Ungültiger Zahlungsstatus'], 400);
        }
        if ($paymentStatus === null) {
            $paymentStatus = AccountingBooking::PAYMENT_STATUS_PAID;
        }

        return [
            'costCenter' => $cc,
            'bookedAt' => $bookedAt,
            'amount' => $amount,
            'entryType' => $entryType,
            'paymentMethod' => $paymentMethod,
            'paymentStatus' => $paymentStatus,
            'group' => $group,
            'receiptLabel' => $receipt === '' ? null : mb_substr($receipt, 0, 255),
            'notes' => $notes === '' ? null : $notes,
        ];
    }

    private function normalizeAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $n = (float) $value;
        } else {
            $s = str_replace(["\u{00A0}", ' '], '', trim((string) $value));
            $s = str_replace(',', '.', $s);
            if (!is_numeric($s)) {
                return null;
            }
            $n = (float) $s;
        }
        if ($n < 0) {
            return null;
        }
        return number_format($n, 2, '.', '');
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($d instanceof \DateTimeImmutable) {
            return $d;
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizePaymentMethod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) $value;
        if (!in_array($v, AccountingBooking::PAYMENT_METHODS, true)) {
            return null;
        }

        return $v;
    }

    private function normalizePaymentStatus(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) $value;
        if (!in_array($v, AccountingBooking::PAYMENT_STATUSES, true)) {
            return null;
        }

        return $v;
    }

    private function resolveCostCenter(string $id, string $departmentId): AccountingCostCenter|JsonResponse
    {
        if ($id === '') {
            return new JsonResponse(['error' => 'Kostenstelle erforderlich'], 400);
        }
        $cc = $this->entityManager->find(AccountingCostCenter::class, $id);
        if (!$cc || $cc->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Kostenstelle nicht gefunden'], 400);
        }

        return $cc;
    }

    /**
     * @return MaterialItem|null|JsonResponse
     */
    private function resolveMaterialItemFromRequest(array $data, string $departmentId): MaterialItem|JsonResponse|null
    {
        $raw = trim((string) ($data['material_item_id'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $mi = $this->entityManager->find(MaterialItem::class, $raw);
        if (!$mi || $mi->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 400);
        }

        return $mi;
    }

    private function resolveGroup(mixed $groupId, string $departmentId): Group|JsonResponse|null
    {
        if ($groupId === null || $groupId === '') {
            return null;
        }
        $gid = (string) $groupId;
        $g = $this->entityManager->find(Group::class, $gid);
        if (!$g || $g->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden oder falsches Department'], 400);
        }

        return $g;
    }

    /**
     * @param array<string, mixed>|null $source
     *
     * @return array<string, mixed>
     */
    private function serialize(AccountingBooking $b, ?array $source = null): array
    {
        $g = $b->getGroup();
        $mi = $b->getMaterialItem();
        $cc = $b->getCostCenter();

        $payload = [
            'id' => $b->getId(),
            'department_id' => $b->getDepartment()->getId(),
            'cost_center_id' => $cc->getId(),
            'cost_center_name' => $cc->getName(),
            'cost_center_account_code' => $cc->getAccountCode(),
            'material_item_id' => $mi?->getId(),
            'material_name' => $mi?->getName(),
            'group_id' => $g?->getId(),
            'group_name' => $g?->getName(),
            'amount' => $b->getAmount(),
            'booked_at' => $b->getBookedAt()->format('Y-m-d'),
            'entry_type' => $b->getEntryType(),
            'payment_method' => $b->getPaymentMethod(),
            'payment_status' => $b->getPaymentStatus(),
            'receipt_label' => $b->getReceiptLabel(),
            'notes' => $b->getNotes(),
            'created_at' => $b->getCreatedAt()->format('c'),
            'updated_at' => $b->getUpdatedAt()->format('c'),
        ];

        if ($source !== null) {
            $payload['source'] = $source;
        }

        $payload['receipts'] = $this->photoNormalizer->normalizeOutgoing($b->getReceipts());

        return $payload;
    }
}
