<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassMailTemplate;
use App\Entity\DepartmentSetting;
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
        $keep = [DepartmentGrossanlassMailTemplate::KIND_ANFRAGE];
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
            if ($row->getKind() === DepartmentGrossanlassMailTemplate::KIND_ANFRAGE) {
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
     * @return array{subject: string, body: string, to: string, placeholders: array<string, string>}
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
        $vars = $this->placeholders($department, $inquiry);

        return [
            'subject' => $this->apply($subjectTpl, $vars),
            'body' => $this->apply($bodyTpl, $vars),
            'to' => $inquiry?->getEmail() ?? 'demo@firma.example',
            'placeholders' => $vars,
        ];
    }

    /**
     * @param list<string> $inquiryIds
     * @return list<array{inquiry_id: string, subject: string, body: string, to: string, placeholders: array<string, string>}>
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
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(Department $department, ?DepartmentGrossanlassInquiry $inquiry): array
    {
        $names = $inquiry
            ? $this->resolveCategoryLabels($department, $inquiry->getCategoryIds())
            : $this->allCategoryNames($department);
        $packages = implode(', ', $names);
        if ($packages === '') {
            $packages = 'Bereiche folgen';
        }
        $id = $inquiry?->getId() ?? '____________';
        $reference = $this->displayReference($department, $id);

        $vars = [
            'ANREDE' => 'Guten Tag',
            'FIRMA' => $inquiry?->getName() ?? 'Muster AG',
            'ANLASS' => $department->getName(),
            'ORT' => $inquiry?->getPlace() ?? '',
            'ZEITRAUMTEXT' => 'Aufbau, Anlasswoche und Rückgabe gemäss Absprache',
            'MATERIALLISTE' => $packages,
            'BEREICHE' => $packages,
            'ABSENDER' => 'OK Material & Logistik',
            'REFERENZ' => $reference,
            'EMAIL' => $inquiry?->getEmail() ?? '',
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
            $names[] = $row->getName();
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
            $byId[$row->getId()] = $row->getName();
            $byName[mb_strtolower($row->getName())] = $row->getName();
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
        foreach ([DepartmentGrossanlassMailTemplate::KIND_ANFRAGE] as $kind) {
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
                'body' => '<p>{{ANREDE}}</p><p>wir fragen an, ob {{FIRMA}} uns für {{ANLASS}} unterstützen kann.</p><p>Bereiche: {{BEREICHE}}<br>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_DANK_ABSAGE => [
                'subject' => $eventName . ' – Danke für die Rückmeldung',
                'body' => '<p>{{ANREDE}}</p><p>vielen Dank für die Rückmeldung von {{FIRMA}}. Wir haben die Absage notiert.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_ZUSAGE_OK => [
                'subject' => $eventName . ' – Zusage bestätigt',
                'body' => '<p>{{ANREDE}}</p><p>vielen Dank für die Zusage von {{FIRMA}}. Wir haben notiert: {{BEREICHE}}.</p><p>Zeitraum: {{ZEITRAUMTEXT}}</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NICHT_GENOMMEN => [
                'subject' => $eventName . ' – Zusammenarbeit dieses Mal nicht',
                'body' => '<p>{{ANREDE}}</p><p>herzlichen Dank für die Zusage von {{FIRMA}}. Für dieses Paket nehmen wir eine andere Lösung. Wir melden uns gerne bei einem nächsten Anlass.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NEHMEN => [
                'subject' => $eventName . ' – Wir rechnen mit euch',
                'body' => '<p>{{ANREDE}}</p><p>wir nehmen das Angebot von {{FIRMA}} gerne an.</p><p>Bereiche: {{BEREICHE}}<br>Zeitraum: {{ZEITRAUMTEXT}}</p><p>Nächste Schritte folgen in diesem Thread.</p>' . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN => [
                'subject' => $eventName . ' – Kurze Nachfrage',
                'body' => '<p>{{ANREDE}}</p><p>wir möchten kurz nachfassen, ob unsere Anfrage an {{FIRMA}} angekommen ist.</p><p>Bereiche: {{BEREICHE}}</p>' . $footer,
            ],
        ];
    }
}
