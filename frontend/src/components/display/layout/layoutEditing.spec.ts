import { describe, expect, it } from 'vitest'
import type { LayoutDocument, WidgetDefinition } from '@/api/displayLayout'
import {
  addItem,
  addPage,
  addVariant,
  canEmbed,
  clampGeometry,
  findFreeSpot,
  isPageReferenced,
  pageIssues,
  removeVariant,
  setVariantFlow,
  variantFlow,
  widgetContent,
} from './layoutEditing'

const textDef: WidgetDefinition = {
  type: 'text', category: 'general', contexts: ['grossanlass'], data_source: 'static', min_size: [2, 1], default_size: [6, 2], version: 1,
  fields: { text: { type: 'text', default: '' }, level: { type: 'enum', values: ['body'], default: 'body' } },
}

function doc(): LayoutDocument {
  return {
    schema_version: 1,
    default_variant_id: 'v-1',
    variants: [{ id: 'v-1', name: 'Standard', aspect_ratio: '16:9', root: { type: 'page', page_id: 'p-1' } }],
    pages: [{ id: 'p-1', name: 'A', rows: 12, items: [] }],
  }
}

describe('layout editing helpers', () => {
  it('clamps geometry to the grid', () => {
    expect(clampGeometry({ x: 10, y: 11, w: 5, h: 4 }, 12)).toEqual({ x: 7, y: 8, w: 5, h: 4 })
    expect(clampGeometry({ x: -3, y: -1, w: 0, h: 0 }, 12)).toEqual({ x: 0, y: 0, w: 1, h: 1 })
    expect(clampGeometry({ x: 0, y: 0, w: 20, h: 30 }, 12)).toEqual({ x: 0, y: 0, w: 12, h: 12 })
  })

  it('finds the first free spot and avoids overlaps', () => {
    expect(findFreeSpot([], 12, 6, 4)).toEqual({ x: 0, y: 0, w: 6, h: 4 })
    expect(findFreeSpot([{ x: 0, y: 0, w: 6, h: 4 }], 12, 6, 4)).toEqual({ x: 6, y: 0, w: 6, h: 4 })
    expect(findFreeSpot([{ x: 0, y: 0, w: 12, h: 12 }], 12, 6, 4)).toEqual({ x: 0, y: 0, w: 6, h: 4 })
  })

  it('adds widgets with registry defaults and unique ids', () => {
    const d = doc()
    const a = addItem(d.pages[0]!, widgetContent(textDef), [6, 2])
    const b = addItem(d.pages[0]!, widgetContent(textDef), [6, 2])
    expect(a.id).not.toBe(b.id)
    expect(a.content).toMatchObject({ type: 'widget', widget: 'text', config: { text: '', level: 'body' } })
    expect(b.x).toBe(6)
  })

  it('prevents embedding cycles', () => {
    const d = doc()
    const p2 = addPage(d, 'B')
    const p3 = addPage(d, 'C')
    addItem(d.pages[0]!, { type: 'page', page_id: p2.id }, [4, 4])
    addItem(p2, { type: 'page', page_id: p3.id }, [4, 4])
    expect(canEmbed(d, 'p-1', 'p-1')).toBe(false)
    expect(canEmbed(d, p3.id, 'p-1')).toBe(false)
    expect(canEmbed(d, p3.id, p2.id)).toBe(false)
    expect(canEmbed(d, 'p-1', p3.id)).toBe(true)
  })

  it('knows which pages are referenced', () => {
    const d = doc()
    const p2 = addPage(d, 'B')
    expect(isPageReferenced(d, p2.id)).toBe(false)
    addItem(d.pages[0]!, { type: 'page', page_id: p2.id }, [4, 4])
    expect(isPageReferenced(d, p2.id)).toBe(true)
    expect(isPageReferenced(d, 'p-1')).toBe(true)
  })

  it('switches a variant between a single page and a whole-display rotation', () => {
    const d = doc()
    const p2 = addPage(d, 'B')
    const variant = d.variants[0]!
    setVariantFlow(variant, ['p-1', p2.id], true, 15)
    expect(variantFlow(variant)).toMatchObject({ rotation: true, enabled: true, interval: 15, pageIds: ['p-1', p2.id] })
    setVariantFlow(variant, ['p-1'], false, 15)
    expect(variant.root).toEqual({ type: 'page', page_id: 'p-1' })
    // Rotation vollständig deaktiviert, aber mehrere Seiten vorhanden: bleibt eine (stehende) Rotation.
    setVariantFlow(variant, ['p-1', p2.id], false, 15)
    expect(variantFlow(variant)).toMatchObject({ rotation: true, enabled: false })
  })

  it('keeps at least one variant and a valid default', () => {
    const d = doc()
    const second = addVariant(d, 'LED-Wall', '3:2', d.variants[0])
    expect(second.root).toEqual({ type: 'page', page_id: 'p-1' })
    expect(removeVariant(d, 'v-1')).toBe(true)
    expect(d.default_variant_id).toBe(second.id)
    expect(removeVariant(d, second.id)).toBe(false)
  })

  it('reports overlaps and too small widgets', () => {
    const d = doc()
    const page = d.pages[0]!
    page.items.push(
      { id: 'a', x: 0, y: 0, w: 6, h: 4, content: widgetContent(textDef) },
      { id: 'b', x: 5, y: 3, w: 1, h: 1, content: widgetContent(textDef) },
    )
    const issues = pageIssues(page, new Map([['text', textDef]]))
    expect(issues).toEqual(expect.arrayContaining([{ itemId: 'b', code: 'too_small' }, { itemId: 'b', code: 'overlap' }]))
  })
})

import { pickVariant, profileAspect, profileFontScale } from './layoutEditing'

describe('device simulation', () => {
  const multi = (): LayoutDocument => ({
    schema_version: 1,
    default_variant_id: 'std',
    variants: [
      { id: 'std', name: 'Standard', aspect_ratio: '16:9', root: { type: 'page', page_id: 'p' } },
      { id: 'led', name: 'LED', aspect_ratio: '3:2', root: { type: 'page', page_id: 'p' } },
    ],
    pages: [{ id: 'p', name: 'P', rows: 12, items: [] }],
  })

  it('picks the closest variant and falls back to the default', () => {
    expect(pickVariant(multi(), { aspect: 16 / 9 })).toBe('std')
    expect(pickVariant(multi(), { aspect: 3 / 2 })).toBe('led')
    expect(pickVariant(multi(), { aspect: 9 / 16 })).toBe('std')
    expect(pickVariant(multi(), { aspect: 3 / 2, variantId: 'std' })).toBe('std')
    expect(pickVariant(multi(), { aspect: 3 / 2, variantId: 'gone' })).toBe('led')
  })

  it('derives aspect and font scale from a device profile', () => {
    const base = { pixel_width: 3000, pixel_height: 2000, width_cm: null, height_cm: null, aspect_ratio: null, viewing_distance_m: null, font_profile: 'auto', variant_id: null }
    expect(profileAspect(base)).toBeCloseTo(1.5)
    expect(profileAspect({ ...base, pixel_width: null, pixel_height: null, width_cm: 300, height_cm: 200 })).toBeCloseTo(1.5)
    expect(profileAspect(null)).toBeNull()
    expect(profileFontScale({ ...base, font_profile: 'large' })).toBe(1.25)
    expect(profileFontScale({ ...base, viewing_distance_m: 20 })).toBe(1.6)
    expect(profileFontScale(null)).toBe(1)
  })
})
