import type { PrintLayoutField } from '@/api/printLayouts'

export const MIN_FIELD_PCT = 8
export const MAX_FIELD_PCT = 100

export type ResizeHandle = 'nw' | 'ne' | 'sw' | 'se'

export function cloneLayoutFields(fields: PrintLayoutField[]): PrintLayoutField[] {
  return fields.map((item) => ({ ...item }))
}

export function clampField(field: PrintLayoutField): PrintLayoutField {
  const w = Math.min(MAX_FIELD_PCT, Math.max(MIN_FIELD_PCT, field.w))
  const h = Math.min(MAX_FIELD_PCT, Math.max(MIN_FIELD_PCT, field.h))
  const x = Math.min(MAX_FIELD_PCT - w, Math.max(0, field.x))
  const y = Math.min(MAX_FIELD_PCT - h, Math.max(0, field.y))
  return { ...field, x, y, w, h }
}

export function moveField(field: PrintLayoutField, dx: number, dy: number): PrintLayoutField {
  return clampField({ ...field, x: field.x + dx, y: field.y + dy })
}

export function resizeField(
  field: PrintLayoutField,
  handle: ResizeHandle,
  dx: number,
  dy: number,
): PrintLayoutField {
  let { x, y, w, h } = field
  if (handle.includes('e')) w += dx
  if (handle.includes('s')) h += dy
  if (handle.includes('w')) {
    w -= dx
    x += dx
  }
  if (handle.includes('n')) {
    h -= dy
    y += dy
  }
  return clampField({ ...field, x, y, w, h })
}

export function scaleField(field: PrintLayoutField, factor: number): PrintLayoutField {
  const cx = field.x + field.w / 2
  const cy = field.y + field.h / 2
  const w = field.w * factor
  const h = field.h * factor
  return clampField({ ...field, x: cx - w / 2, y: cy - h / 2, w, h })
}
