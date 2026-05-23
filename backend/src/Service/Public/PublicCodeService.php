<?php

namespace App\Service\Public;

use App\Entity\Activity;
use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\PublicCode;
use App\Entity\WorkshopTicket;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PublicCodeService
{
    public const ENTITY_MATERIAL = 'material';
    public const ENTITY_BATCH = 'batch';
    public const ENTITY_ACTIVITY = 'activity';
    public const ENTITY_WORKSHOP = 'workshop';

    private string $publicQrBaseUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
        #[Autowire('%env(APP_PUBLIC_QR_URL)%')] private string $appPublicQrUrl,
    ) {
        $trimmedQr = trim($this->appPublicQrUrl);
        $this->publicQrBaseUrl = $trimmedQr !== ''
            ? rtrim($trimmedQr, '/')
            : rtrim($this->appFrontendUrl, '/');
    }

    public function resolveMaterialByPublicCode(string $publicCode): ?array
    {
        $normalized = trim($publicCode);
        if ($normalized === '') {
            return null;
        }

        /** @var PublicCode|null $codeEntry */
        $codeEntry = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => 'material',
            'publicCode' => $normalized,
        ]);
        if (!$codeEntry) {
            return null;
        }

        // Public-Status prüfen
        if (!$codeEntry->getIsPublic() || !$codeEntry->getIsActive()) {
            return null;
        }
        if ($codeEntry->getRevokedAt() !== null) {
            return null;
        }

        $expiresAt = $codeEntry->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTime()) {
            return null;
        }

        /** @var MaterialItem|null $material */
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($codeEntry->getEntityId());
        if (!$material || $material->getDeletedAt() !== null) {
            return null;
        }

        $department = $material->getDepartment();
        $publicSettings = $this->resolvePublicSettings($department->getId());
        $recipientEmail = $this->getPublicRecipientEmailFromSettings($department, $publicSettings);

        $this->recordSuccessfulPublicScan($codeEntry);

        return [
            'code' => $codeEntry->getPublicCode(),
            'entity_type' => 'material',
            'material' => [
                'id' => $material->getId(),
                'name' => $material->getName(),
                'description' => $material->getDescription(),
                'manufacturer' => $material->getManufacturer(),
                'model' => $material->getModel(),
            ],
            'department' => [
                'id' => $department->getId(),
                'name' => $department->getName(),
            ],
            'contact' => ($recipientEmail && $publicSettings['show_contact_email']) ? [
                'email' => $recipientEmail,
            ] : null,
            'contact_note' => ($publicSettings['show_contact_note'] && ($publicSettings['contact_note'] ?? '')) ? $publicSettings['contact_note'] : null,
            'public_ui' => [
                'show_contact_form' => $publicSettings['show_contact_form'],
                'show_contact_email' => $publicSettings['show_contact_email'],
                'show_contact_note' => $publicSettings['show_contact_note'],
                'can_deliver_message' => $this->canDeliverPublicMessage($department->getId(), $recipientEmail),
            ],
        ];
    }

    public function resolveBatchByPublicCode(string $publicCode): ?array
    {
        $normalized = trim($publicCode);
        if ($normalized === '') {
            return null;
        }

        /** @var PublicCode|null $codeEntry */
        $codeEntry = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => 'batch',
            'publicCode' => $normalized,
        ]);
        if (!$codeEntry) {
            return null;
        }

        if (!$codeEntry->getIsPublic() || !$codeEntry->getIsActive()) {
            return null;
        }
        if ($codeEntry->getRevokedAt() !== null) {
            return null;
        }
        $expiresAt = $codeEntry->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTime()) {
            return null;
        }

        /** @var MaterialBatch|null $batch */
        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($codeEntry->getEntityId());
        if (!$batch) {
            return null;
        }

        $material = $batch->getMaterialItem();
        if (!$material || $material->getDeletedAt() !== null) {
            return null;
        }

        $department = $material->getDepartment();
        $publicSettings = $this->resolvePublicSettings($department->getId());
        $recipientEmail = $this->getPublicRecipientEmailFromSettings($department, $publicSettings);

        $this->recordSuccessfulPublicScan($codeEntry);

        return $this->buildBatchLookupPayload($codeEntry, $batch, $material, $department, $publicSettings, $recipientEmail);
    }

    /**
     * Kanonische Material+Charge-URL: /i/m/{materialCode}/b/{batchCode}.
     * Prüft, dass der Batch zum Material mit materialCode gehört.
     */
    public function resolveMaterialBatchByPublicCodes(string $materialCode, string $batchCode): ?array
    {
        $matCode = trim($materialCode);
        $batchCodeNorm = trim($batchCode);
        if ($matCode === '' || $batchCodeNorm === '') {
            return null;
        }

        $batchEntry = $this->findActivePublicCodeEntry(self::ENTITY_BATCH, $batchCodeNorm);
        $matEntry = $this->findActivePublicCodeEntry(self::ENTITY_MATERIAL, $matCode);
        if ($batchEntry === null || $matEntry === null) {
            return null;
        }

        /** @var MaterialBatch|null $batch */
        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($batchEntry->getEntityId());
        if (!$batch) {
            return null;
        }

        $material = $batch->getMaterialItem();
        if (
            !$material
            || $material->getDeletedAt() !== null
            || $material->getId() !== $matEntry->getEntityId()
        ) {
            return null;
        }

        $department = $material->getDepartment();
        $publicSettings = $this->resolvePublicSettings($department->getId());
        $recipientEmail = $this->getPublicRecipientEmailFromSettings($department, $publicSettings);

        $this->recordSuccessfulPublicScan($batchEntry);

        $payload = $this->buildBatchLookupPayload(
            $batchEntry,
            $batch,
            $material,
            $department,
            $publicSettings,
            $recipientEmail,
        );
        $payload['material_code'] = $matCode;
        $payload['batch_code'] = $batchCodeNorm;
        $payload['public_url'] = $this->buildMaterialBatchPublicUrl($matCode, $batchCodeNorm);

        return $payload;
    }

    public function resolveActivityByPublicCode(string $publicCode): ?array
    {
        $normalized = trim($publicCode);
        if ($normalized === '') {
            return null;
        }

        $codeEntry = $this->findActivePublicCodeEntry(self::ENTITY_ACTIVITY, $normalized);
        if ($codeEntry === null) {
            return null;
        }

        /** @var Activity|null $activity */
        $activity = $this->entityManager->getRepository(Activity::class)->find($codeEntry->getEntityId());
        if (!$activity || $activity->isDeleted()) {
            return null;
        }

        $department = $activity->getDepartment();
        $publicSettings = $this->resolvePublicSettings($department->getId());
        $recipientEmail = $this->getPublicRecipientEmailFromSettings($department, $publicSettings);

        $this->recordSuccessfulPublicScan($codeEntry);

        $usageStart = $activity->getUsageStart();
        $usageEnd = $activity->getUsageEnd();
        $planningStart = $activity->getPlanningStart();
        $planningEnd = $activity->getPlanningEnd();

        return [
            'code' => $codeEntry->getPublicCode(),
            'entity_type' => self::ENTITY_ACTIVITY,
            'public_url' => $this->buildActivityPublicUrl($codeEntry->getPublicCode()),
            'activity' => [
                'id' => $activity->getId(),
                'name' => $activity->getName(),
                'type' => $activity->getType(),
                'usage_start' => $usageStart?->format(\DateTimeInterface::ATOM),
                'usage_end' => $usageEnd?->format(\DateTimeInterface::ATOM),
                'planning_start' => $planningStart?->format(\DateTimeInterface::ATOM),
                'planning_end' => $planningEnd?->format(\DateTimeInterface::ATOM),
            ],
            'department' => [
                'id' => $department->getId(),
                'name' => $department->getName(),
            ],
            'contact' => ($recipientEmail && $publicSettings['show_contact_email']) ? [
                'email' => $recipientEmail,
            ] : null,
            'contact_note' => ($publicSettings['show_contact_note'] && ($publicSettings['contact_note'] ?? '')) ? $publicSettings['contact_note'] : null,
            'public_ui' => $this->buildPublicUiPayload($department->getId(), $publicSettings, $recipientEmail),
        ];
    }

    public function resolveWorkshopByPublicCode(string $publicCode): ?array
    {
        $normalized = trim($publicCode);
        if ($normalized === '') {
            return null;
        }

        $codeEntry = $this->findActivePublicCodeEntry(self::ENTITY_WORKSHOP, $normalized);
        if ($codeEntry === null) {
            return null;
        }

        /** @var WorkshopTicket|null $ticket */
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find($codeEntry->getEntityId());
        if (!$ticket) {
            return null;
        }

        $department = $ticket->getDepartment();
        $material = $ticket->getMaterialItem();
        if ($material->getDeletedAt() !== null) {
            return null;
        }

        $publicSettings = $this->resolvePublicSettings($department->getId());
        $recipientEmail = $this->getPublicRecipientEmailFromSettings($department, $publicSettings);

        $this->recordSuccessfulPublicScan($codeEntry);

        return [
            'code' => $codeEntry->getPublicCode(),
            'entity_type' => self::ENTITY_WORKSHOP,
            'public_url' => $this->buildWorkshopPublicUrl($codeEntry->getPublicCode()),
            'workshop' => [
                'id' => $ticket->getId(),
                'title' => $ticket->getTitle(),
                'type' => $ticket->getType(),
                'status' => $ticket->getStatus(),
                'material_name' => $material->getName(),
            ],
            'department' => [
                'id' => $department->getId(),
                'name' => $department->getName(),
            ],
            'contact' => ($recipientEmail && $publicSettings['show_contact_email']) ? [
                'email' => $recipientEmail,
            ] : null,
            'contact_note' => ($publicSettings['show_contact_note'] && ($publicSettings['contact_note'] ?? '')) ? $publicSettings['contact_note'] : null,
            'public_ui' => $this->buildPublicUiPayload($department->getId(), $publicSettings, $recipientEmail),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBatchLookupPayload(
        PublicCode $codeEntry,
        MaterialBatch $batch,
        MaterialItem $material,
        Department $department,
        array $publicSettings,
        ?string $recipientEmail,
    ): array {
        return [
            'code' => $codeEntry->getPublicCode(),
            'entity_type' => self::ENTITY_BATCH,
            'batch' => [
                'id' => $batch->getId(),
                'serial_number' => $batch->getSerialNumber(),
                'label' => $batch->getLabel(),
                'status' => $batch->getStatus(),
            ],
            'material' => [
                'id' => $material->getId(),
                'name' => $material->getName(),
                'description' => $material->getDescription(),
                'manufacturer' => $material->getManufacturer(),
                'model' => $material->getModel(),
            ],
            'department' => [
                'id' => $department->getId(),
                'name' => $department->getName(),
            ],
            'contact' => ($recipientEmail && $publicSettings['show_contact_email']) ? [
                'email' => $recipientEmail,
            ] : null,
            'contact_note' => ($publicSettings['show_contact_note'] && ($publicSettings['contact_note'] ?? '')) ? $publicSettings['contact_note'] : null,
            'public_ui' => $this->buildPublicUiPayload($department->getId(), $publicSettings, $recipientEmail),
        ];
    }

    /**
     * Versandart: email | in_app | both.
     */
    public function getPublicFoundContactDelivery(string $departmentId): string
    {
        /** @var DepartmentSetting[] $settings */
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)->findBy([
            'departmentId' => $departmentId,
        ]);
        $map = [];
        foreach ($settings as $setting) {
            $map[$setting->getSettingKey()] = $setting->getSettingValue();
        }
        $defaults = DepartmentSetting::getGeneralDefaults();
        $raw = strtolower(trim((string) ($map['general.public_found_contact_delivery'] ?? $defaults['general.public_found_contact_delivery'] ?? 'both')));
        if (!in_array($raw, ['email', 'in_app', 'both'], true)) {
            return 'both';
        }

        return $raw;
    }

    private function canDeliverPublicMessage(string $departmentId, ?string $recipientEmail): bool
    {
        $delivery = $this->getPublicFoundContactDelivery($departmentId);
        $storeInApp = in_array($delivery, ['in_app', 'both'], true);
        $sendEmail = in_array($delivery, ['email', 'both'], true);
        $hasEmail = $recipientEmail !== null && trim((string) $recipientEmail) !== '';

        if ($storeInApp) {
            return true;
        }

        return $hasEmail && $sendEmail;
    }

    /**
     * Erfolgreicher öffentlicher Aufruf (Lookup): Scan-Statistik, Fehler unterdrücken damit der Lookup nicht scheitert.
     */
    private function recordSuccessfulPublicScan(PublicCode $code): void
    {
        try {
            $code->setScanCount($code->getScanCount() + 1);
            $code->setLastScannedAt(new \DateTime());
            $this->entityManager->flush();
        } catch (\Throwable) {
            // Lookup-Antwort hat Vorrang
        }
    }

    /**
     * Stellt sicher, dass ein Material einen aktiven Public-Code besitzt.
     * Erstellt nur dann einen neuen Code, wenn noch keiner vorhanden ist.
     *
     * @param string|null $createdByUserId Symfony-User-ID (12), wenn aus authentifiziertem Request
     */
    public function ensureMaterialPublicCode(MaterialItem $material, ?string $createdByUserId = null): PublicCode
    {
        $materialId = $material->getId();
        if (!$materialId) {
            throw new \InvalidArgumentException('Material muss eine ID besitzen, bevor ein Public-Code erzeugt wird.');
        }

        /** @var PublicCode|null $existing */
        $existing = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => 'material',
            'entityId' => $materialId,
            'isActive' => true,
        ]);
        if ($existing) {
            return $existing;
        }

        $entry = new PublicCode();
        $entry->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, PublicCode::class, 'pc'));
        $entry->setEntityType('material');
        $entry->setEntityId($materialId);
        $entry->setDepartmentId($material->getDepartmentId());
        $entry->setIsPublic(true);
        $entry->setIsActive(true);
        $entry->setVersion(1);
        $entry->setPublicCode(IdGenerator::generateUniquePublicCode($this->entityManager, PublicCode::class, 'publicCode'));
        if ($createdByUserId !== null && $createdByUserId !== '') {
            $entry->setCreatedByUserId($createdByUserId);
        }

        $this->entityManager->persist($entry);

        return $entry;
    }

    /**
     * Öffentlicher QR pro Charge (Etikett: kanonische URL mit Material- + Batch-Code).
     * Ausnahme: serialisierte Charge ohne Seriennummer — kein Batch-QR.
     *
     * @param string|null $createdByUserId Symfony-User-ID (12), wenn aus authentifiziertem Request
     */
    public function ensureBatchPublicCode(MaterialBatch $batch, ?string $createdByUserId = null): PublicCode
    {
        $batchId = $batch->getId();
        if (!$batchId) {
            throw new \InvalidArgumentException('Batch muss eine ID besitzen, bevor ein Public-Code erzeugt wird.');
        }

        /** @var PublicCode|null $existing */
        $existing = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => 'batch',
            'entityId' => $batchId,
            'isActive' => true,
        ]);
        if ($existing) {
            return $existing;
        }

        $material = $batch->getMaterialItem();
        if (
            $material !== null
            && $material->getTrackingType() === 'serialized'
            && trim((string) $batch->getSerialNumber()) === ''
        ) {
            throw new \InvalidArgumentException(
                'Bei serialisierten Artikeln gibt es einen öffentlichen QR-Code nur pro Seriennummer (Einheit), nicht für eine Charge ohne Seriennummer.'
            );
        }

        $entry = new PublicCode();
        $entry->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, PublicCode::class, 'pc'));
        $entry->setEntityType('batch');
        $entry->setEntityId($batchId);
        $entry->setDepartmentId($batch->getMaterialItem()->getDepartmentId());
        $entry->setIsPublic(true);
        $entry->setIsActive(true);
        $entry->setVersion(1);
        $entry->setPublicCode(IdGenerator::generateUniquePublicCode($this->entityManager, PublicCode::class, 'publicCode'));
        if ($createdByUserId !== null && $createdByUserId !== '') {
            $entry->setCreatedByUserId($createdByUserId);
        }

        $this->entityManager->persist($entry);

        return $entry;
    }

    /**
     * @param string|null $createdByUserId Symfony-User-ID (12), wenn aus authentifiziertem Request
     */
    public function ensureActivityPublicCode(Activity $activity, ?string $createdByUserId = null): PublicCode
    {
        $activityId = $activity->getId();
        if (!$activityId) {
            throw new \InvalidArgumentException('Aktivität muss eine ID besitzen, bevor ein Public-Code erzeugt wird.');
        }

        /** @var PublicCode|null $existing */
        $existing = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => self::ENTITY_ACTIVITY,
            'entityId' => $activityId,
            'isActive' => true,
        ]);
        if ($existing) {
            return $existing;
        }

        $entry = new PublicCode();
        $entry->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, PublicCode::class, 'pc'));
        $entry->setEntityType(self::ENTITY_ACTIVITY);
        $entry->setEntityId($activityId);
        $entry->setDepartmentId($activity->getDepartmentId());
        $entry->setIsPublic(true);
        $entry->setIsActive(true);
        $entry->setVersion(1);
        $entry->setPublicCode(IdGenerator::generateUniquePublicCode($this->entityManager, PublicCode::class, 'publicCode'));
        if ($createdByUserId !== null && $createdByUserId !== '') {
            $entry->setCreatedByUserId($createdByUserId);
        }

        $this->entityManager->persist($entry);

        return $entry;
    }

    /**
     * @param string|null $createdByUserId Symfony-User-ID (12), wenn aus authentifiziertem Request
     */
    public function ensureWorkshopPublicCode(WorkshopTicket $ticket, ?string $createdByUserId = null): PublicCode
    {
        $ticketId = $ticket->getId();
        if (!$ticketId) {
            throw new \InvalidArgumentException('Werkstatt-Ticket muss eine ID besitzen, bevor ein Public-Code erzeugt wird.');
        }

        /** @var PublicCode|null $existing */
        $existing = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => self::ENTITY_WORKSHOP,
            'entityId' => $ticketId,
            'isActive' => true,
        ]);
        if ($existing) {
            return $existing;
        }

        $entry = new PublicCode();
        $entry->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, PublicCode::class, 'pc'));
        $entry->setEntityType(self::ENTITY_WORKSHOP);
        $entry->setEntityId($ticketId);
        $entry->setDepartmentId($ticket->getDepartmentId());
        $entry->setIsPublic(true);
        $entry->setIsActive(true);
        $entry->setVersion(1);
        $entry->setPublicCode(IdGenerator::generateUniquePublicCode($this->entityManager, PublicCode::class, 'publicCode'));
        if ($createdByUserId !== null && $createdByUserId !== '') {
            $entry->setCreatedByUserId($createdByUserId);
        }

        $this->entityManager->persist($entry);

        return $entry;
    }

    public function getActiveActivityPublicCode(string $activityId): ?PublicCode
    {
        return $this->getActiveEntityPublicCode(self::ENTITY_ACTIVITY, $activityId);
    }

    public function getActiveWorkshopPublicCode(string $workshopTicketId): ?PublicCode
    {
        return $this->getActiveEntityPublicCode(self::ENTITY_WORKSHOP, $workshopTicketId);
    }

    private function getActiveEntityPublicCode(string $entityType, string $entityId): ?PublicCode
    {
        /** @var PublicCode|null $entry */
        $entry = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => $entityType,
            'entityId' => $entityId,
            'isActive' => true,
        ]);

        if (!$entry || !$entry->getIsPublic() || $entry->getRevokedAt() !== null) {
            return null;
        }

        $expiresAt = $entry->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTime()) {
            return null;
        }

        return $entry;
    }

    public function getActiveMaterialPublicCode(string $materialId): ?PublicCode
    {
        /** @var PublicCode|null $entry */
        $entry = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => 'material',
            'entityId' => $materialId,
            'isActive' => true,
        ]);

        if (!$entry || !$entry->getIsPublic() || $entry->getRevokedAt() !== null) {
            return null;
        }

        $expiresAt = $entry->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTime()) {
            return null;
        }

        return $entry;
    }

    /**
     * @deprecated Nur noch für Legacy-Auflösung `/i/m/{code}`; Etiketten nutzen {@see buildMaterialBatchPublicUrl}.
     */
    public function buildMaterialPublicUrl(string $publicCode): string
    {
        return $this->publicQrBaseUrl . '/i/m/' . rawurlencode($publicCode);
    }

    public function buildMaterialBatchPublicUrl(string $materialCode, string $batchCode): string
    {
        return $this->publicQrBaseUrl
            . '/i/m/' . rawurlencode($materialCode)
            . '/b/' . rawurlencode($batchCode);
    }

    public function buildActivityPublicUrl(string $activityCode): string
    {
        return $this->publicQrBaseUrl . '/i/a/' . rawurlencode($activityCode);
    }

    public function buildWorkshopPublicUrl(string $workshopCode): string
    {
        return $this->publicQrBaseUrl . '/i/w/' . rawurlencode($workshopCode);
    }

    public function buildCanonicalMaterialBatchPublicUrlForIds(string $materialId, string $batchId): ?string
    {
        $matEntry = $this->getActiveMaterialPublicCode($materialId);
        $batchEntry = $this->getActiveBatchPublicCode($batchId);
        if ($matEntry === null || $batchEntry === null) {
            return null;
        }

        return $this->buildMaterialBatchPublicUrl(
            $matEntry->getPublicCode(),
            $batchEntry->getPublicCode(),
        );
    }

    /**
     * Ordnet den bestehenden öffentlichen QR (gleicher Code-String) der neuen Batch-ID zu –
     * z. B. physische Combo aus Kiste: derselbe Aufkleber wie an der Kisten-Charge.
     */
    public function reassignBatchPublicCode(string $fromBatchId, string $toBatchId): void
    {
        if ($fromBatchId === '' || $toBatchId === '' || $fromBatchId === $toBatchId) {
            return;
        }

        /** @var PublicCode|null $entry */
        $entry = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => 'batch',
            'entityId' => $fromBatchId,
            'isActive' => true,
        ]);
        if (!$entry) {
            return;
        }

        $entry->setEntityId($toBatchId);
    }

    public function getActiveBatchPublicCode(string $batchId): ?PublicCode
    {
        /** @var PublicCode|null $entry */
        $entry = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => 'batch',
            'entityId' => $batchId,
            'isActive' => true,
        ]);

        if (!$entry || !$entry->getIsPublic() || $entry->getRevokedAt() !== null) {
            return null;
        }

        $expiresAt = $entry->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTime()) {
            return null;
        }

        return $entry;
    }

    public function buildBatchPublicUrl(string $publicCode): string
    {
        return $this->publicQrBaseUrl . '/i/b/' . rawurlencode($publicCode);
    }

    /**
     * Public-Kontaktmail:
     * 1) allgemeine Adresse (type=general, is_primary=true)
     * 2) allgemeine Adresse (type=general, beliebig)
     */
    private function resolvePublicEmail(Department $department): ?string
    {
        /** @var Address|null $generalPrimary */
        $generalPrimary = $this->entityManager->getRepository(Address::class)->findOneBy([
            'departmentId' => $department->getId(),
            'type' => 'general',
            'isPrimary' => true,
            'deletedAt' => null,
        ]);
        $generalPrimaryEmail = $generalPrimary?->getEmail();
        if ($generalPrimaryEmail && trim($generalPrimaryEmail) !== '') {
            return trim($generalPrimaryEmail);
        }

        /** @var Address|null $generalAny */
        $generalAny = $this->entityManager->getRepository(Address::class)->findOneBy([
            'departmentId' => $department->getId(),
            'type' => 'general',
            'deletedAt' => null,
        ]);
        $generalAnyEmail = $generalAny?->getEmail();
        if ($generalAnyEmail && trim($generalAnyEmail) !== '') {
            return trim($generalAnyEmail);
        }

        return null;
    }

    /**
     * Public-Material-Settings aus department_setting laden.
     *
     * @return array{
     *   contact_email: string,
     *   contact_note: string,
     *   show_contact_form: bool,
     *   show_contact_email: bool,
     *   show_contact_note: bool
     * }
     */
    private function resolvePublicSettings(string $departmentId): array
    {
        /** @var DepartmentSetting[] $settings */
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)->findBy([
            'departmentId' => $departmentId,
        ]);

        $map = [];
        foreach ($settings as $setting) {
            $map[$setting->getSettingKey()] = $setting->getSettingValue();
        }

        $defaults = DepartmentSetting::getGeneralDefaults();
        $email = trim((string) ($map['general.public_contact_email'] ?? $defaults['general.public_contact_email']));
        $note = trim((string) ($map['general.public_contact_note'] ?? $defaults['general.public_contact_note']));

        return [
            'contact_email' => $email,
            'contact_note' => $note,
            'show_contact_form' => $this->parsePublicBoolSetting($map, 'general.public_show_contact_form', true),
            'show_contact_email' => $this->parsePublicBoolSetting($map, 'general.public_show_contact_email', true),
            'show_contact_note' => $this->parsePublicBoolSetting($map, 'general.public_show_contact_note', true),
        ];
    }

    /**
     * Öffentliche Kontakt-E-Mail für Versand (unabhängig von „E-Mail auf Seite anzeigen“).
     */
    public function getPublicRecipientEmailForDepartmentId(string $departmentId): ?string
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return null;
        }
        $publicSettings = $this->resolvePublicSettings($departmentId);

        return $this->getPublicRecipientEmailFromSettings($department, $publicSettings);
    }

    private function getPublicRecipientEmailFromSettings(Department $department, array $publicSettings): ?string
    {
        $fromSettings = trim((string) ($publicSettings['contact_email'] ?? ''));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return $this->resolvePublicEmail($department);
    }

    /**
     * Default true wenn Key fehlt (Abwärtskompatibilität). "0", "false", "no", "off" = aus.
     */
    private function parsePublicBoolSetting(array $map, string $key, bool $defaultIfMissing): bool
    {
        if (!array_key_exists($key, $map)) {
            return $defaultIfMissing;
        }
        $raw = strtolower(trim((string) $map[$key]));
        if ($raw === '') {
            return $defaultIfMissing;
        }
        if (in_array($raw, ['0', 'false', 'no', 'off', 'nein'], true)) {
            return false;
        }
        if (in_array($raw, ['1', 'true', 'yes', 'on', 'ja'], true)) {
            return true;
        }

        return $defaultIfMissing;
    }

    private function findActivePublicCodeEntry(string $entityType, string $publicCode): ?PublicCode
    {
        $normalized = trim($publicCode);
        if ($normalized === '') {
            return null;
        }

        /** @var PublicCode|null $codeEntry */
        $codeEntry = $this->entityManager->getRepository(PublicCode::class)->findOneBy([
            'entityType' => $entityType,
            'publicCode' => $normalized,
        ]);
        if (!$codeEntry || !$codeEntry->getIsPublic() || !$codeEntry->getIsActive()) {
            return null;
        }
        if ($codeEntry->getRevokedAt() !== null) {
            return null;
        }
        $expiresAt = $codeEntry->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTime()) {
            return null;
        }

        return $codeEntry;
    }

    /**
     * @param array{
     *   contact_email: string,
     *   contact_note: string,
     *   show_contact_form: bool,
     *   show_contact_email: bool,
     *   show_contact_note: bool
     * } $publicSettings
     *
     * @return array{
     *   show_contact_form: bool,
     *   show_contact_email: bool,
     *   show_contact_note: bool,
     *   can_deliver_message: bool
     * }
     */
    private function buildPublicUiPayload(string $departmentId, array $publicSettings, ?string $recipientEmail): array
    {
        return [
            'show_contact_form' => $publicSettings['show_contact_form'],
            'show_contact_email' => $publicSettings['show_contact_email'],
            'show_contact_note' => $publicSettings['show_contact_note'],
            'can_deliver_message' => $this->canDeliverPublicMessage($departmentId, $recipientEmail),
        ];
    }

}

