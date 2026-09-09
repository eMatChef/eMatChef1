<?php

declare(strict_types=1);

namespace App\Service\PrintCatalog;

use App\Entity\PrintDeviceModel;
use App\Entity\PrintMedia;

/**
 * Bogen-Raster: Zellen in mm aus Medium-Geometrie.
 *
 * @phpstan-type SheetRect array{x: float, y: float, w: float, h: float, col: int, row: int, index: int}
 * @phpstan-type SheetSpec array{
 *     sheet_width_mm: float,
 *     sheet_height_mm: float,
 *     margin_top_mm: float,
 *     margin_left_mm: float,
 *     gap_x_mm: float,
 *     gap_y_mm: float,
 *     shape: string,
 *     cols: int,
 *     rows: int,
 *     label_width_mm: float,
 *     label_height_mm: float
 * }
 */
final class PrintSheetGeometry
{
    public const SHAPE_RECT = 'rect';
    public const SHAPE_ROUND = 'round';

    /**
     * @return SheetSpec
     */
    public static function specFromMedia(PrintMedia $media, ?float $cutLengthMm = null): array
    {
        $labelW = (float) $media->getWidthMm();
        $labelH = $media->isContinuous()
            ? (float) ($cutLengthMm ?? $media->getDefaultCutLengthMm() ?? 55)
            : (float) ($media->getHeightMm() ?? $labelW);
        $cols = max(1, $media->getCols());
        $rows = max(1, $media->getRows());
        $family = $media->getFamily();

        $sheetW = $media->getSheetWidthMm() !== null ? (float) $media->getSheetWidthMm() : null;
        $sheetH = $media->getSheetHeightMm() !== null ? (float) $media->getSheetHeightMm() : null;
        if ($sheetW === null || $sheetH === null) {
            if ($family === PrintDeviceModel::FAMILY_OFFICE_A4) {
                $sheetW = $sheetW ?? 210.0;
                $sheetH = $sheetH ?? 297.0;
            } else {
                $sheetW = $labelW;
                $sheetH = $labelH;
                $cols = 1;
                $rows = 1;
            }
        }

        $centered = self::centerGrid($sheetW, $sheetH, $labelW, $labelH, $cols, $rows);
        $marginTop = $media->getMarginTopMm() !== null ? (float) $media->getMarginTopMm() : $centered['margin_top_mm'];
        $marginLeft = $media->getMarginLeftMm() !== null ? (float) $media->getMarginLeftMm() : $centered['margin_left_mm'];
        $gapX = $media->getGapXMm() !== null ? (float) $media->getGapXMm() : 0.0;
        $gapY = $media->getGapYMm() !== null ? (float) $media->getGapYMm() : 0.0;
        $shape = $media->getShape() ?: ($labelW === $labelH && str_contains(strtolower($media->getName()), 'rund')
            ? self::SHAPE_ROUND
            : self::SHAPE_RECT);

        return [
            'sheet_width_mm' => $sheetW,
            'sheet_height_mm' => $sheetH,
            'margin_top_mm' => $marginTop,
            'margin_left_mm' => $marginLeft,
            'gap_x_mm' => $gapX,
            'gap_y_mm' => $gapY,
            'shape' => $shape,
            'cols' => $cols,
            'rows' => $rows,
            'label_width_mm' => $labelW,
            'label_height_mm' => $labelH,
        ];
    }

    /**
     * @param SheetSpec $spec
     * @return list<SheetRect>
     */
    public static function cells(array $spec): array
    {
        $out = [];
        $index = 0;
        for ($row = 0; $row < $spec['rows']; ++$row) {
            for ($col = 0; $col < $spec['cols']; ++$col) {
                $out[] = [
                    'x' => $spec['margin_left_mm'] + $col * ($spec['label_width_mm'] + $spec['gap_x_mm']),
                    'y' => $spec['margin_top_mm'] + $row * ($spec['label_height_mm'] + $spec['gap_y_mm']),
                    'w' => $spec['label_width_mm'],
                    'h' => $spec['label_height_mm'],
                    'col' => $col,
                    'row' => $row,
                    'index' => $index,
                ];
                ++$index;
            }
        }

        return $out;
    }

    /**
     * @return array{margin_top_mm: float, margin_left_mm: float}
     */
    public static function centerGrid(
        float $sheetW,
        float $sheetH,
        float $labelW,
        float $labelH,
        int $cols,
        int $rows,
    ): array {
        $gridW = $cols * $labelW;
        $gridH = $rows * $labelH;

        return [
            'margin_left_mm' => round(max(0.0, ($sheetW - $gridW) / 2), 2),
            'margin_top_mm' => round(max(0.0, ($sheetH - $gridH) / 2), 2),
        ];
    }

    public static function cellIndex(int $col, int $row, int $cols): int
    {
        return $row * $cols + $col;
    }
}
