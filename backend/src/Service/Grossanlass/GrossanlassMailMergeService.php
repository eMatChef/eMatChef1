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

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{
     *     templates: list<array{kind: string, subject: string, body: string}>,
     *     custom_placeholders: list<array{key: string, sample: string}>,
     *     gmail_routing: array<string, mixed>
     * }
     */
    public function listTemplates(Department $department): array
    {
        return [
            'templates' => $this->templateRows($department),
            'custom_placeholders' => $this->listCustomPlaceholders($department),
            'gmail_routing' => $this->getGmailRouting($department),
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
     *     gmail_routing: array<string, mixed>
     * }
     */
    public function saveTemplates(
        Department $department,
        array $templates,
        array $customPlaceholders = [],
        ?array $gmailRouting = null,
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

        return $this->listTemplates($department);
    }

    /**
     * @return array{subject: string, body: string, to: string, placeholders: array<string, string>, attachment_filename: string|null}
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
        $attachment = null;
        if ($kind === DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN && $inquiry !== null
            && $this->materialItemsGrouped($department, $inquiry) !== []
        ) {
            $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $inquiry->getName()) ?? 'Firma';
            $attachment = 'Materialliste-' . mb_substr(trim($slug, '-') ?: 'Firma', 0, 40) . '.pdf';
        }

        return [
            'subject' => $this->apply($subjectTpl, $vars),
            'body' => $this->apply($bodyTpl, $vars),
            'to' => $inquiry?->getEmail() ?? 'demo@firma.example',
            'placeholders' => $vars,
            'attachment_filename' => $attachment,
        ];
    }

    /**
     * @param list<string> $inquiryIds
     * @return list<array{inquiry_id: string, subject: string, body: string, to: string, placeholders: array<string, string>, attachment_filename: string|null}>
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
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(
        Department $department,
        ?DepartmentGrossanlassInquiry $inquiry,
        string $kind = DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
    ): array {
        $names = $inquiry
            ? $this->resolveCategoryLabels($department, $inquiry->getCategoryIds())
            : $this->allCategoryNames($department);
        $packages = implode(', ', $names);
        if ($packages === '') {
            $packages = 'Bereiche folgen';
        }
        $areaOnly = in_array($kind, [
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE,
            DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN,
            DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN,
        ], true);
        if ($areaOnly) {
            $materialList = $packages;
        } else {
            $materialList = $this->materialListForInquiry($department, $inquiry);
            if ($materialList === '') {
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
            'ZEITRAUMTEXT' => 'Aufbau, Anlasswoche und Rückgabe gemäss Absprache',
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
            $this->resolveCategoryLabels($department, $inquiry->getCategoryIds()),
            $status ?? GrossanlassGmailRouting::STATUS_WAITING,
            $inquiry->getStatus(),
        );
    }

    /**
     * @return list<string>
     */
    public function allCategoryNames(Department $department): array
    {
        $names = [];
        foreach ($this->categoriesForDepartment($department) as $row) {
            $path = $this->categoryPackagePath($row);
            if ($path !== '') {
                $names[] = $path;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $categoryIds
     * @return list<string>
     */
    public function resolveCategoryLabels(Department $department, array $categoryIds): array
    {
        $byId = [];
        $byName = [];
        foreach ($this->categoriesForDepartment($department) as $row) {
            $path = $this->categoryPackagePath($row);
            $byId[$row->getId()] = $path !== '' ? $path : $row->getName();
            $byName[mb_strtolower($row->getName())] = $path !== '' ? $path : $row->getName();
        }
        $out = [];
        foreach ($categoryIds as $raw) {
            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }
            if (isset($byId[$value])) {
                $out[] = $byId[$value];
                continue;
            }
            $lower = mb_strtolower($value);
            $out[] = $byName[$lower] ?? $value;
        }

        return array_values(array_unique($out));
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
     * @return list<array{category: string, items: list<string>}>
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
            ->addSelect('c')
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
            $catName = $line->getCategory()?->getName() ?: 'Ohne Bereich';
            if (!isset($grouped[$catName])) {
                $grouped[$catName] = [];
            }
            $grouped[$catName][$label] = true;
        }
        $out = [];
        foreach ($grouped as $category => $labels) {
            $out[] = [
                'category' => $category,
                'items' => array_keys($labels),
            ];
        }

        return $out;
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

    public function categoryPackagePath(ActivityGrossanlassProcurementCategory $category): string
    {
        $name = GrossanlassGmailRouting::sanitizeSegment($category->getName());
        if ($name === '') {
            return '';
        }
        $parent = $category->getParent();
        if ($parent === null) {
            return $name;
        }
        $parentName = GrossanlassGmailRouting::sanitizeSegment($parent->getName());

        return $parentName !== '' ? $parentName . '/' . $name : $name;
    }

    /**
     * @param array{name?: string, parent_name?: string|null} $row
     */
    public static function categoryPackagePathFromRow(array $row): string
    {
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
        if ($changed) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array<string, array{subject: string, body: string}>
     */
    private function defaultTexts(string $eventName): array
    {
        $footer = '<p>Freundliche Grüsse<br>{{ABSENDER}}</p><p>Referenz {{REFERENZ}}</p>';

        return [
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE => [
                'subject' => $eventName . ' – Anfrage Material & Logistik',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir fragen an, ob {{FIRMA}} uns für {{ANLASS}} im Bereich {{BEREICHE}} unterstützen kann.</p><p>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_PRAEZISIEREN => [
                'subject' => $eventName . ' – Materialliste',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Rückmeldung von {{FIRMA}}. Im Anhang die genaue Liste zu {{BEREICHE}} — ohne Stückzahlen, die klären wir danach.</p><p>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_DANK_ABSAGE => [
                'subject' => $eventName . ' – Danke für die Rückmeldung',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Rückmeldung von {{FIRMA}}. Wir haben die Absage notiert.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_ZUSAGE_OK => [
                'subject' => $eventName . ' – Zusage bestätigt',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>vielen Dank für die Zusage von {{FIRMA}}. Wir haben notiert: {{BEREICHE}}.</p><p>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NICHT_GENOMMEN => [
                'subject' => $eventName . ' – Zusammenarbeit dieses Mal nicht',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>herzlichen Dank für die Zusage von {{FIRMA}}. Für dieses Paket nehmen wir eine andere Lösung. Wir melden uns gerne bei einem nächsten Anlass.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NEHMEN => [
                'subject' => $eventName . ' – Wir rechnen mit euch',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir nehmen das Angebot von {{FIRMA}} gerne an.</p><p>Bereiche: {{BEREICHE}}<br>Zeitraum: {{ZEITRAUMTEXT}}</p><p>Nächste Schritte folgen in diesem Thread.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN => [
                'subject' => $eventName . ' – Kurze Nachfrage',
                'body' => '<p>Guten Tag {{ANREDE}} {{NACHNAME}}</p><p>wir möchten kurz nachfassen, ob unsere Anfrage an {{FIRMA}} angekommen ist.</p><p>Bereiche: {{BEREICHE}}</p>' . $footer,
            ],
        ];
    }
}
