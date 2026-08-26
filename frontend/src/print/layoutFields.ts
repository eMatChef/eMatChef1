import type { PrintLayout, PrintLayoutField } from '@/api/printLayouts'

export type LayoutFieldKey = 'qr' | 'title' | 'code'
export type PrintContentKey = LayoutFieldKey | 'event' | 'ressort' | 'role' | 'drive'

export type LayoutFieldPreset = {
  id: LayoutFieldKey
  type: 'qr' | 'text'
  key: 'label' | 'public_url' | 'public_code'
  x: number
  y: number
  w: number
  h: number
}

export const LAYOUT_FIELD_PRESETS: LayoutFieldPreset[] = [
  { id: 'qr', type: 'qr', key: 'public_url', x: 8, y: 10, w: 38, h: 70 },
  { id: 'title', type: 'text', key: 'label', x: 50, y: 12, w: 46, h: 40 },
  { id: 'code', type: 'text', key: 'public_code', x: 50, y: 56, w: 46, h: 28 },
]

export const DEFAULT_PRINT_CONTENT: PrintContentKey[] = ['qr', 'title', 'code']
export const USER_CARD_PRINT_CONTENT: PrintContentKey[] = [
  'qr',
  'title',
  'code',
  'event',
  'ressort',
  'role',
  'drive',
]

const TEXT_CONTENT_KEYS: PrintContentKey[] = ['title', 'event', 'ressort', 'role', 'drive']

export function isLayoutFieldKey(id: string): id is LayoutFieldKey {
  return id === 'qr' || id === 'title' || id === 'code'
}

export function defaultLayoutFields(enabled: LayoutFieldKey[] = ['qr', 'title', 'code']): PrintLayoutField[] {
  return LAYOUT_FIELD_PRESETS
    .filter((item) => enabled.includes(item.id))
    .map(({ id, type, key, x, y, w, h }) => ({ id, type, key, x, y, w, h }))
}

export function fieldEnabled(fields: PrintLayoutField[], id: LayoutFieldKey): boolean {
  return fields.some((item) => item.id === id)
}

export function enabledFieldKeys(fields: PrintLayoutField[]): LayoutFieldKey[] {
  return LAYOUT_FIELD_PRESETS.filter((item) => fieldEnabled(fields, item.id)).map((item) => item.id)
}

export function layoutKeysFromContent(enabled: PrintContentKey[]): LayoutFieldKey[] {
  const keys: LayoutFieldKey[] = []
  if (enabled.includes('qr')) keys.push('qr')
  if (enabled.some((item) => TEXT_CONTENT_KEYS.includes(item))) keys.push('title')
  if (enabled.includes('code')) keys.push('code')
  return keys.length ? keys : ['qr']
}

export function layoutWithEnabledFields(layout: PrintLayout, enabled: LayoutFieldKey[]): PrintLayout {
  const keys = enabled.filter(isLayoutFieldKey)
  const use = keys.length > 0 ? keys : (['qr'] as LayoutFieldKey[])
  const fields = use.map((id) => {
    const existing = layout.fields.find((item) => item.id === id)
    if (existing) return existing
    const preset = LAYOUT_FIELD_PRESETS.find((item) => item.id === id)
    if (!preset) {
      return { id, type: 'text' as const, key: 'label' as const, x: 8, y: 10, w: 84, h: 30 }
    }
    return { id: preset.id, type: preset.type, key: preset.key, x: preset.x, y: preset.y, w: preset.w, h: preset.h }
  })
  return { ...layout, fields }
}

export function toggleLayoutField(fields: PrintLayoutField[], id: LayoutFieldKey, on: boolean): PrintLayoutField[] {
  const preset = LAYOUT_FIELD_PRESETS.find((item) => item.id === id)
  if (!preset) return fields
  const has = fieldEnabled(fields, id)
  if (on && !has) {
    return [...fields, { id: preset.id, type: preset.type, key: preset.key, x: preset.x, y: preset.y, w: preset.w, h: preset.h }]
  }
  if (!on && has) {
    return fields.filter((item) => item.id !== id)
  }
  return fields
}
