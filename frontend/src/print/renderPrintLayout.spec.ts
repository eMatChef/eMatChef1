import { describe, expect, it } from 'vitest'
import { fieldRect, mmToPx, specAspect } from '@/print/renderPrintLayout'

describe('print layout geometry', () => {
  it('maps percent fields onto a sheet cell', () => {
    expect(
      fieldRect(
        { id: 'qr', type: 'qr', key: 'public_url', x: 10, y: 20, w: 40, h: 50 },
        { x: 5, y: 8, w: 70, h: 40, col: 0, row: 0, index: 0 },
      ),
    ).toEqual({ x: 12, y: 16, w: 28, h: 20 })
  })

  it('converts millimetres at a given pixel density', () => {
    expect(mmToPx(25.4, 300 / 25.4)).toBeCloseTo(300)
  })

  it('keeps sheet aspect from millimetres', () => {
    expect(
      specAspect({
        sheet_width_mm: 210,
        sheet_height_mm: 297,
        margin_top_mm: 0,
        margin_left_mm: 0,
        gap_x_mm: 0,
        gap_y_mm: 0,
        shape: 'rect',
        cols: 1,
        rows: 1,
        label_width_mm: 210,
        label_height_mm: 297,
      }),
    ).toBeCloseTo(210 / 297)
  })
})
