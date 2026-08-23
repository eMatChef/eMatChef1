<?php

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\ActivityGrossanlassWishResponseValue;
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
        $this->procurement->freezeAskedFromInquiry($department, $inquiry);
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
        $this->procurement->freezeAskedFromInquiry($department, $inquiry);
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
            if ($inquiry->getEmail() === '') {
                throw new \InvalidArgumentException('E-Mail fehlt für ' . $inquiry->getName());
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
        $this->assertManage($department, $user);

        $tips = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('r.formPurpose = :purpose')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('purpose', ActivityGrossanlassRound::PURPOSE_COMPANY_TIP)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $created = [];
        foreach ($tips as $wish) {
            if (!$wish instanceof ActivityGrossanlassWishLine) {
                continue;
            }
            $existing = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
                ->findOneBy(['tipWishId' => $wish->getId()]);
            if ($existing instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            $extracted = $this->extractTipFields($wish);
            $inquiry = $this->newInquiry($department);
            $inquiry->setName($extracted['name']);
            $inquiry->setEmail($extracted['email']);
            $inquiry->setPlace($extracted['place'] !== '' ? $extracted['place'] : $wish->getLocation());
            $inquiry->setCategoryIds($extracted['categories']);
            $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_VORSCHLAG);
            $inquiry->setTipWish($wish);
            $inquiry->setTipFrom($wish->getGroup()->getName());
            $this->entityManager->persist($inquiry);
            $created[] = $inquiry;
        }
        $this->entityManager->flush();

        return array_map(fn (DepartmentGrossanlassInquiry $row) => $this->serialize($row), $created);
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

    /**
     * @return array{name: string, email: string, place: string, categories: list<string>}
     */
    private function extractTipFields(ActivityGrossanlassWishLine $wish): array
    {
        $name = trim($wish->getLabel());
        $email = '';
        $place = trim($wish->getLocation());
        $categories = [];
        $response = $wish->getResponse();
        if ($response !== null) {
            $values = $this->entityManager->getRepository(ActivityGrossanlassWishResponseValue::class)
                ->findBy(['responseId' => $response->getId()]);
            foreach ($values as $value) {
                if (!$value instanceof ActivityGrossanlassWishResponseValue) {
                    continue;
                }
                $text = trim((string) ($value->getValueText() ?? $value->getValueNumber() ?? ''));
                if ($text === '') {
                    continue;
                }
                $label = mb_strtolower($value->getField()?->getLabel() ?? '');
                if (str_contains($label, 'mail') || str_contains($label, 'kontakt')) {
                    if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                        $email = strtolower($text);
                    } elseif ($email === '' && str_contains($text, '@')) {
                        $email = strtolower($text);
                    }
                } elseif (str_contains($label, 'kategorie') || str_contains($label, 'bereich')) {
                    $categories[] = $text;
                } elseif (str_contains($label, 'ort') && $place === '') {
                    $place = $text;
                } elseif ((str_contains($label, 'firma') || str_contains($label, 'titel')) && $name === '') {
                    $name = $text;
                }
            }
        }
        if ($name === '') {
            $name = 'Firmenvorschlag';
        }

        return [
            'name' => $name,
            'email' => $email,
            'place' => $place,
            'categories' => $categories,
        ];
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
