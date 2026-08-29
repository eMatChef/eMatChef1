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
        private GrossanlassPlaceGeocoder $geocoder,
        private GrossanlassInquiryWebLookup $contactLookup,
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
            $this->procurement->freezeAskedFromInquiry($department, $inquiry);
        }
        $this->entityManager->flush();

        return $this->serialize($inquiry);
    }

    /**
     * @return array{ok: true, id: string}
     */
    public function delete(Department $department, User $user, string $inquiryId): array
    {
        $this->assertManage($department, $user);
        $inquiry = $this->find($department, $inquiryId);
        $id = $inquiry->getId();
        $this->entityManager->remove($inquiry);
        $this->entityManager->flush();

        return ['ok' => true, 'id' => $id];
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
                    'E-Mail oder Kategorie fehlt für ' . $inquiry->getName(),
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
                    'website' => $row['website'],
                    'offering' => $row['offering'],
                    'notes' => $row['notes'],
                    ...($row['contact_first_name'] !== '' || $row['contact_last_name'] !== ''
                        ? [
                            'contact_first_name' => $row['contact_first_name'],
                            'contact_last_name' => $row['contact_last_name'],
                        ]
                        : ['contact_name' => $row['contact_name']]),
                    'contact_salutation' => $row['contact_salutation'],
                    'phone' => $row['phone'],
                    'category_ids' => $this->mapCategoryTokens($department, $row['categories']),
                ], true, false);
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
     * Fehlende Orte geocoden (Wellen, nicht alle 750 in einem Request).
     *
     * @return array{updated: list<array<string, mixed>>, geocoded: int, remaining: int}
     */
    public function geocodeMissing(Department $department, User $user, int $limit = 50): array
    {
        $this->assertManage($department, $user);
        $limit = max(1, min(80, $limit));
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
            ->findBy(['departmentId' => $department->getId()], ['createdAt' => 'DESC']);
        $updated = [];
        $attempted = 0;
        $stillMissing = 0;
        foreach ($rows as $inquiry) {
            if (!$inquiry instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            if ($inquiry->hasCoordinates() || $inquiry->getPlace() === '') {
                continue;
            }
            if ($attempted >= $limit) {
                ++$stillMissing;
                continue;
            }
            ++$attempted;
            $this->applyPlaceCoords($inquiry, true);
            if ($inquiry->hasCoordinates()) {
                $updated[] = $this->serialize($inquiry);
            } else {
                ++$stillMissing;
            }
        }
        $this->entityManager->flush();

        return [
            'updated' => $updated,
            'geocoded' => count($updated),
            'remaining' => $stillMissing,
        ];
    }

    /**
     * Öffentliche Kontaktdaten vorschlagen (Webseite, E-Mail, Telefon).
     * Übernehmen bleibt in der UI — hier wird nichts gespeichert.
     *
     * @param array<string, mixed> $data
     * @return array{
     *   query: string,
     *   search_url: string,
     *   website: string|null,
     *   emails: list<array{value: string, source: string}>,
     *   phones: list<array{value: string, source: string}>
     * }
     */
    public function webLookup(Department $department, User $user, array $data): array
    {
        $this->assertManage($department, $user);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Firmenname fehlt');
        }

        return $this->contactLookup->lookup(
            $name,
            (string) ($data['place'] ?? ''),
            (string) ($data['website'] ?? ''),
        );
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
    private function applyFields(DepartmentGrossanlassInquiry $inquiry, array $data, bool $creating, bool $geocodePlace = true): void
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
            $place = trim((string) ($data['place'] ?? ''));
            $placeChanged = $creating || $place !== $inquiry->getPlace();
            $inquiry->setPlace($place);
            $hasExplicit = array_key_exists('latitude', $data) || array_key_exists('longitude', $data);
            if ($hasExplicit) {
                $lat = $data['latitude'] ?? null;
                $lng = $data['longitude'] ?? null;
                $inquiry->setLatitude(is_numeric($lat) ? (float) $lat : null);
                $inquiry->setLongitude(is_numeric($lng) ? (float) $lng : null);
            } elseif ($placeChanged && $geocodePlace) {
                $this->applyPlaceCoords($inquiry, true);
            }
        }
        if (!(array_key_exists('place', $data) || $creating) && (array_key_exists('latitude', $data) || array_key_exists('longitude', $data))) {
            $lat = $data['latitude'] ?? $inquiry->getLatitude();
            $lng = $data['longitude'] ?? $inquiry->getLongitude();
            $inquiry->setLatitude(is_numeric($lat) ? (float) $lat : null);
            $inquiry->setLongitude(is_numeric($lng) ? (float) $lng : null);
        }
        if (array_key_exists('website', $data) || $creating) {
            $inquiry->setWebsite(mb_substr(trim((string) ($data['website'] ?? '')), 0, 500));
        }
        if (array_key_exists('offering', $data) || $creating) {
            $inquiry->setOffering(trim((string) ($data['offering'] ?? '')));
        }
        if (array_key_exists('notes', $data) || $creating) {
            $inquiry->setNotes(trim((string) ($data['notes'] ?? '')));
        }
        if (array_key_exists('contact_first_name', $data) || array_key_exists('contact_last_name', $data)) {
            if (array_key_exists('contact_first_name', $data) || $creating) {
                $inquiry->setContactFirstName(trim((string) ($data['contact_first_name'] ?? '')));
            }
            if (array_key_exists('contact_last_name', $data) || $creating) {
                $inquiry->setContactLastName(trim((string) ($data['contact_last_name'] ?? '')));
            }
        } elseif (array_key_exists('contact_name', $data) || $creating) {
            $inquiry->setContactName(trim((string) ($data['contact_name'] ?? '')));
        }
        if (array_key_exists('contact_salutation', $data) || $creating) {
            $inquiry->setContactSalutation(trim((string) ($data['contact_salutation'] ?? '')));
        }
        if (array_key_exists('phone', $data) || $creating) {
            $inquiry->setPhone(mb_substr(trim((string) ($data['phone'] ?? '')), 0, 64));
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
            'latitude' => $inquiry->getLatitude(),
            'longitude' => $inquiry->getLongitude(),
            'website' => $inquiry->getWebsite(),
            'offering' => $inquiry->getOffering(),
            'notes' => $inquiry->getNotes(),
            ...$inquiry->serializeContact(),
            'phone' => $inquiry->getPhone(),
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

    private function applyPlaceCoords(DepartmentGrossanlassInquiry $inquiry, bool $overwrite): void
    {
        if (!$overwrite && $inquiry->hasCoordinates()) {
            return;
        }
        $coords = $this->geocoder->geocode($inquiry->getPlace());
        $inquiry->setLatitude($coords['lat'] ?? null);
        $inquiry->setLongitude($coords['lng'] ?? null);
    }
}
