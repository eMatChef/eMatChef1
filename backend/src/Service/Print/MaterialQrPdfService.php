<?php

declare(strict_types=1);

namespace App\Service\Print;

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * A4-PDF mit Material-QR-Codes: 3×4 Raster = 12 Etiketten pro Seite.
 */
class MaterialQrPdfService
{
    private const COLS = 3;
    private const ROWS = 4;
    private const PER_PAGE = 12;

    /**
     * @param MaterialQrExportRow[] $rows
     */
    public function renderPdf(array $rows, string $documentTitle): string
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('Keine QR-Zeilen für PDF vorhanden');
        }

        $html = $this->buildHtml($rows, $documentTitle);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param MaterialQrExportRow[] $rows
     */
    private function buildHtml(array $rows, string $documentTitle): string
    {
        $pages = array_chunk($rows, self::PER_PAGE);
        $body = '';

        foreach ($pages as $pageIndex => $pageRows) {
            $body .= '<div class="page">';
            if ($pageIndex === 0) {
                $body .= '<h1>' . $this->escape($documentTitle) . '</h1>';
                $body .= '<p class="meta">' . $this->escape((string) count($rows)) . ' QR-Codes</p>';
            }

            $body .= '<table class="grid"><tbody>';
            for ($row = 0; $row < self::ROWS; ++$row) {
                $body .= '<tr>';
                for ($col = 0; $col < self::COLS; ++$col) {
                    $index = $row * self::COLS + $col;
                    $body .= '<td class="cell">';
                    if (isset($pageRows[$index])) {
                        $body .= $this->renderCell($pageRows[$index]);
                    }
                    $body .= '</td>';
                }
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8" /><style>'
            . $this->css()
            . '</style></head><body>'
            . $body
            . '</body></html>';
    }

    private function renderCell(MaterialQrExportRow $row): string
    {
        $qrDataUri = $this->buildQrDataUri($row->publicUrl);

        return '<div class="card">'
            . '<img src="' . $qrDataUri . '" alt="QR" class="qr" />'
            . '<div class="material">' . $this->escape($row->materialName) . '</div>'
            . '<div class="line">' . $this->escape($row->lineLabel) . '</div>'
            . '<div class="code">' . $this->escape($row->publicCode !== '' ? $row->publicCode : '-') . '</div>'
            . '</div>';
    }

    private function buildQrDataUri(string $url): string
    {
        $result = (new Builder())->build(
            data: $url,
            size: 200,
            margin: 4,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
        );

        return $result->getDataUri();
    }

    private function css(): string
    {
        return <<<'CSS'
@page { size: A4 portrait; margin: 10mm; }
body { font-family: DejaVu Sans, sans-serif; color: #111827; margin: 0; }
.page { page-break-after: always; }
.page:last-child { page-break-after: auto; }
h1 { font-size: 14pt; margin: 0 0 2mm; }
.meta { font-size: 9pt; color: #6b7280; margin: 0 0 4mm; }
table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
td.cell { width: 33.33%; height: 64mm; vertical-align: top; padding: 1.5mm; }
.card { border: 0.3mm solid #d1d5db; border-radius: 2mm; text-align: center; padding: 2mm; height: 58mm; box-sizing: border-box; }
img.qr { width: 34mm; height: 34mm; object-fit: contain; }
.material { margin-top: 1.5mm; font-size: 8.5pt; font-weight: bold; line-height: 1.2; max-height: 10mm; overflow: hidden; }
.line { margin-top: 0.8mm; font-size: 7.5pt; color: #374151; line-height: 1.2; max-height: 8mm; overflow: hidden; }
.code { margin-top: 0.8mm; font-size: 7pt; font-family: DejaVu Sans Mono, monospace; color: #4b5563; }
CSS;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
