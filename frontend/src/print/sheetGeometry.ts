import type { PrintMedia } from '@/api/printCatalog'
import type { PrintSheetCell, PrintSheetSpec } from '@/api/printLayouts'

export function specFromMedia(media: PrintMedia, cutLengthMm?: number | null): PrintSheetSpec {
  const labelW = media.width_mm
  const labelH = media.is_continuous
    ? (cutLengthMm ?? media.default_cut_length_mm ?? 55)
    : (media.height_mm ?? labelW)
  let cols = Math.max(1, media.cols || 1)
  let rows = Math.max(1, media.rows || 1)
  let sheetW = media.sheet_width_mm ?? null
  let sheetH = media.sheet_height_mm ?? null
  if (sheetW == null || sheetH == null) {
    if (media.family === 'office_a4') {
      sheetW = sheetW ?? 210
      sheetH = sheetH ?? 297
    } else {
      sheetW = labelW
      sheetH = labelH
      cols = 1
      rows = 1
    }
  }
  const centered = centerGrid(sheetW, sheetH, labelW, labelH, cols, rows)
  const shape = media.shape || (
    labelW === labelH && /rund/i.test(media.name) ? 'round' : 'rect'
  )
  return {
    sheet_width_mm: sheetW,
    sheet_height_mm: sheetH,
    margin_top_mm: media.margin_top_mm ?? centered.margin_top_mm,
    margin_left_mm: media.margin_left_mm ?? centered.margin_left_mm,
    gap_x_mm: media.gap_x_mm ?? 0,
    gap_y_mm: media.gap_y_mm ?? 0,
    shape,
    cols,
    rows,
    label_width_mm: labelW,
    label_height_mm: labelH,
  }
}

export function cellsFromSpec(spec: PrintSheetSpec): PrintSheetCell[] {
  const out: PrintSheetCell[] = []
  let index = 0
  for (let row = 0; row < spec.rows; row++) {
    for (let col = 0; col < spec.cols; col++) {
      out.push({
        x: spec.margin_left_mm + col * (spec.label_width_mm + spec.gap_x_mm),
        y: spec.margin_top_mm + row * (spec.label_height_mm + spec.gap_y_mm),
        w: spec.label_width_mm,
        h: spec.label_height_mm,
        col,
        row,
        index,
      })
      index += 1
    }
  }
  return out
}

function centerGrid(
  sheetW: number,
  sheetH: number,
  labelW: number,
  labelH: number,
  cols: number,
  rows: number,
) {
  return {
    margin_left_mm: round2(Math.max(0, (sheetW - cols * labelW) / 2)),
    margin_top_mm: round2(Math.max(0, (sheetH - rows * labelH) / 2)),
  }
}

function round2(n: number): number {
  return Math.round(n * 100) / 100
}

export function labelsPerSheet(media: PrintMedia, spec?: PrintSheetSpec): number {
  if (spec) return Math.max(1, spec.cols * spec.rows)
  if (media.family !== 'office_a4') return 1
  return Math.max(1, (media.cols || 1) * (media.rows || 1))
}
