<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF-Anhang für die Folge-Mail: Bedarfspositionen nach Bereich, ohne Stückzahl im Fliesstext.
 */
final class GrossanlassInquiryMaterialPdf
{
    public function __construct(private GrossanlassMailMergeService $merge)
    {
    }

    /**
     * @return array{filename: string, mime: string, content: string}|null
     */
    public function attachmentFor(Department $department, DepartmentGrossanlassInquiry $inquiry): ?array
    {
        $groups = $this->merge->materialItemsGrouped($department, $inquiry);
        if ($groups === []) {
            return null;
        }
        $filename = $this->filenameFor($inquiry);
        $html = $this->buildHtml($department, $inquiry, $groups);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $binary = $dompdf->output();
        if ($binary === '') {
            return null;
        }

        return [
            'filename' => $filename,
            'mime' => 'application/pdf',
            'content' => $binary,
        ];
    }

    public function filenameFor(DepartmentGrossanlassInquiry $inquiry): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $inquiry->getName()) ?? 'Firma';
        $slug = trim($slug, '-') ?: 'Firma';

        return 'Materialliste-' . mb_substr($slug, 0, 40) . '.pdf';
    }

    /**
     * @param list<array{category: string, items: list<string>}> $groups
     */
    private function buildHtml(Department $department, DepartmentGrossanlassInquiry $inquiry, array $groups): string
    {
        $title = htmlspecialchars('Materialliste ' . $department->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $firma = htmlspecialchars($inquiry->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        foreach ($groups as $group) {
            $cat = htmlspecialchars($group['category'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $rows .= '<tr><th colspan="1" style="text-align:left;padding:10px 8px 4px;background:#f1f5f9;font-size:12px;">'
                . $cat . '</th></tr>';
            foreach ($group['items'] as $label) {
                $safe = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $rows .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #e5e7eb;">' . $safe . '</td></tr>';
            }
        }

        return '<html><head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
            h1 { font-size: 16px; margin: 0 0 4px; }
            p { margin: 0 0 12px; color: #475569; }
            table { width: 100%; border-collapse: collapse; }
        </style></head><body>
            <h1>' . $title . '</h1>
            <p>Für ' . $firma . ' — Gegenstände ohne Stückzahl. Mengen folgen nach Absprache.</p>
            <table>' . $rows . '</table>
        </body></html>';
    }
}
