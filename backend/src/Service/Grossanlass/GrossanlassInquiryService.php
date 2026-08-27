<?php

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassInquiryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassCommitmentService $commitments,
        private GrossanlassProcurementService $procurement,
        private GrossanlassAnswerCollectorService $collector,
        private GrossanlassMailMergeService $merge,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user): array
    {
        $this->assertManage($department, $user);

        $rows = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
            ->findBy(['departmentId' => $department->getId()], ['createdAt' => 'DESC']);

        return array_map(fn (DepartmentGrossanlassInquiry $row) => $this->serialize($row), $rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertManage($department, $user);
        $inquiry = $this->newInquiry($department);
        $this->applyFields($inquiry, $data, true);
        $this->entityManager->persist($inquiry);
        $this->entityManager->flush();

        return $this->serialize($inquiry);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $inquiryId, array $data): array
    {
        $this->assertManage($department, $user);
        $inquiry = $this->find($department, $inquiryId);
        $this->applyFields($inquiry, $data, false);
        if (($data['status'] ?? null) === DepartmentGrossanlassInquiry::STATUS_ZUSAGE) {
            $this->commitments->ensureFromInquiry($department, $user, $inquiry->getId());
            $inquiry = $this->find($department, $inquiryId);
        }
        if (in_array($inquiry->getStatus(), [
            DepartmentGrossanlassInquiry::STATUS_GESENDET,
            DepartmentGrossanlassInquiry::STATUS_ANTWORT,
            DepartmentGrossanlassInquiry::STATUS_ZUSAGE,
        ], true)) {
            $this->procurement->freezeAskedFromInquiry($department, $inquiry);
        }
        $this->entityManager->flush();

        return $this->serialize($inquiry);
    }

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    public function markSent(Department $department, User $user, array $ids): array
    {
        $this->assertManage($department, $user);
        $updated = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($id);
            if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
                continue;
            }
            if (!$inquiry->isReadyForMail()) {
                throw new \InvalidArgumentException(
                    'E-Mail oder Paket fehlt für ' . $inquiry->getName(),
                );
            }
            if (in_array($inquiry->getStatus(), [
                DepartmentGrossanlassInquiry::STATUS_ZUSAGE,
                DepartmentGrossanlassInquiry::STATUS_ABSAGE,
            ], true)) {
                continue;
            }
            $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_GESENDET);
            $inquiry->appendThread([
                'who' => 'ok',
                'text' => 'Als gesendet gemerkt (ohne Gmail).',
            ]);
            $this->procurement->freezeAskedFromInquiry($department, $inquiry);
            $updated[] = $this->serialize($inquiry);
        }
        $this->entityManager->flush();

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    public function recordReply(Department $department, User $user, string $inquiryId, array $data): array
    {
        $this->assertManage($department, $user);
        $inquiry = $this->find($department, $inquiryId);
        $text = trim((string) ($data['text'] ?? 'Antwort der Firma erfasst.'));
        $inquiry->appendThread(['who' => 'firm', 'text' => $text]);
        if ($inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_GESENDET
            || $inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_ENTWURF
            || $inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_VORSCHLAG
        ) {
            $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ANTWORT);
        }
        $this->entityManager->flush();

        return $this->serialize($inquiry);
    }

    /**
     * Übernimmt offene Firmenvorschläge aus company_tip-Runden.
     *
     * @return list<array<string, mixed>>
     */
    public function importTips(Department $department, User $user): array
    {
        return $this->collector->importPendingTips($department, $user);
    }

    /**
     * @return array{created: list<array<string, mixed>>, skipped: int, errors: list<array{line: int, message: string}>}
     */
    public function importCsv(Department $department, User $user, string $csv): array
    {
        $this->assertManage($department, $user);
        $parsed = GrossanlassInquiryCsv::parse($csv);
        $existingEmails = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
            ->findBy(['departmentId' => $department->getId()]) as $row) {
            if (!$row instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            $email = $row->getEmail();
            if ($email !== '') {
                $existingEmails[$email] = true;
            }
        }
        $created = [];
        $skipped = 0;
        $errors = [];
        foreach ($parsed as $row) {
            $email = $row['email'];
            if ($email !== '' && isset($existingEmails[$email])) {
                ++$skipped;
                continue;
            }
            try {
                $inquiry = $this->newInquiry($department);
                $this->applyFields($inquiry, [
                    'name' => $row['name'],
                    'email' => $email,
                    'place' => $row['place'],
                    'category_ids' => $this->mapCategoryTokens($department, $row['categories']),
                ], true);
                $this->entityManager->persist($inquiry);
                if ($email !== '') {
                    $existingEmails[$email] = true;
                }
                $created[] = $inquiry;
            } catch (\InvalidArgumentException $e) {
                $errors[] = ['line' => $row['line'], 'message' => $e->getMessage()];
            }
        }
        $this->entityManager->flush();

        return [
            'created' => array_map(fn (DepartmentGrossanlassInquiry $row) => $this->serialize($row), $created),
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function mapCategoryTokens(Department $department, array $tokens): array
    {
        $byId = [];
        $byName = [];
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($rows as $row) {
            if (!$row instanceof ActivityGrossanlassProcurementCategory) {
                continue;
            }
            $byId[$row->getId()] = $row->getId();
            $byName[mb_strtolower($row->getName(), 'UTF-8')] = $row->getId();
        }
        $ids = [];
        foreach ($tokens as $token) {
            $value = trim($token);
            if ($value === '') {
                continue;
            }
            if (isset($byId[$value])) {
                $ids[] = $byId[$value];
                continue;
            }
            $ids[] = $byName[mb_strtolower($value, 'UTF-8')] ?? $value;
        }

        return array_values(array_unique($ids));
    }

    private function newInquiry(Department $department): DepartmentGrossanlassInquiry
    {
        $inquiry = new DepartmentGrossanlassInquiry();
        $inquiry->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::INQUIRY,
            DepartmentGrossanlassInquiry::class,
        ));
        $inquiry->setDepartment($department);

        return $inquiry;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFields(DepartmentGrossanlassInquiry $inquiry, array $data, bool $creating): void
    {
        if ($creating || array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Name ist erforderlich');
            }
            $inquiry->setName($name);
        }
        if (array_key_exists('email', $data) || $creating) {
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Ungültige E-Mail-Adresse');
            }
            $inquiry->setEmail($email);
        }
        if (array_key_exists('place', $data) || $creating) {
            $inquiry->setPlace(trim((string) ($data['place'] ?? '')));
        }
        if (array_key_exists('category_ids', $data) || $creating) {
            $raw = $data['category_ids'] ?? [];
            if (is_string($raw)) {
                $raw = preg_split('/[,;]+/', $raw) ?: [];
            }
            $ids = [];
            if (is_array($raw)) {
                foreach ($raw as $item) {
                    $value = trim((string) $item);
                    if ($value !== '') {
                        $ids[] = $value;
                    }
                }
            }
            $inquiry->setCategoryIds($ids);
        }
        if (array_key_exists('status', $data)) {
            $status = (string) $data['status'];
            if (!in_array($status, DepartmentGrossanlassInquiry::STATUSES, true)) {
                throw new \InvalidArgumentException('Ungültiger Status');
            }
            $inquiry->setStatus($status);
        }
    }

    private function find(Department $department, string $inquiryId): DepartmentGrossanlassInquiry
    {
        $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($inquiryId);
        if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Anfrage nicht gefunden');
        }

        return $inquiry;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DepartmentGrossanlassInquiry $inquiry): array
    {
        return [
            'id' => $inquiry->getId(),
            'reference' => $this->merge->displayReference($inquiry->getDepartment(), $inquiry->getId()),
            'name' => $inquiry->getName(),
            'email' => $inquiry->getEmail(),
            'place' => $inquiry->getPlace(),
            'category_ids' => $inquiry->getCategoryIds(),
            'status' => $inquiry->getStatus(),
            'tip_from' => $inquiry->getTipFrom(),
            'tip_wish_id' => $inquiry->getTipWishId(),
            'thread' => $inquiry->getThread(),
            'gmail_draft_id' => $inquiry->getGmailDraftId(),
            'gmail_thread_id' => $inquiry->getGmailThreadId(),
            'gmail_message_id' => $inquiry->getGmailMessageId(),
            'gmail_open_url' => $this->gmailOpenUrl($inquiry),
            'created_at' => $inquiry->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $inquiry->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function gmailOpenUrl(DepartmentGrossanlassInquiry $inquiry): ?string
    {
        if ($inquiry->getGmailThreadId()) {
            return 'https://mail.google.com/mail/u/0/#all/' . $inquiry->getGmailThreadId();
        }
        if ($inquiry->getGmailDraftId()) {
            return 'https://mail.google.com/mail/u/0/#drafts';
        }

        return null;
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Anfragen');
        }
    }
}
