<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\ActivityJsOrder;
use mikehaertl\pdftk\Pdf;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Befüllt das offizielle J+S-PDF-AcroForm (nicht neu layouten).
 */
class ActivityJsOrderPdfService
{
    private string $templatePath;

    public function __construct(
        private JsOrderPdfFieldMapper $fieldMapper,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->templatePath = $projectDir . '/resources/js-order/bestellformular_lagersport_trekking_d.pdf';
    }

    public function renderPdf(ActivityJsOrder $order): string
    {
        if (!is_file($this->templatePath)) {
            throw new \RuntimeException('J+S-PDF-Vorlage fehlt: ' . $this->templatePath);
        }

        $fields = $this->fieldMapper->mapOrderToFormFields($order);
        $tmpOut = tempnam(sys_get_temp_dir(), 'jsorder_pdf_');
        if ($tmpOut === false) {
            throw new \RuntimeException('Temporäre PDF-Datei konnte nicht angelegt werden');
        }
        $outputPath = $tmpOut . '.pdf';
        @unlink($tmpOut);

        try {
            $pdf = new Pdf($this->templatePath);
            $pdf->fillForm($fields);
            $pdf->flatten();
            if (!$pdf->saveAs($outputPath)) {
                throw new \RuntimeException('PDF konnte nicht erzeugt werden: ' . $pdf->getError());
            }

            $binary = file_get_contents($outputPath);
            if ($binary === false || $binary === '') {
                throw new \RuntimeException('Erzeugtes PDF ist leer');
            }

            return $binary;
        } finally {
            @unlink($outputPath);
        }
    }
}
