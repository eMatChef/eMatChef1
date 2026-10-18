import { describe, expect, it } from 'vitest'
import type { DisplayMapData, LayoutDocument } from '@/api/displayLayout'
import { buildDisplayMapModel, layoutUsesWidget, resolveDisplayMapConfig } from './displayMapModel'
import { gaPlaceColor } from './grossanlassGaMap'

const data: DisplayMapData = {
  state: 'ready',
  plan: { id: 'gm1', name: 'Plan', image_url: '/api/public/display-device/map-image/gm1', bounds_north: 47.1, bounds_south: 47.0, bounds_east: 8.1, bounds_west: 8.0, overlay_opacity: 0.8 },
  places: [
    { id: 'p1', name: 'Eingang', kind: 'poi', latitude: 47.0123, longitude: 8.0456, polygon: null, starred: false, group_ids: ['g-bau'] },
    { id: 'p2', name: 'Baustelle', kind: 'bauprojekt', latitude: 47.02, longitude: 8.02, polygon: null, starred: true, group_ids: ['g-bau', 'g-holz'] },
    { id: 'a1', name: 'Festgelände', kind: 'area', latitude: 47.001, longitude: 8.002, polygon: [{ lat: 47.0, lng: 8.0 }, { lat: 47.002, lng: 8.001 }, { lat: 47.001, lng: 8.004 }], starred: false, group_ids: [] },
    { id: 'a2', name: 'Defekt', kind: 'area', latitude: null, longitude: null, polygon: [{ lat: 47.0, lng: 8.0 }], starred: false, group_ids: [] },
  ],
}

describe('displayMapModel', () => {
  it('übernimmt Koordinaten, Polygone, Farben und Plan unverändert', () => {
    const model = buildDisplayMapModel(data, resolveDisplayMapConfig({}))
    expect(model.pins.map((p) => [p.id, p.latitude, p.longitude])).toEqual([['p1', 47.0123, 8.0456], ['p2', 47.02, 8.02]])
    expect(model.pins[1].color).toBe(gaPlaceColor('bauprojekt'))
    expect(model.pins[1].highlighted).toBe(true)
    expect(model.polygons).toHaveLength(1)
    expect(model.polygons[0].points[2]).toEqual({ lat: 47.001, lng: 8.004 })
    expect(model.overlay).toEqual({ url: '/api/public/display-device/map-image/gm1', north: 47.1, south: 47.0, east: 8.1, west: 8.0, opacity: 0.8 })
    expect(model.empty).toBe(false)
  })

  it('filtert nach Objektart, Bauprojekt/Bereich und Plan-Schalter', () => {
    const model = buildDisplayMapModel(data, resolveDisplayMapConfig({ kinds: ['bauprojekt', 'area'], group_id: 'g-holz', show_plans: false }))
    expect(model.pins.map((p) => p.id)).toEqual(['p2'])
    expect(model.polygons).toEqual([])
    expect(model.overlay).toBeNull()
  })

  it('zeigt ohne Daten einen Leerzustand und erfindet nichts', () => {
    expect(buildDisplayMapModel(null, resolveDisplayMapConfig({})).empty).toBe(true)
    expect(buildDisplayMapModel({ state: 'ready', plan: null, places: [] }, resolveDisplayMapConfig({})).empty).toBe(true)
    expect(buildDisplayMapModel(data, resolveDisplayMapConfig({ kinds: [], show_plans: false })).empty).toBe(true)
  })

  it('löst ältere Konfigurationen ohne Kartenfelder mit Standardwerten auf', () => {
    expect(resolveDisplayMapConfig({ title: 'Lage', filter_ressort: 'Bau' })).toEqual({
      baseLayer: 'osm', showPlans: true, kinds: ['bauprojekt', 'anfahrt', 'lagerplatz', 'unterlager', 'poi', 'area'], groupId: '', showLabels: true, fixedView: null,
    })
    expect(resolveDisplayMapConfig({ base_layer: 'google' }).baseLayer).toBe('osm')
  })

  it('verwendet den festen Ausschnitt nur bei view=fixed', () => {
    expect(resolveDisplayMapConfig({ view: 'auto', center_lat: 47, center_lng: 8, zoom: 16 }).fixedView).toBeNull()
    expect(resolveDisplayMapConfig({ view: 'fixed', center_lat: 47.25, center_lng: 8.5, zoom: 17 }).fixedView).toEqual({ lat: 47.25, lng: 8.5, zoom: 17 })
  })

  it('erkennt das Karten-Widget in Seiten und Rotationen', () => {
    const widget = { type: 'widget' as const, widget: 'ga_site_map', widget_version: 1, config: {} }
    const doc = {
      schema_version: 1 as const,
      default_variant_id: 'v',
      variants: [{ id: 'v', name: 'V', aspect_ratio: '16:9', root: { type: 'page' as const, page_id: 'p' } }],
      pages: [{ id: 'p', name: 'P', rows: 12, items: [{ id: 'i', x: 0, y: 0, w: 12, h: 12, content: { type: 'rotation' as const, enabled: true, interval_sec: 10, steps: [{ duration_sec: null, content: widget }] } }] }],
    } satisfies LayoutDocument
    expect(layoutUsesWidget(doc, 'ga_site_map')).toBe(true)
    expect(layoutUsesWidget(doc, 'ga_overview')).toBe(false)
    expect(layoutUsesWidget(null, 'ga_site_map')).toBe(false)
  })
})
