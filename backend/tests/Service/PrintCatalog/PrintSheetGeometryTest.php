<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintCatalog;

use App\Entity\PrintDeviceModel;
use App\Entity\PrintMedia;
use App\Service\PrintCatalog\PrintSheetGeometry;
use PHPUnit\Framework\TestCase;

class PrintSheetGeometryTest extends TestCase
{
    public function testAverySheetHasTenCells(): void
    {
        $media = new PrintMedia();
        $media->setFamily(PrintDeviceModel::FAMILY_OFFICE_A4);
        $media->setName('Avery 3425');
        $media->setWidthMm('105.00');
        $media->setHeightMm('57.00');
        $media->setCols(2);
        $media->setRows(5);
        $media->setSheetWidthMm('210.00');
        $media->setSheetHeightMm('297.00');
        $media->setMarginTopMm('6.00');
        $media->setMarginLeftMm('0.00');
        $media->setGapXMm('0.00');
        $media->setGapYMm('0.00');

        $spec = PrintSheetGeometry::specFromMedia($media);
        $cells = PrintSheetGeometry::cells($spec);
        $this->assertCount(10, $cells);
        $this->assertSame(0.0, $cells[0]['x']);
        $this->assertSame(6.0, $cells[0]['y']);
        $this->assertSame(105.0, $cells[1]['x']);
        $this->assertSame(6.0 + 4 * 57.0, $cells[9]['y']);
    }

    public function testBrotherContinuousUsesCutLengthAsHeight(): void
    {
        $media = new PrintMedia();
        $media->setFamily(PrintDeviceModel::FAMILY_BROTHER_QL);
        $media->setName('DK-22225');
        $media->setWidthMm('38.00');
        $media->setIsContinuous(true);
        $media->setDefaultCutLengthMm(55);
        $media->setCols(1);
        $media->setRows(1);

        $spec = PrintSheetGeometry::specFromMedia($media);
        $this->assertSame(38.0, $spec['sheet_width_mm']);
        $this->assertSame(55.0, $spec['label_height_mm']);
        $this->assertCount(1, PrintSheetGeometry::cells($spec));
    }
}
