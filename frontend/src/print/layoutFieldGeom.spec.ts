import { describe, expect, it } from 'vitest'
import { clampField, moveField, resizeField, scaleField } from '@/print/layoutFieldGeom'
import type { PrintLayoutField } from '@/api/printLayouts'

function box(partial: Partial<PrintLayoutField> = {}): PrintLayoutField {
  return {
    id: 'title',
    type: 'text',
    key: 'label',
    x: 50,
    y: 12,
    w: 46,
    h: 40,
    ...partial,
  }
}

describe('layoutFieldGeom', () => {
  it('clamps a box to the label', () => {
    expect(clampField(box({ x: -10, y: 90, w: 80, h: 80 }))).toMatchObject({
      x: 0,
      y: 20,
      w: 80,
      h: 80,
    })
  })

  it('moves without leaving the label', () => {
    expect(moveField(box({ x: 90, y: 0, w: 20, h: 20 }), 40, -10)).toMatchObject({
      x: 80,
      y: 0,
    })
  })

  it('resizes from the south-east corner', () => {
    expect(resizeField(box({ x: 10, y: 10, w: 20, h: 20 }), 'se', 10, 5)).toMatchObject({
      x: 10,
      y: 10,
      w: 30,
      h: 25,
    })
  })

  it('resizes from the north-west corner', () => {
    expect(resizeField(box({ x: 40, y: 40, w: 20, h: 20 }), 'nw', -10, -5)).toMatchObject({
      x: 30,
      y: 35,
      w: 30,
      h: 25,
    })
  })

  it('scales around the centre', () => {
    const next = scaleField(box({ x: 40, y: 40, w: 20, h: 20 }), 2)
    expect(next.w).toBe(40)
    expect(next.h).toBe(40)
    expect(next.x).toBe(30)
    expect(next.y).toBe(30)
  })
})
