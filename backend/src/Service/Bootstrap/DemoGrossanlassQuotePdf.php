<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Erzeugt die Demo-Offerten als PDF (Dompdf, wie der Material-PDF-Anhang der Anfragen). Der Inhalt stammt vollständig aus den
 * Seed-Daten der Beschaffungskette; es werden keine fremden oder zufälligen Dokumente verwendet.
 */
final class DemoGrossanlassQuotePdf
{
    /**
     * @param array{supplier: string, reference: string, date: string, customer: string, label: string, quantity: int, unit: string, amount: string, delivery: string, note: string} $data
     */
    public function render(array $data): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111827;}'
            . 'h1{font-size:18px;margin:0 0 4px;}table{width:100%;border-collapse:collapse;margin-top:18px;}'
            . 'th{text-align:left;background:#f1f5f9;padding:6px 8px;font-size:10px;}td{padding:6px 8px;border-bottom:1px solid #e5e7eb;}'
            . '.right{text-align:right;}.muted{color:#6b7280;}'
            . '</style></head><body>'
            . '<h1>Offerte ' . $e($data['reference']) . '</h1>'
            . '<p class="muted">' . $e($data['supplier']) . ' · ' . $e($data['date']) . '</p>'
            . '<p>Kunde: ' . $e($data['customer']) . '<br>Demo-Dokument für die Testumgebung, keine echte Offerte.</p>'
            . '<table><tr><th>Position</th><th class="right">Menge</th><th class="right">Betrag CHF</th></tr>'
            . '<tr><td>' . $e($data['label']) . '</td><td class="right">' . $data['quantity'] . ' ' . $e($data['unit']) . '</td><td class="right">' . $e($data['amount']) . '</td></tr>'
            . '<tr><td colspan="2"><strong>Total inkl. MWST</strong></td><td class="right"><strong>' . $e($data['amount']) . '</strong></td></tr></table>'
            . '<p>Lieferung / Abholung: ' . $e($data['delivery']) . '</p>'
            . ($data['note'] !== '' ? '<p class="muted">' . $e($data['note']) . '</p>' : '')
            . '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
