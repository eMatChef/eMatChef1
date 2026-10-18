import type { ActivityLocationPin, ActivityMapOverlay, ActivityMapPolygon } from '@/components/activities/ActivityDualLocationMap.vue'
import type { DisplayMapData, LayoutContent, LayoutDocument } from '@/api/displayLayout'
import { GA_PLACE_KINDS, gaPlaceColor, gaPlaceKind, normalizeGaPolygon } from '@/utils/grossanlassGaMap'

/** Konfiguration des Widgets «ga_site_map» (Schema: DisplayWidgetRegistry); fehlende Felder älterer Layouts erhalten Standardwerte. */
export interface DisplayMapConfig {
  baseLayer: 'osm' | 'swisstopo' | 'swissimage'
  showPlans: boolean
  kinds: string[]
  groupId: string
  showLabels: boolean
  fixedView: { lat: number; lng: number; zoom: number } | null
}

const BASE_LAYERS = ['osm', 'swisstopo', 'swissimage']

function num(value: unknown, fallback: number, min: number, max: number): number {
  const n = Number(value)
  return Number.isFinite(n) && n >= min && n <= max ? n : fallback
}

export function resolveDisplayMapConfig(raw: Record<string, unknown>): DisplayMapConfig {
  const base = String(raw.base_layer ?? 'osm')
  return {
    baseLayer: (BASE_LAYERS.includes(base) ? base : 'osm') as DisplayMapConfig['baseLayer'],
    showPlans: raw.show_plans !== false,
    kinds: Array.isArray(raw.kinds) ? raw.kinds.map(String) : [...GA_PLACE_KINDS],
    groupId: typeof raw.group_id === 'string' ? raw.group_id.trim() : '',
    showLabels: raw.show_labels !== false,
    fixedView: raw.view === 'fixed'
      ? { lat: num(raw.center_lat, 46.8182, -90, 90), lng: num(raw.center_lng, 8.2275, -180, 180), zoom: Math.round(num(raw.zoom, 15, 3, 19)) }
      : null,
  }
}

export interface DisplayMapModel {
  pins: ActivityLocationPin[]
  polygons: ActivityMapPolygon[]
  overlay: ActivityMapOverlay | null
  /** Nichts darzustellen (kein Ort, kein Plan): neutraler Leerzustand statt einer leeren Weltkarte. */
  empty: boolean
}

/**
 * Wandelt die Anzeige-Kartendaten in Pins, Flächen und Plan der zentralen Karte (ActivityDualLocationMap) um –
 * mit denselben Farben und Objektarten wie in der GA-Planung. Es werden nur vorhandene Daten angezeigt.
 */
export function buildDisplayMapModel(data: DisplayMapData | null, config: DisplayMapConfig): DisplayMapModel {
  const pins: ActivityLocationPin[] = []
  const polygons: ActivityMapPolygon[] = []
  for (const place of data?.places ?? []) {
    const kind = gaPlaceKind(place.kind)
    if (!config.kinds.includes(kind)) continue
    if (config.groupId && !place.group_ids.includes(config.groupId)) continue
    const color = gaPlaceColor(kind)
    if (kind === 'area') {
      const points = normalizeGaPolygon(place.polygon)
      if (points.length >= 3) {
        polygons.push({ id: place.id, label: place.name, color, points, starred: place.starred, highlighted: place.starred })
      }
    } else if (place.latitude != null && place.longitude != null) {
      pins.push({ id: place.id, label: place.name, latitude: place.latitude, longitude: place.longitude, variant: 'poi', color, highlighted: place.starred })
    }
  }
  const plan = config.showPlans ? data?.plan ?? null : null
  const overlay: ActivityMapOverlay | null = plan
    ? { url: plan.image_url, north: plan.bounds_north, south: plan.bounds_south, east: plan.bounds_east, west: plan.bounds_west, opacity: plan.overlay_opacity }
    : null
  return { pins, polygons, overlay, empty: pins.length === 0 && polygons.length === 0 && overlay === null }
}

/** true, wenn das Layout irgendwo (Varianten, Seiten, Rotationen) das Widget enthält. */
export function layoutUsesWidget(layout: LayoutDocument | null | undefined, widget: string): boolean {
  if (!layout) return false
  const visit = (content: LayoutContent): boolean => {
    if (content.type === 'widget') return content.widget === widget
    if (content.type === 'group') return content.items.some((item) => visit(item.content))
    if (content.type === 'rotation') return content.steps.some((step) => visit(step.content))
    return false
  }
  return layout.variants.some((v) => visit(v.root)) || layout.pages.some((p) => p.items.some((item) => visit(item.content)))
}
