<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassMailTemplate;
use App\Entity\DepartmentSetting;
use App\Util\GrossanlassContactName;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassMailMergeService
{
    public const CUSTOM_PLACEHOLDERS_SETTING = 'grossanlass.mail.custom_placeholders';
    public const GMAIL_ROUTING_SETTING = 'grossanlass.mail.gmail_routing';
    public const ZEITRAUM_TEXT_SETTING = 'grossanlass.mail.zeitraum_text';
    public const ZEITRAUM_ABSPRACHE = 'Der benötigte Zeitraum richtet sich nach dem jeweiligen Material und kann individuell abgestimmt werden.';

    /** @var list<string> */
    public const BUILTIN_PLACEHOLDERS = [
        'ANREDE',
        'FIRMA',
        'ANLASS',
        'ORT',
        'ZEITRAUMTEXT',
        'MATERIALLISTE',
        'BEREICHE',
        'ABSENDER',
        'REFERENZ',
        'EMAIL',
        'WEBSEITE',
        'WAS',
        'HINWEISE',
        'VORNAME',
        'NACHNAME',
        'KONTAKT',
        'TELEFON',
    ];

    /** Anfrage, Nachfassen, Präzisieren: Anlass-PDFs + Bedarf-Übersicht. */
    public const KINDS_WITH_ATTACHMENTS = [
        DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
        DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN,
        DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassMailAttachmentService $mailAttachments,
    ) {
    }

    public static function kindAttachesFiles(string $kind): bool
    {
        return in_array($kind, self::KINDS_WITH_ATTACHMENTS, true);
    }

    /**
     * @return array{
     *     templates: list<array{kind: string, subject: string, body: string}>,
     *     custom_placeholders: list<array{key: string, sample: string}>,
     *     gmail_routing: array<string, mixed>,
     *     zeitraum_text: string,
     *     attachments: list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int, url: string}>
     * }
     */
    public function listTemplates(Department $department): array
    {
        return [
            'templates' => $this->templateRows($department),
            'custom_placeholders' => $this->listCustomPlaceholders($department),
            'gmail_routing' => $this->getGmailRouting($department),
            'zeitraum_text' => $this->storedZeitraumText($department),
            'attachments' => $this->mailAttachments->listPublic($department),
        ];
    }

    /**
     * @return list<array{kind: string, subject: string, body: string}>
     */
    private function templateRows(Department $department): array
    {
        $this->ensureDefaults($department);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassMailTemplate::class)
            ->findBy(['departmentId' => $department->getId()]);
        $byKind = [];
        foreach ($rows as $row) {
            if ($row instanceof DepartmentGrossanlassMailTemplate) {
                $byKind[$row->getKind()] = $row;
            }
        }
        $out = [];
        foreach (DepartmentGrossanlassMailTemplate::KINDS as $kind) {
            $row = $byKind[$kind] ?? null;
            if (!$row instanceof DepartmentGrossanlassMailTemplate) {
                continue;
            }
            $out[] = [
                'kind' => $kind,
                'subject' => $row->getSubject(),
                'body' => $row->getBody(),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{kind?: string, subject?: string, body?: string}> $templates
     * @param list<array{key?: string, sample?: string}> $customPlaceholders
     * @param array<string, mixed>|null $gmailRouting
     * @return array{
     *     templates: list<array{kind: string, subject: string, body: string}>,
     *     custom_placeholders: list<array{key: string, sample: string}>,
     *     gmail_routing: array<string, mixed>,
     *     zeitraum_text: string,
     *     attachments: list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int, url: string}>
     * }
     */
    public function saveTemplates(
        Department $department,
        array $templates,
        array $customPlaceholders = [],
        ?array $gmailRouting = null,
        ?string $zeitraumText = null,
    ): array
    {
        $this->ensureDefaults($department);
        $keep = [
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
            DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN,
        ];
        foreach ($templates as $item) {
            $kind = (string) ($item['kind'] ?? '');
            if (!in_array($kind, DepartmentGrossanlassMailTemplate::KINDS, true)) {
                continue;
            }
            $row = $this->entityManager->getRepository(DepartmentGrossanlassMailTemplate::class)
                ->findOneBy(['departmentId' => $department->getId(), 'kind' => $kind]);
            if (!$row instanceof DepartmentGrossanlassMailTemplate) {
                $row = new DepartmentGrossanlassMailTemplate();
                $row->setDepartment($department);
                $row->setKind($kind);
                $this->entityManager->persist($row);
            }
            if (array_key_exists('subject', $item)) {
                $row->setSubject(trim((string) $item['subject']));
            }
            if (array_key_exists('body', $item)) {
                $row->setBody((string) $item['body']);
            }
            $keep[] = $kind;
        }
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassMailTemplate::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($existing as $row) {
            if (!$row instanceof DepartmentGrossanlassMailTemplate) {
                continue;
            }
            if ($row->getKind() === DepartmentGrossanlassMailTemplate::KIND_ANFRAGE
                || $row->getKind() === DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN
            ) {
                continue;
            }
            if (!in_array($row->getKind(), $keep, true)) {
                $this->entityManager->remove($row);
            }
        }
        $this->entityManager->flush();
        $this->saveCustomPlaceholders($department, $customPlaceholders);
        if ($gmailRouting !== null) {
            $this->saveGmailRouting($department, $gmailRouting);
        }
        if ($zeitraumText !== null) {
            $this->saveZeitraumText($department, $zeitraumText);
        }

        return $this->listTemplates($department);
    }

    /**
     * @return array{
     *     subject: string,
     *     body: string,
     *     to: string,
     *     placeholders: array<string, string>,
     *     attachment_filename: string|null,
     *     attachments: list<string>,
     *     positions: array{allowed: list<string>, other: list<string>, groups: list<array{category: string, items: list<string>}>}
     * }
     */
    public function preview(
        Department $department,
        ?DepartmentGrossanlassInquiry $inquiry,
        string $kind = DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
    ): array {
        $this->ensureDefaults($department);
        if (!in_array($kind, DepartmentGrossanlassMailTemplate::KINDS, true)) {
            $kind = DepartmentGrossanlassMailTemplate::KIND_ANFRAGE;
        }
        $template = $this->entityManager->getRepository(DepartmentGrossanlassMailTemplate::class)
            ->findOneBy(['departmentId' => $department->getId(), 'kind' => $kind]);
        $subjectTpl = $template?->getSubject() ?? '';
        $bodyTpl = $template?->getBody() ?? '';
        $vars = $this->placeholders($department, $inquiry, $kind);
        $attachments = $this->previewAttachmentNames($department, $inquiry, $kind);
        $body = $this->apply($bodyTpl, $vars);

        return [
            'subject' => $this->apply($subjectTpl, $vars),
            'body' => $body,
            'to' => $inquiry?->getEmail() ?? 'demo@firma.example',
            'placeholders' => $vars,
            'attachment_filename' => $attachments[0] ?? null,
            'attachments' => $attachments,
            'positions' => $this->bodyPositionCatalog($department, $inquiry),
        ];
    }

    /**
     * @param list<string> $inquiryIds
     * @return list<array{
     *     inquiry_id: string,
     *     subject: string,
     *     body: string,
     *     to: string,
     *     placeholders: array<string, string>,
     *     attachment_filename: string|null,
     *     attachments: list<string>,
     *     positions: array{allowed: list<string>, other: list<string>}
     * }>
     */
    public function previewMany(Department $department, array $inquiryIds, string $kind = DepartmentGrossanlassMailTemplate::KIND_ANFRAGE): array
    {
        $out = [];
        foreach ($inquiryIds as $inquiryId) {
            if (!is_string($inquiryId) || $inquiryId === '') {
                continue;
            }
            $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($inquiryId);
            if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
                continue;
            }
            $merged = $this->preview($department, $inquiry, $kind);
            $out[] = [
                'inquiry_id' => $inquiry->getId(),
                'subject' => $merged['subject'],
                'body' => $merged['body'],
                'to' => $merged['to'],
                'placeholders' => $merged['placeholders'],
                'attachment_filename' => $merged['attachment_filename'],
                'attachments' => $merged['attachments'],
                'positions' => $merged['positions'],
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function previewAttachmentNames(
        Department $department,
        ?DepartmentGrossanlassInquiry $inquiry,
        string $kind,
    ): array {
        if (!self::kindAttachesFiles($kind)) {
            return [];
        }
        $names = $this->mailAttachments->previewNames($department);
        $material = $this->materialListAttachmentName($department, $inquiry);
        if ($material !== null) {
            $names[] = $material;
        }

        return $names;
    }

    public function materialListAttachmentName(
        Department $department,
        ?DepartmentGrossanlassInquiry $inquiry,
    ): ?string {
        if ($inquiry === null || $this->materialItemsGrouped($department, $inquiry) === []) {
            return null;
        }

        return self::materialListFilename($inquiry->getName());
    }

    public static function materialListFilename(string $firmName): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $firmName) ?? 'Firma';
        $slug = trim($slug, '-') ?: 'Firma';

        return 'Materialliste-' . mb_substr($slug, 0, 40) . '.pdf';
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(
        Department $department,
        ?DepartmentGrossanlassInquiry $inquiry,
        string $kind = DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
    ): array {
        $tree = $this->categoryTreeRows($department);
        $itemNames = $inquiry
            ? self::mailItemNames($tree, $inquiry->getCategoryIds())
            : self::sampleMailItemNames($tree);
        $groups = $this->materialItemsGrouped($department, $inquiry);
        $areaNames = self::areaNamesFromGroupedItems($groups);
        if ($areaNames === []) {
            $areaNames = $inquiry
                ? self::mailAreaNames($tree, $inquiry->getCategoryIds())
                : self::sampleMailAreaNames($tree);
        }
        $packages = self::formatGermanNameList($areaNames);
        if ($packages === '') {
            $packages = 'Bereiche folgen';
        }
        $materialList = self::formatGermanNameList($itemNames);
        $areaOnly = in_array($kind, [
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
            DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN,
            DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN,
        ], true);
        if ($areaOnly) {
            $fromBedarf = self::formatGroupedMaterialListHtml($groups);
            $materialList = $fromBedarf !== '' ? $fromBedarf : 'Positionen folgen';
        } else {
            $withQty = $this->materialListForInquiry($department, $inquiry);
            if ($withQty !== '') {
                $materialList = $withQty;
            } elseif ($materialList === '') {
                $materialList = $packages;
            }
        }
        $id = $inquiry?->getId() ?? '____________';
        $reference = $this->displayReference($department, $id);
        $names = GrossanlassContactName::mailParts(
            $inquiry?->getContactFirstName() ?? '',
            $inquiry?->getContactLastName() ?? '',
            $inquiry?->getContactName() ?? '',
            $inquiry?->getContactSalutation() ?? '',
        );

        $vars = [
            'ANREDE' => $names['ANREDE'],
            'FIRMA' => $inquiry?->getName() ?? 'Muster AG',
            'ANLASS' => $department->getName(),
            'ORT' => $inquiry?->getPlace() ?? '',
            'ZEITRAUMTEXT' => self::zeitraumTextForMail($this->getZeitraumText($department)),
            'MATERIALLISTE' => $materialList,
            'BEREICHE' => $packages,
            'ABSENDER' => 'OK Material & Logistik',
            'REFERENZ' => $reference,
            'EMAIL' => $inquiry?->getEmail() ?? '',
            'WEBSEITE' => $inquiry?->getWebsite() ?? '',
            'WAS' => $inquiry?->getOffering() ?? '',
            'HINWEISE' => $inquiry?->getNotes() ?? '',
            'VORNAME' => $names['VORNAME'],
            'NACHNAME' => $names['NACHNAME'],
            'KONTAKT' => $names['KONTAKT'],
            'TELEFON' => $inquiry?->getPhone() ?? '',
        ];
        foreach ($this->listCustomPlaceholders($department) as $row) {
            $key = $row['key'];
            if ($key === '' || isset($vars[$key])) {
                continue;
            }
            $vars[$key] = $row['sample'] !== '' ? $row['sample'] : '{{' . $key . '}}';
        }

        return $vars;
    }

    /**
     * @return list<array{key: string, sample: string}>
     */
    public function listCustomPlaceholders(Department $department): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::CUSTOM_PLACEHOLDERS_SETTING]);
        if (!$setting instanceof DepartmentSetting) {
            return [];
        }
        $decoded = json_decode($setting->getSettingValue(), true);

        return $this->normalizeCustomPlaceholders(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param list<array{key?: string, sample?: string}|mixed> $items
     */
    public function saveCustomPlaceholders(Department $department, array $items): void
    {
        $normalized = $this->normalizeCustomPlaceholders($items);
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::CUSTOM_PLACEHOLDERS_SETTING]);
        if (!$setting instanceof DepartmentSetting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::CUSTOM_PLACEHOLDERS_SETTING);
            $this->entityManager->persist($setting);
        }
        $setting->setSettingValue(json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: '[]');
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public function getZeitraumText(Department $department): string
    {
        $stored = $this->storedZeitraumText($department);

        return $stored !== '' ? $stored : self::ZEITRAUM_ABSPRACHE;
    }

    public function storedZeitraumText(Department $department): string
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::ZEITRAUM_TEXT_SETTING]);
        if (!$setting instanceof DepartmentSetting) {
            return '';
        }
        $raw = $setting->getSettingValue();
        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            return trim($decoded);
        }

        return trim($raw);
    }

    public function saveZeitraumText(Department $department, string $text): void
    {
        $trimmed = mb_substr(trim($text), 0, 2000);
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::ZEITRAUM_TEXT_SETTING]);
        if (!$setting instanceof DepartmentSetting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::ZEITRAUM_TEXT_SETTING);
            $this->entityManager->persist($setting);
        }
        $setting->setSettingValue(json_encode($trimmed, JSON_UNESCAPED_UNICODE) ?: '""');
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public static function zeitraumTextForMail(string $plain): string
    {
        $text = trim($plain);
        if ($text === '') {
            $text = self::ZEITRAUM_ABSPRACHE;
        }

        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }

    /**
     * @param list<mixed> $items
     * @return list<array{key: string, sample: string}>
     */
    private function normalizeCustomPlaceholders(array $items): array
    {
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = strtoupper((string) preg_replace('/[^A-Za-z0-9_]/', '_', (string) ($item['key'] ?? '')));
            $key = trim($key, '_');
            if ($key === '' || isset($seen[$key]) || in_array($key, self::BUILTIN_PLACEHOLDERS, true)) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'sample' => mb_substr(trim((string) ($item['sample'] ?? '')), 0, 500),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *     label_root: string,
     *     label_inquiries: string,
     *     label_waiting: string,
     *     label_replied: string,
     *     label_by_package: bool,
     *     extra_labels: list<string>,
     *     reference_prefix: string
     * }
     */
    public function getGmailRouting(Department $department): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::GMAIL_ROUTING_SETTING]);
        $decoded = [];
        if ($setting instanceof DepartmentSetting) {
            $raw = json_decode($setting->getSettingValue(), true);
            $decoded = is_array($raw) ? $raw : [];
        }

        $normalized = GrossanlassGmailRouting::normalize($decoded);
        $normalized['label_root'] = GrossanlassGmailRouting::resolveRoot($normalized, $department->getName());

        return $normalized;
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function saveGmailRouting(Department $department, array $raw): void
    {
        $normalized = GrossanlassGmailRouting::normalize($raw);
        $normalized['label_root'] = GrossanlassGmailRouting::resolveRoot($normalized, $department->getName());
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::GMAIL_ROUTING_SETTING]);
        if (!$setting instanceof DepartmentSetting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::GMAIL_ROUTING_SETTING);
            $this->entityManager->persist($setting);
        }
        $setting->setSettingValue(json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: '{}');
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public function displayReference(Department $department, string $inquiryId): string
    {
        $routing = $this->getGmailRouting($department);

        return GrossanlassGmailRouting::displayReference($routing['reference_prefix'], $inquiryId);
    }

    /**
     * @return list<string>
     */
    public function gmailLabelNames(
        Department $department,
        DepartmentGrossanlassInquiry $inquiry,
        ?string $status = null,
    ): array {
        return GrossanlassGmailRouting::labelNames(
            $this->getGmailRouting($department),
            $department->getName(),
            $this->resolvePackageLabels($department, $inquiry->getCategoryIds()),
            $status ?? GrossanlassGmailRouting::STATUS_WAITING,
            $inquiry->getStatus(),
        );
    }

    /**
     * @return list<string>
     */
    public function allCategoryNames(Department $department): array
    {
        return $this->allPackageNames($department);
    }

    /**
     * Nicht-Blätter für Gmail-Labels (Werkzeuge/Handwerkzeuge), keine Artikel.
     *
     * @return list<string>
     */
    public function allPackageNames(Department $department): array
    {
        $rows = $this->categoryTreeRows($department);
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }
        $paths = [];
        foreach ($rows as $row) {
            if (self::isItemRow($row)) {
                continue;
            }
            $path = self::namePath($byId, $row['id']);
            if ($path !== '') {
                $paths[$path] = true;
            }
        }

        return array_keys($paths);
    }

    /**
     * Paket-Pfade für Gmail-Labels (Bereich/Unterbereich), ohne Artikelblätter.
     *
     * @param list<string> $categoryIds
     * @return list<string>
     */
    public function resolvePackageLabels(Department $department, array $categoryIds): array
    {
        return self::packagePaths($this->categoryTreeRows($department), $categoryIds);
    }

    /**
     * @param list<string> $categoryIds
     * @return list<string>
     */
    public function resolveCategoryLabels(Department $department, array $categoryIds): array
    {
        return self::mailItemNames($this->categoryTreeRows($department), $categoryIds);
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @param list<string> $selectedIds
     * @return list<string>
     */
    public static function mailItemNames(array $rows, array $selectedIds): array
    {
        $wanted = self::wantedLeafIds($rows, $selectedIds);
        if ($wanted === []) {
            return [];
        }
        $ordered = [];
        foreach (self::walkTreeIds($rows) as $id) {
            if (isset($wanted[$id])) {
                $name = trim((string) ($wanted[$id]['name'] ?? ''));
                if ($name !== '') {
                    $ordered[] = $name;
                }
            }
        }

        return array_values(array_unique($ordered));
    }

    /**
     * Grobe Wurzelbereiche der Auswahl (Werkzeuge, Infrastruktur), nicht die Unterbereiche.
     *
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @param list<string> $selectedIds
     * @return list<string>
     */
    public static function mailAreaNames(array $rows, array $selectedIds): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }
        $rootIds = [];
        foreach (array_keys(self::wantedLeafIds($rows, $selectedIds)) as $leafId) {
            $current = $byId[$leafId] ?? null;
            $seen = [];
            while ($current !== null && !isset($seen[$current['id']])) {
                $seen[$current['id']] = true;
                $parentId = $current['parentId'] ?? null;
                if (!is_string($parentId) || $parentId === '' || !isset($byId[$parentId])) {
                    $rootIds[$current['id']] = true;
                    break;
                }
                $current = $byId[$parentId];
            }
        }
        $names = [];
        foreach (self::walkTreeIds($rows) as $id) {
            if (!isset($rootIds[$id])) {
                continue;
            }
            $name = trim((string) ($byId[$id]['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @return list<string>
     */
    public static function sampleMailAreaNames(array $rows, int $limit = 8): array
    {
        $names = [];
        foreach ($rows as $row) {
            if (($row['parentId'] ?? null) !== null) {
                continue;
            }
            $name = trim($row['name']);
            if ($name === '' || $name === ActivityGrossanlassProcurementCategory::JS_NAME) {
                continue;
            }
            $names[] = $name;
            if (count($names) >= $limit) {
                break;
            }
        }

        return $names;
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @return list<string>
     */
    public static function sampleMailItemNames(array $rows, int $limit = 8): array
    {
        $names = [];
        foreach (self::walkTreeIds($rows) as $id) {
            if (self::childrenOf($rows, $id) !== []) {
                continue;
            }
            foreach ($rows as $row) {
                if ($row['id'] !== $id) {
                    continue;
                }
                if (self::isItemRow($row)) {
                    break;
                }
                $name = trim($row['name']);
                if ($name !== '' && $name !== ActivityGrossanlassProcurementCategory::JS_NAME) {
                    $names[] = $name;
                }
                break;
            }
            if (count($names) >= $limit) {
                break;
            }
        }

        return $names;
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @param list<string> $selectedIds
     * @return list<string>
     */
    public static function packagePaths(array $rows, array $selectedIds): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }
        $paths = [];
        foreach (array_keys(self::wantedLeafIds($rows, $selectedIds)) as $leafId) {
            $leaf = $byId[$leafId] ?? null;
            if ($leaf === null) {
                continue;
            }
            $nodeId = $leafId;
            $parentId = $leaf['parentId'] ?? null;
            if (self::isItemRow($leaf) && is_string($parentId) && $parentId !== '') {
                $nodeId = $parentId;
            }
            $path = self::namePath($byId, $nodeId);
            if ($path !== '') {
                $paths[$path] = true;
            }
        }

        return array_keys($paths);
    }

    /**
     * @param list<string> $names
     */
    public static function formatGermanNameList(array $names): string
    {
        $clean = [];
        foreach ($names as $name) {
            $trimmed = trim((string) $name);
            if ($trimmed !== '') {
                $clean[] = htmlspecialchars($trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        $n = count($clean);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $clean[0];
        }
        if ($n === 2) {
            return $clean[0] . ' sowie ' . $clean[1];
        }

        return implode(', ', array_slice($clean, 0, -1)) . ' sowie ' . $clean[$n - 1];
    }

    /**
     * Positionen aus Beschaffung → Bedarf für die angefragten Bereiche.
     */
    public function materialListForInquiry(Department $department, ?DepartmentGrossanlassInquiry $inquiry): string
    {
        $selected = $inquiry !== null
            ? $this->expandSelectedCategoryIds($department, $inquiry->getCategoryIds())
            : null;
        if ($inquiry !== null && $selected === []) {
            return '';
        }

        /** @var list<ActivityGrossanlassProcurementLine> $lines */
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->createQueryBuilder('l')
            ->leftJoin('l.category', 'c')
            ->addSelect('c')
            ->where('l.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('l.label', 'ASC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            $categoryId = $line->getCategoryId();
            if ($selected !== null && ($categoryId === null || !isset($selected[$categoryId]))) {
                continue;
            }
            $items[] = [
                'quantity' => $line->getQuantity(),
                'label' => $line->getLabel(),
            ];
        }

        return self::formatMaterialListHtml($items);
    }

    /**
     * Bedarfspositionen der Firmen-Bereiche, gruppiert — ohne Stückzahl (Anhang Folge-Mail).
     *
     * @return list<array{category: string, area: string, items: list<string>}>
     */
    public function materialItemsGrouped(Department $department, ?DepartmentGrossanlassInquiry $inquiry): array
    {
        $selected = $inquiry !== null
            ? $this->expandSelectedCategoryIds($department, $inquiry->getCategoryIds())
            : null;
        if ($inquiry !== null && $selected === []) {
            return [];
        }

        /** @var list<ActivityGrossanlassProcurementLine> $lines */
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->createQueryBuilder('l')
            ->leftJoin('l.category', 'c')
            ->leftJoin('c.parent', 'p')
            ->addSelect('c', 'p')
            ->where('l.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('l.label', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            $categoryId = $line->getCategoryId();
            if ($selected !== null && ($categoryId === null || !isset($selected[$categoryId]))) {
                continue;
            }
            $label = trim($line->getLabel());
            if ($label === '') {
                continue;
            }
            $category = $line->getCategory();
            $key = $categoryId ?? '';
            if (!isset($grouped[$key])) {
                $name = $category?->getName() ?: 'Ohne Bereich';
                $parent = $category?->getParent();
                $grouped[$key] = [
                    'category' => $name,
                    'area' => $parent?->getName() ?: $name,
                    'items' => [],
                ];
            }
            $grouped[$key]['items'][$label] = true;
        }
        $out = [];
        foreach ($this->categoriesForDepartment($department) as $category) {
            $key = $category->getId();
            if (!isset($grouped[$key])) {
                continue;
            }
            $out[] = [
                'category' => $category->getName(),
                'area' => $category->getParent()?->getName() ?: $category->getName(),
                'items' => array_keys($grouped[$key]['items']),
            ];
            unset($grouped[$key]);
        }
        foreach ($grouped as $rest) {
            $out[] = [
                'category' => $rest['category'],
                'area' => $rest['area'],
                'items' => array_keys($rest['items']),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{category?: string, area?: string, items?: list<string>}> $groups
     * @return list<string>
     */
    public static function areaNamesFromGroupedItems(array $groups): array
    {
        $names = [];
        foreach ($groups as $group) {
            $area = trim((string) ($group['area'] ?? $group['category'] ?? ''));
            if ($area === '' || isset($names[$area])) {
                continue;
            }
            $names[$area] = true;
        }

        return array_keys($names);
    }

    /**
     * @param list<array{quantity?: int, label?: string}> $items
     */
    public static function formatMaterialListHtml(array $items, bool $withQuantity = true): string
    {
        $parts = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 0);
            $line = ($withQuantity && $qty > 0) ? $qty . '× ' . $label : $label;
            $parts[] = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return implode('<br>', $parts);
    }

    /**
     * Kategorie mit gewünschten Artikeln darunter — ohne Stückzahl.
     *
     * @param list<array{category: string, items: list<string>}> $groups
     */
    public static function formatGroupedMaterialListHtml(array $groups): string
    {
        $blocks = [];
        foreach ($groups as $group) {
            $category = trim((string) ($group['category'] ?? ''));
            $names = [];
            foreach ($group['items'] ?? [] as $item) {
                $name = trim((string) $item);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            if ($category === '' || $names === []) {
                continue;
            }
            $blocks[] = '<strong>' . htmlspecialchars($category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</strong><br>' . self::formatGermanNameList($names);
        }

        return implode('<br><br>', $blocks);
    }

    /**
     * Namen, die in der Anfrage-Mail stehen dürfen (Bedarfspositionen der gewählten
     * Kategorien), und andere Bedarfspositionen dieses Anlasses.
     *
     * @return array{
     *     allowed: list<string>,
     *     other: list<string>,
     *     groups: list<array{category: string, items: list<string>}>
     * }
     */
    public function bodyPositionCatalog(Department $department, ?DepartmentGrossanlassInquiry $inquiry): array
    {
        $groups = $this->materialItemsGrouped($department, $inquiry);
        $allowedMap = [];
        $groupRows = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                $label = trim((string) $item);
                if ($label === '') {
                    continue;
                }
                $items[] = $label;
                if (self::isMatchablePositionName($label)) {
                    $allowedMap[$label] = true;
                }
            }
            if ($items !== []) {
                $groupRows[] = [
                    'category' => $group['category'],
                    'items' => $items,
                ];
            }
        }
        $otherMap = [];
        /** @var list<ActivityGrossanlassProcurementLine> $lines */
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            $label = trim($line->getLabel());
            if (!self::isMatchablePositionName($label) || isset($allowedMap[$label])) {
                continue;
            }
            $otherMap[$label] = true;
        }

        return [
            'allowed' => array_keys($allowedMap),
            'other' => array_keys($otherMap),
            'groups' => $groupRows,
        ];
    }

    public function assertInquiryMailPositions(
        Department $department,
        DepartmentGrossanlassInquiry $inquiry,
        string $body,
    ): void {
        $catalog = $this->bodyPositionCatalog($department, $inquiry);
        $match = self::matchBodyPositions($body, $catalog['allowed'], $catalog['other']);
        if ($match['unexpected'] !== []) {
            throw new \InvalidArgumentException(
                'Der Text fragt Positionen an, die für diese Firma nicht gewählt sind: '
                . implode(', ', $match['unexpected']),
            );
        }
    }

    /**
     * @param list<string> $allowed
     * @param list<string> $other
     * @return array{mentioned: list<string>, omitted: list<string>, unexpected: list<string>}
     */
    public static function matchBodyPositions(string $body, array $allowed, array $other): array
    {
        $plain = self::htmlToPlainForMatch($body);
        $mentioned = self::findMentionedNames($plain, $allowed);
        $mentionedSet = array_fill_keys($mentioned, true);
        $omitted = [];
        foreach ($allowed as $name) {
            $name = trim($name);
            if ($name === '' || isset($mentionedSet[$name])) {
                continue;
            }
            $omitted[] = $name;
        }

        return [
            'mentioned' => $mentioned,
            'omitted' => $omitted,
            'unexpected' => self::findMentionedNames($plain, $other),
        ];
    }

    /**
     * @param list<array{category: string, items: list<string>}> $groups
     * @param list<string>|null $labels null = alle Positionen
     * @return list<array{category: string, items: list<string>}>
     */
    public static function filterGroupedItemsByLabels(array $groups, ?array $labels): array
    {
        if ($labels === null) {
            return $groups;
        }
        $want = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $want[$label] = true;
            }
        }
        $out = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                $item = trim((string) $item);
                if ($item !== '' && isset($want[$item])) {
                    $items[] = $item;
                }
            }
            if ($items === []) {
                continue;
            }
            $out[] = [
                'category' => $group['category'],
                'items' => $items,
            ];
        }

        return $out;
    }

    /**
     * PDF-Positionen: Override (Schnitt mit erlaubt) oder der Rest aus dem Mailtext, sonst alle.
     *
     * @param list<string> $allowed
     * @param list<string> $omitted
     * @param list<mixed>|null $override
     * @return list<string>
     */
    public static function resolveAttachmentItemLabels(array $allowed, array $omitted, ?array $override): array
    {
        $allowedSet = [];
        foreach ($allowed as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $allowedSet[$label] = true;
            }
        }
        if ($override !== null) {
            $out = [];
            foreach ($override as $raw) {
                $label = trim((string) $raw);
                if ($label !== '' && isset($allowedSet[$label]) && !in_array($label, $out, true)) {
                    $out[] = $label;
                }
            }

            return $out;
        }
        if ($omitted !== []) {
            $out = [];
            foreach ($omitted as $raw) {
                $label = trim((string) $raw);
                if (isset($allowedSet[$label]) && !in_array($label, $out, true)) {
                    $out[] = $label;
                }
            }

            return $out;
        }

        return array_keys($allowedSet);
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    public static function findMentionedNames(string $plain, array $names): array
    {
        $unique = [];
        foreach ($names as $name) {
            $trimmed = trim((string) $name);
            if (!self::isMatchablePositionName($trimmed)) {
                continue;
            }
            $unique[$trimmed] = true;
        }
        $list = array_keys($unique);
        usort($list, static fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        $found = [];
        $haystack = $plain;
        foreach ($list as $name) {
            $pattern = '/(?<![\p{L}\p{N}\-])' . preg_quote($name, '/') . '(?![\p{L}\p{N}\-])/iu';
            if (preg_match($pattern, $haystack) !== 1) {
                continue;
            }
            $found[] = $name;
            $haystack = preg_replace($pattern, ' ', $haystack, 1) ?? $haystack;
        }

        return $found;
    }

    public static function quoteMention(string $plain, string $name, int $pad = 42): string
    {
        $pattern = '/.{0,' . $pad . '}' . preg_quote($name, '/') . '.{0,' . $pad . '}/iu';
        if (preg_match($pattern, $plain, $match) === 1) {
            return trim($match[0]);
        }

        return $name;
    }

    public static function htmlToPlainForMatch(string $html): string
    {
        $text = str_ireplace(
            ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</h2>', '</h3>', '</strong>'],
            "\n",
            $html,
        );
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public static function isMatchablePositionName(string $name): bool
    {
        return mb_strlen(trim($name)) >= 3;
    }

    /**
     * @param list<string> $tokens
     * @return array<string, true>
     */
    private function expandSelectedCategoryIds(Department $department, array $tokens): array
    {
        $all = $this->categoriesForDepartment($department);
        $selected = [];
        foreach ($tokens as $raw) {
            $token = trim((string) $raw);
            if ($token === '') {
                continue;
            }
            $lower = mb_strtolower($token, 'UTF-8');
            foreach ($all as $row) {
                if ($row->getId() === $token || mb_strtolower($row->getName(), 'UTF-8') === $lower) {
                    $selected[$row->getId()] = true;
                }
            }
        }
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($all as $row) {
                $parentId = $row->getParentId();
                if ($parentId === null || !isset($selected[$parentId]) || isset($selected[$row->getId()])) {
                    continue;
                }
                $selected[$row->getId()] = true;
                $changed = true;
            }
        }

        return $selected;
    }

    /**
     * @return list<array{id: string, parentId: string|null, name: string}>
     */
    private function categoryTreeRows(Department $department): array
    {
        $out = [];
        foreach ($this->categoriesForDepartment($department) as $row) {
            $out[] = [
                'id' => $row->getId(),
                'parentId' => $row->getParentId(),
                'name' => $row->getName(),
                'kind' => $row->getKind(),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @param list<string> $selectedIds
     * @return array<string, array{id: string, parentId: string|null, name: string}>
     */
    private static function wantedLeafIds(array $rows, array $selectedIds): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }
        $selected = [];
        foreach ($selectedIds as $raw) {
            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }
            if (isset($byId[$value])) {
                $selected[$value] = true;
                continue;
            }
            $lower = mb_strtolower($value, 'UTF-8');
            foreach ($rows as $row) {
                if (mb_strtolower($row['name'], 'UTF-8') === $lower) {
                    $selected[$row['id']] = true;
                }
            }
        }
        $wanted = [];
        foreach (array_keys($selected) as $id) {
            $leaves = self::descendantLeaves($rows, $id);
            if ($leaves === []) {
                continue;
            }
            $picked = [];
            foreach ($leaves as $leaf) {
                if (isset($selected[$leaf['id']])) {
                    $picked[] = $leaf;
                }
            }
            foreach ($picked !== [] ? $picked : $leaves as $leaf) {
                $wanted[$leaf['id']] = $leaf;
            }
        }

        return $wanted;
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @return list<array{id: string, parentId: string|null, name: string}>
     */
    private static function descendantLeaves(array $rows, string $rootId): array
    {
        $leaves = [];
        $walk = function (string $id) use (&$walk, &$leaves, $rows): void {
            $children = self::childrenOf($rows, $id);
            if ($children === []) {
                foreach ($rows as $row) {
                    if ($row['id'] === $id) {
                        $leaves[] = $row;
                        break;
                    }
                }

                return;
            }
            foreach ($children as $child) {
                $walk($child['id']);
            }
        };
        $walk($rootId);

        return $leaves;
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @return list<array{id: string, parentId: string|null, name: string}>
     */
    private static function childrenOf(array $rows, ?string $parentId): array
    {
        $children = [];
        foreach ($rows as $row) {
            if (($row['parentId'] ?? null) === $parentId) {
                $children[] = $row;
            }
        }

        return $children;
    }

    /**
     * @param list<array{id: string, parentId: string|null, name: string}> $rows
     * @return list<string>
     */
    private static function walkTreeIds(array $rows): array
    {
        $ids = [];
        $walk = function (?string $parentId) use (&$walk, &$ids, $rows): void {
            foreach (self::childrenOf($rows, $parentId) as $row) {
                $ids[] = $row['id'];
                $walk($row['id']);
            }
        };
        $walk(null);
        foreach ($rows as $row) {
            if (!in_array($row['id'], $ids, true)) {
                $ids[] = $row['id'];
            }
        }

        return $ids;
    }

    /**
     * @param array<string, array{id: string, parentId: string|null, name: string}> $byId
     */
    private static function namePath(array $byId, string $id): string
    {
        $parts = [];
        $current = $id;
        $seen = [];
        while ($current !== '' && isset($byId[$current]) && !isset($seen[$current])) {
            $seen[$current] = true;
            $name = GrossanlassGmailRouting::sanitizeSegment($byId[$current]['name']);
            if ($name !== '') {
                array_unshift($parts, $name);
            }
            $parent = $byId[$current]['parentId'] ?? null;
            $current = is_string($parent) ? $parent : '';
        }

        return implode('/', $parts);
    }

    /**
     * @param array{id?: string, parentId?: string|null, name?: string, kind?: string} $row
     */
    private static function isItemRow(array $row): bool
    {
        return ($row['kind'] ?? '') === ActivityGrossanlassProcurementCategory::KIND_ITEM;
    }

    public function categoryPackagePath(ActivityGrossanlassProcurementCategory $category): string
    {
        return self::categoryPackagePathFromEntity($category);
    }

    public static function categoryPackagePathFromEntity(ActivityGrossanlassProcurementCategory $category): string
    {
        $parts = [];
        $current = $category;
        $seen = [];
        while ($current instanceof ActivityGrossanlassProcurementCategory) {
            $id = $current->getId();
            if (isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;
            $name = GrossanlassGmailRouting::sanitizeSegment($current->getName());
            if ($name !== '') {
                array_unshift($parts, $name);
            }
            $current = $current->getParent();
        }

        return implode('/', $parts);
    }

    /**
     * @param array{name?: string, parent_name?: string|null, path?: string|null} $row
     */
    public static function categoryPackagePathFromRow(array $row): string
    {
        $path = GrossanlassGmailRouting::sanitizePath((string) ($row['path'] ?? ''));
        if ($path !== '') {
            return $path;
        }
        $name = GrossanlassGmailRouting::sanitizeSegment((string) ($row['name'] ?? ''));
        if ($name === '') {
            return '';
        }
        $parent = GrossanlassGmailRouting::sanitizeSegment((string) ($row['parent_name'] ?? ''));

        return $parent !== '' ? $parent . '/' . $name : $name;
    }

    /**
     * @return list<ActivityGrossanlassProcurementCategory>
     */
    private function categoriesForDepartment(Department $department): array
    {
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->findBy(['departmentId' => $department->getId()], ['sortOrder' => 'ASC', 'name' => 'ASC']);
        $out = [];
        foreach ($rows as $row) {
            if ($row instanceof ActivityGrossanlassProcurementCategory) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array<string, string> $vars
     */
    public function apply(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }

    public function ensureDefaults(Department $department): void
    {
        $defaults = $this->defaultTexts($department->getName());
        $changed = false;
        foreach ([
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
            DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN,
        ] as $kind) {
            $pair = $defaults[$kind] ?? null;
            if ($pair === null) {
                continue;
            }
            $existing = $this->entityManager->getRepository(DepartmentGrossanlassMailTemplate::class)
                ->findOneBy(['departmentId' => $department->getId(), 'kind' => $kind]);
            if ($existing instanceof DepartmentGrossanlassMailTemplate) {
                continue;
            }
            $row = new DepartmentGrossanlassMailTemplate();
            $row->setDepartment($department);
            $row->setKind($kind);
            $row->setSubject($pair['subject']);
            $row->setBody($pair['body']);
            $this->entityManager->persist($row);
            $changed = true;
        }
        $existingRows = $this->entityManager->getRepository(DepartmentGrossanlassMailTemplate::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($existingRows as $row) {
            if (!$row instanceof DepartmentGrossanlassMailTemplate) {
                continue;
            }
            $pair = $defaults[$row->getKind()] ?? null;
            if ($pair === null) {
                continue;
            }
            if (!$this->isLegacyInquiryBody($row->getKind(), $row->getBody())) {
                continue;
            }
            $row->setBody($pair['body']);
            $changed = true;
        }
        foreach ($existingRows as $row) {
            if (!$row instanceof DepartmentGrossanlassMailTemplate) {
                continue;
            }
            $body = $row->getBody();
            if (!str_contains($body, self::ZEITRAUM_ABSPRACHE)) {
                continue;
            }
            $row->setBody(str_replace(self::ZEITRAUM_ABSPRACHE, '{{ZEITRAUMTEXT}}', $body));
            $changed = true;
        }
        if ($changed) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array<string, array{subject: string, body: string}>
     */
    private function defaultTexts(string $eventName): array
    {
        $footer = self::mailFooterHtml();
        $positions = self::mailPositionsHtml();

        return [
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE => [
                'subject' => $eventName . ' – Anfrage Material & Logistik',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir fragen an, ob {{FIRMA}} uns für {{ANLASS}} unterstützen kann.</p>' . $positions . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN => [
                'subject' => $eventName . ' – Materialliste',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Rückmeldung von {{FIRMA}}. Im Anhang die genaue Liste der Positionen — ohne Stückzahlen, die klären wir danach.</p><p>{{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_DANK_ABSAGE => [
                'subject' => $eventName . ' – Danke für die Rückmeldung',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Rückmeldung von {{FIRMA}}. Wir haben die Absage notiert.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_ZUSAGE_OK => [
                'subject' => $eventName . ' – Zusage bestätigt',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Zusage von {{FIRMA}}. Wir haben notiert: {{MATERIALLISTE}}.</p><p>{{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NICHT_GENOMMEN => [
                'subject' => $eventName . ' – Zusammenarbeit dieses Mal nicht',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>herzlichen Dank für die Zusage von {{FIRMA}}. Für dieses Paket nehmen wir eine andere Lösung. Wir melden uns gerne bei einem nächsten Anlass.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NEHMEN => [
                'subject' => $eventName . ' – Wir rechnen mit euch',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir nehmen das Angebot von {{FIRMA}} gerne an.</p><p>{{MATERIALLISTE}}</p><p>{{ZEITRAUMTEXT}}</p><p>Nächste Schritte folgen in diesem Thread.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN => [
                'subject' => $eventName . ' – Kurze Nachfrage',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir möchten kurz nachfassen, ob unsere Anfrage an {{FIRMA}} angekommen ist.</p>' . $positions . $footer,
            ],
        ];
    }

    private static function mailFooterHtml(): string
    {
        return '<p>Freundliche Grüsse<br>{{ABSENDER}}</p><p>Referenz {{REFERENZ}}</p>';
    }

    private static function mailPositionsHtml(): string
    {
        return '<p>Aktuell suchen wir insbesondere Unterstützung bei folgenden Positionen:</p><p>{{MATERIALLISTE}}</p>'
            . '<p>Die genauen Ausführungen, Dimensionen und benötigten Mengen befinden sich derzeit noch in Planung und würden wir bei grundsätzlichem Interesse gemeinsam mit Ihnen konkretisieren.</p>'
            . '<p>{{ZEITRAUMTEXT}}</p>';
    }

    private function isLegacyInquiryBody(string $kind, string $body): bool
    {
        $footer = self::mailFooterHtml();
        $legacy = match ($kind) {
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE => [
                '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir fragen an, ob {{FIRMA}} uns für {{ANLASS}} im Bereich {{BEREICHE}} unterstützen kann.</p><p>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN => [
                '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Rückmeldung von {{FIRMA}}. Im Anhang die genaue Liste zu {{BEREICHE}} — ohne Stückzahlen, die klären wir danach.</p><p>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN => [
                '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir möchten kurz nachfassen, ob unsere Anfrage an {{FIRMA}} angekommen ist.</p><p>Bereiche: {{BEREICHE}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_ZUSAGE_OK => [
                '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Zusage von {{FIRMA}}. Wir haben notiert: {{BEREICHE}}.</p><p>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NEHMEN => [
                '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir nehmen das Angebot von {{FIRMA}} gerne an.</p><p>Bereiche: {{BEREICHE}}<br>Zeitraum: {{ZEITRAUMTEXT}}</p><p>Nächste Schritte folgen in diesem Thread.</p>' . $footer,
            ],
            default => [],
        };

        return in_array($body, $legacy, true);
    }
}
