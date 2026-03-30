<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\AccountingCostCenter;
use App\Entity\Department;
use App\Entity\Group;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\DBAL\ParameterType;

#[Route('/api/departments/{departmentId}/accounting/bookings', name: 'api_accounting_bookings_')]
class AccountingBookingController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager
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

        $out = [];
        foreach ($rows as $b) {
            $out[] = $this->serialize($b);
        }

        return new JsonResponse($out);
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
        $booking->setGroup($parse['group']);
        $booking->setReceiptLabel($parse['receiptLabel']);
        $booking->setNotes($parse['notes']);

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        if ($followUp !== null) {
            try {
                $followUp->setAccountingBooking($booking);
                $followUp->setStatus(AccountingAcquisitionFollowUp::STATUS_RECORDED);
                $followUp->touchUpdatedAt();
                $this->entityManager->flush();
            } catch (\Throwable) {
                // Buchung ist gespeichert; Verknüpfung fehlgeschlagen (z. B. Schema)
            }
        }

        return new JsonResponse($this->serialize($booking), 201);
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

        $booking->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($booking));
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

        return [
            'costCenter' => $cc,
            'bookedAt' => $bookedAt,
            'amount' => $amount,
            'entryType' => $entryType,
            'paymentMethod' => $paymentMethod,
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
     * @return array<string, mixed>
     */
    private function serialize(AccountingBooking $b): array
    {
        $g = $b->getGroup();

        return [
            'id' => $b->getId(),
            'department_id' => $b->getDepartment()->getId(),
            'cost_center_id' => $b->getCostCenter()->getId(),
            'cost_center_name' => $b->getCostCenter()->getName(),
            'group_id' => $g?->getId(),
            'group_name' => $g?->getName(),
            'amount' => $b->getAmount(),
            'booked_at' => $b->getBookedAt()->format('Y-m-d'),
            'entry_type' => $b->getEntryType(),
            'payment_method' => $b->getPaymentMethod(),
            'receipt_label' => $b->getReceiptLabel(),
            'notes' => $b->getNotes(),
            'created_at' => $b->getCreatedAt()->format('c'),
            'updated_at' => $b->getUpdatedAt()->format('c'),
        ];
    }
}
