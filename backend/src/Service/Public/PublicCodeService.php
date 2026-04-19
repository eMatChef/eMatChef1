<?php

namespace App\Service\Public;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\PublicCode;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PublicCodeService
{
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

        return [
            'code' => $codeEntry->getPublicCode(),
            'entity_type' => 'batch',
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
            'public_ui' => [
                'show_contact_form' => $publicSettings['show_contact_form'],
                'show_contact_email' => $publicSettings['show_contact_email'],
                'show_contact_note' => $publicSettings['show_contact_note'],
                'can_deliver_message' => $this->canDeliverPublicMessage($department->getId(), $recipientEmail),
            ],
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
     * Massenartikel: öffentlicher QR über {@see ensureMaterialPublicCode} (pro Artikel), nicht pro Charge.
     *
     * Hier: QR an einer Batch-Zeile nur für serialisierte Einheiten (Seriennummer gesetzt) oder Sonderfälle
     * (z. B. Vorlagen/Combo), nicht für normale Massen-Chargen.
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

    public function buildMaterialPublicUrl(string $publicCode): string
    {
        return $this->publicQrBaseUrl . '/i/m/' . rawurlencode($publicCode);
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
        ]);
        $generalPrimaryEmail = $generalPrimary?->getEmail();
        if ($generalPrimaryEmail && trim($generalPrimaryEmail) !== '') {
            return trim($generalPrimaryEmail);
        }

        /** @var Address|null $generalAny */
        $generalAny = $this->entityManager->getRepository(Address::class)->findOneBy([
            'departmentId' => $department->getId(),
            'type' => 'general',
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

}

