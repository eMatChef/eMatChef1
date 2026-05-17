<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingAcquisitionFollowUp;
use App\Service\InboxMessageService;
use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/accounting/acquisition-followups', name: 'api_accounting_acquisition_followups_')]
class AccountingAcquisitionFollowUpController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
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

        $status = trim((string) $request->query->get('status', AccountingAcquisitionFollowUp::STATUS_PENDING));
        if (!in_array($status, [AccountingAcquisitionFollowUp::STATUS_PENDING, AccountingAcquisitionFollowUp::STATUS_RECORDED], true)) {
            $status = AccountingAcquisitionFollowUp::STATUS_PENDING;
        }

        $activityIdFilter = trim((string) $request->query->get('activity_id', ''));

        $deptRef = $this->entityManager->getReference(Department::class, $departmentId);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(AccountingAcquisitionFollowUp::class, 'f')
            ->where('f.department = :d')
            ->andWhere('f.status = :st')
            ->setParameter('d', $deptRef)
            ->setParameter('st', $status)
            ->orderBy('f.createdAt', 'ASC');

        if ($activityIdFilter !== '') {
            $qb->andWhere('f.activity = :act')
                ->setParameter('act', $activityIdFilter);
        }

        try {
            $rows = $qb->getQuery()->getResult();
        } catch (\Throwable $e) {
            // z. B. Migration noch nicht ausgeführt (Tabelle fehlt) → UI soll nicht mit 500 brechen
            if ($this->isMissingFollowUpTable($e)) {
                return new JsonResponse([]);
            }
            throw $e;
        }

        $out = [];
        foreach ($rows as $f) {
            $out[] = $this->serialize($f);
        }

        return new JsonResponse($out);
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

        $amount = $this->normalizeAmount($data['amount'] ?? null);
        if ($amount === null) {
            return new JsonResponse(['error' => 'Betrag erforderlich (CHF)'], 400);
        }

        $suggested = $this->parseDate((string) ($data['suggested_date'] ?? ''));
        if ($suggested === null) {
            return new JsonResponse(['error' => 'Datum erforderlich (YYYY-MM-DD)'], 400);
        }

        $receipt = isset($data['receipt_label']) ? trim((string) $data['receipt_label']) : '';
        $batchId = isset($data['material_batch_id']) ? trim((string) $data['material_batch_id']) : '';

        $materialBatch = null;
        if ($batchId !== '') {
            $materialBatch = $this->entityManager->find(MaterialBatch::class, $batchId);
            if (!$materialBatch) {
                return new JsonResponse(['error' => 'Material-Batch nicht gefunden'], 400);
            }
            if ($materialBatch->getMaterialItem()->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'Batch gehört nicht zu diesem Department'], 400);
            }
        }

        try {
            $followUp = new AccountingAcquisitionFollowUp();
            // generateUnique() queried die Tabelle → bei fehlender Migration hier Exception (vorher außerhalb try)
            $followUp->setId(IdGenerator::generateUnique($this->entityManager, AccountingAcquisitionFollowUp::class));
            $followUp->setDepartment($dept);
            $followUp->setAmount($amount);
            $followUp->setSuggestedDate($suggested);
            $followUp->setReceiptLabel($receipt === '' ? null : mb_substr($receipt, 0, 255));
            $followUp->setStatus(AccountingAcquisitionFollowUp::STATUS_PENDING);

            if ($materialBatch !== null) {
                $followUp->setMaterialBatch($materialBatch);
                $followUp->setSourceKind(AccountingAcquisitionFollowUp::SOURCE_BATCH);
            }

            $this->entityManager->persist($followUp);
            $this->entityManager->flush();
            $this->inboxMessages->syncAccountingFollowUp($followUp);

            return new JsonResponse($this->serialize($followUp), 201);
        } catch (\Throwable $e) {
            if ($this->isMissingFollowUpTable($e)) {
                return new JsonResponse([
                    'error' => 'Datenbank-Tabelle für Anschaffungs-Aufträge fehlt. Bitte Migration ausführen (accounting_acquisition_follow_up).',
                ], 503);
            }
            throw $e;
        }
    }

    private function isMissingFollowUpTable(\Throwable $e): bool
    {
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            $msg = strtolower($cur->getMessage());
            // PostgreSQL: 42P01 = undefined_table (Nachricht enthält oft nicht den vollen Tabellennamen im äußeren Exception-Text)
            if (str_contains($msg, '42p01')) {
                return true;
            }
            if (str_contains($msg, 'accounting_acquisition_follow_up')
                && (str_contains($msg, 'does not exist')
                    || str_contains($msg, "doesn't exist")
                    || str_contains($msg, 'unknown table')
                    || str_contains($msg, 'no such table')
                    || str_contains($msg, 'undefined table'))) {
                return true;
            }
        }

        return false;
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
        if ($n <= 0) {
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

    /**
     * @return array<string, mixed>
     */
    private function serialize(AccountingAcquisitionFollowUp $f): array
    {
        $batch = $f->getMaterialBatch();
        $booking = $f->getAccountingBooking();
        $activity = $f->getActivity();
        $material = $f->getMaterialItem();

        return [
            'id' => $f->getId(),
            'department_id' => $f->getDepartment()->getId(),
            'material_batch_id' => $batch?->getId(),
            'activity_id' => $activity?->getId(),
            'activity_name' => $activity?->getName(),
            'activity_group_id' => $activity?->getGroupId(),
            'activity_type' => $activity?->getType(),
            'source_kind' => $f->getSourceKind(),
            'source_ref_id' => $f->getSourceRefId(),
            'material_item_id' => $material?->getId(),
            'material_name' => $material?->getName(),
            'amount' => $f->getAmount(),
            'suggested_date' => $f->getSuggestedDate()->format('Y-m-d'),
            'receipt_label' => $f->getReceiptLabel(),
            'status' => $f->getStatus(),
            'accounting_booking_id' => $booking?->getId(),
            'created_at' => $f->getCreatedAt()->format('c'),
            'updated_at' => $f->getUpdatedAt()->format('c'),
        ];
    }
}
