import { describe, expect, it } from 'vitest'
import type { CustomBlock, OfficialBlock, WidgetDefinition } from '@/api/displayLayout'
import { buildLibrary, contentOf, groupLibrary, widgetCategory } from './libraryModel'

const def = (type: string, category: 'general' | 'ga', family?: string): WidgetDefinition => ({
  type, category, family, contexts: ['grossanlass'], data_source: 'static', min_size: [1, 1], default_size: [3, 3], version: 1, fields: {},
})
const group = (name: string) => ({ type: 'group' as const, name, rows: 4, items: [{ id: 'i', x: 0, y: 0, w: 6, h: 2, content: { type: 'widget' as const, widget: 'text', widget_version: 1, config: {} } }] })

describe('Bausteinbibliothek', () => {
  const widgets = [def('clock', 'general'), def('ga_pack_progress', 'ga', 'material'), def('ga_transport_overview', 'ga', 'logistik'), def('ga_site_map', 'ga', 'map'), def('ga_helpers', 'ga', 'helpers'), def('ga_warnings', 'ga', 'warnings')]
  const official: OfficialBlock[] = [
    { id: 'region_materiallager', kind: 'region', category: 'regions', contexts: [], default_size: [6, 11], content: group('r') },
    { id: 'group_material_status', kind: 'group', category: 'material', contexts: [], default_size: [8, 6], content: group('g') },
  ]
  const custom: CustomBlock[] = [{ id: 'c1', name: 'Mein Block', kind: 'group', content: group('c'), default_size: [4, 4], created_by_user_id: null, created_at: '', updated_at: '' }]

  it('ordnet Widgets und Bausteine den Kategorien zu', () => {
    expect(widgetCategory(widgets[0]!)).toBe('general')
    expect(widgetCategory(widgets[1]!)).toBe('material')
    expect(widgetCategory(widgets[2]!)).toBe('logistics')
    expect(widgetCategory(widgets[3]!)).toBe('map')
    expect(widgetCategory(widgets[4]!)).toBe('helpers')
    expect(widgetCategory(widgets[5]!)).toBe('activity')
    const entries = buildLibrary(widgets, official, custom)
    expect(entries.find((e) => e.key === 'official:region_materiallager')?.category).toBe('templates')
    expect(entries.find((e) => e.key === 'official:group_material_status')?.category).toBe('material')
    expect(entries.find((e) => e.key === 'custom:c1')?.category).toBe('custom')
    expect(entries.find((e) => e.key === 'builtin:rotation')?.category).toBe('rotation')
  })

  it('filtert und lässt «Eigene Bausteine» optional leer sichtbar', () => {
    const entries = buildLibrary(widgets, official, [])
    const groups = groupLibrary(entries, (e) => e.id.includes('clock'), ['custom'])
    expect(groups.map((g) => g.category)).toEqual(['general', 'custom'])
  })

  it('liefert beim Einfügen immer eine unabhängige Kopie', () => {
    const entries = buildLibrary(widgets, official, custom)
    const a = contentOf(entries.find((e) => e.key === 'custom:c1')!, new Map(), official, custom, () => ({ type: 'group', rows: 1, items: [] }))!
    ;(a as { name?: string }).name = 'geändert'
    expect(custom[0]!.content).toMatchObject({ name: 'c' })
    const b = contentOf(entries.find((e) => e.key === 'official:group_material_status')!, new Map(), official, custom, () => ({ type: 'group', rows: 1, items: [] }))!
    expect(b).not.toBe(official[1]!.content)
  })
})
