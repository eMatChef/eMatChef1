<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassMailTemplate;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassMailMergeService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<array{kind: string, subject: string, body: string}>
     */
    public function listTemplates(Department $department): array
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
            $out[] = [
                'kind' => $kind,
                'subject' => $row?->getSubject() ?? '',
                'body' => $row?->getBody() ?? '',
            ];
        }

        return $out;
    }

    /**
     * @param list<array{kind?: string, subject?: string, body?: string}> $templates
     * @return list<array{kind: string, subject: string, body: string}>
     */
    public function saveTemplates(Department $department, array $templates): array
    {
        $this->ensureDefaults($department);
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
        }
        $this->entityManager->flush();

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
     * @return array<string, string>
     */
    public function placeholders(Department $department, ?DepartmentGrossanlassInquiry $inquiry): array
    {
        $packages = $inquiry ? implode(', ', $inquiry->getCategoryIds()) : 'Fahrzeuge';
        $id = $inquiry?->getId() ?? '____________';

        return [
            'ANREDE' => 'Guten Tag',
            'FIRMA' => $inquiry?->getName() ?? 'Muster AG',
            'ANLASS' => $department->getName(),
            'ORT' => $inquiry?->getPlace() ?? '',
            'ZEITRAUMTEXT' => 'Aufbau, Anlasswoche und Rückgabe gemäss Absprache',
            'MATERIALLISTE' => $packages !== '' ? $packages : 'Paket folgt',
            'ABSENDER' => 'OK Material & Logistik',
            'REFERENZ' => $id,
            'EMAIL' => $inquiry?->getEmail() ?? '',
        ];
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
        foreach ($defaults as $kind => $pair) {
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
        $footer = "Freundliche Grüsse\n{{ABSENDER}}\n\nReferenz {{REFERENZ}}";

        return [
            DepartmentGrossanlassMailTemplate::KIND_ANFRAGE => [
                'subject' => $eventName . ' – Anfrage Material & Logistik',
                'body' => "{{ANREDE}}\n\nwir fragen an, ob {{FIRMA}} uns für {{ANLASS}} unterstützen kann.\n\nPaket: {{MATERIALLISTE}}\nZeitraum: {{ZEITRAUMTEXT}}\n\n" . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_DANK_ABSAGE => [
                'subject' => $eventName . ' – Danke für die Rückmeldung',
                'body' => "{{ANREDE}}\n\nvielen Dank für die Rückmeldung von {{FIRMA}}. Wir haben die Absage notiert.\n\n" . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_ZUSAGE_OK => [
                'subject' => $eventName . ' – Zusage bestätigt',
                'body' => "{{ANREDE}}\n\nvielen Dank für die Zusage von {{FIRMA}}. Wir haben notiert: {{MATERIALLISTE}}.\nZeitraum: {{ZEITRAUMTEXT}}\n\n" . $footer,
            ],
            DepartmentGrossanlassMailTemplate::KIND_NICHT_GENOMMEN => [
                'subject' => $eventName . ' – Zusammenarbeit dieses Mal nicht',
                'body' => "{{ANREDE}}\n\nherzlichen Dank für die Zusage von {{FIRMA}}. Für dieses Paket nehmen wir eine andere Lösung. Wir melden uns gerne bei einem nächsten Anlass.\n\n" . $footer,
            ],
        ];
    }
}
