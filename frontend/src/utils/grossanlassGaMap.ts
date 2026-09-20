import type { GaMap, GaMapBounds, GaPlaceKind, GaPolygonPoint } from '@/api/grossanlassLogistics'

export const GA_PLACE_KINDS: GaPlaceKind[] = ['bauprojekt', 'anfahrt', 'matplatz', 'unterlager', 'poi', 'area']

/** Neu anlegbar. Matplatz ist der Lagerstandort, area kommt über den Ressort-Toggle. */
export const GA_PLACE_CREATE_KINDS: GaPlaceKind[] = ['bauprojekt', 'anfahrt', 'unterlager', 'poi']

export const GA_PLACE_COLORS: Record<GaPlaceKind, string> = {
  bauprojekt: '#d97706',
  anfahrt: '#0ea5e9',
  matplatz: '#16a34a',
  unterlager: '#7c3aed',
  poi: '#64748b',
  area: '#0f766e',
}

/** Ab diesem Zoom erscheinen unmarkierte GA-Orte und -Bereiche. */
export const GA_MAP_DETAIL_MIN_ZOOM = 15

export const DRAFT_GA_PIN_ID = 'draft-ga'

/** Standard-Deckkraft des Geländeplans (92 % deckend, 8 % Karte sichtbar). */
export const GA_MAP_OVERLAY_OPACITY_DEFAULT = 0.92

/** Beim Anpassen etwas transparenter, damit die Basiskarte zum Ausrichten sichtbar bleibt. */
export const GA_MAP_OVERLAY_OPACITY_EDIT_DELTA = 0.14

export function gaMapOverlayOpacity(value: number | null | undefined, editing = false): number {
  const base =
    typeof value === 'number' && Number.isFinite(value)
      ? Math.min(1, Math.max(0.3, value))
      : GA_MAP_OVERLAY_OPACITY_DEFAULT
  if (!editing) return base
  return Math.max(0.35, base - GA_MAP_OVERLAY_OPACITY_EDIT_DELTA)
}

export function clampMapAxis(value: number): number {
  if (!Number.isFinite(value)) return 0
  return Math.min(1, Math.max(0, value))
}

export function mapPointFromClick(
  rect: { left: number; top: number; width: number; height: number },
  clientX: number,
  clientY: number,
): { x: number; y: number } {
  if (rect.width <= 0 || rect.height <= 0) {
    return { x: 0.5, y: 0.5 }
  }
  return {
    x: clampMapAxis((clientX - rect.left) / rect.width),
    y: clampMapAxis((clientY - rect.top) / rect.height),
  }
}

export function gaPlaceKind(kind: string | null | undefined): GaPlaceKind {
  return GA_PLACE_KINDS.includes(kind as GaPlaceKind) ? (kind as GaPlaceKind) : 'poi'
}

export function gaPlaceCreateKinds(current?: string | null): GaPlaceKind[] {
  if (gaPlaceKind(current) === 'matplatz') {
    return [...GA_PLACE_CREATE_KINDS, 'matplatz']
  }
  return GA_PLACE_CREATE_KINDS
}

export function gaPlaceColor(kind: string | null | undefined): string {
  return GA_PLACE_COLORS[gaPlaceKind(kind)]
}

export function gaPlaceKindLabelKey(
  kind: string | null | undefined,
): 'Bauprojekt' | 'Unterlager' | 'Matplatz' | 'Anfahrt' | 'Poi' | 'Area' {
  const value = gaPlaceKind(kind)
  if (value === 'bauprojekt') return 'Bauprojekt'
  if (value === 'unterlager') return 'Unterlager'
  if (value === 'matplatz') return 'Matplatz'
  if (value === 'anfahrt') return 'Anfahrt'
  if (value === 'area') return 'Area'
  return 'Poi'
}

export function normalizeGaPolygon(value: unknown): GaPolygonPoint[] {
  if (!Array.isArray(value)) return []
  const points: GaPolygonPoint[] = []
  for (const item of value) {
    if (!item || typeof item !== 'object') continue
    const raw = item as { lat?: unknown; lng?: unknown; 0?: unknown; 1?: unknown }
    const lat = Number(raw.lat ?? raw[0])
    const lng = Number(raw.lng ?? raw[1])
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) continue
    points.push({ lat, lng })
    if (points.length >= 32) break
  }
  return points
}

/** Grober Kartenrahmen um einen Punkt (ca. 300 m bei halfSpan 0.0015°). */
export function boundsAroundPoint(
  lat: number,
  lng: number,
  halfSpan = 0.0015,
): GaMapBounds {
  return {
    north: lat + halfSpan,
    south: lat - halfSpan,
    east: lng + halfSpan,
    west: lng - halfSpan,
  }
}

export function overlayImageAspectRatio(imageWidth: number, imageHeight: number): number | null {
  if (!(imageWidth > 0) || !(imageHeight > 0)) return null
  return imageWidth / imageHeight
}

function lngSpanForLatSpan(latSpan: number, centerLat: number, aspect: number): number {
  const cosLat = Math.max(0.2, Math.cos((centerLat * Math.PI) / 180))
  return (latSpan * aspect) / cosLat
}

/** Bounds mit korrektem Bild-Seitenverhältnis (Mercator-korrigiert). */
export function boundsWithAspectRatio(
  centerLat: number,
  centerLng: number,
  imageWidth: number,
  imageHeight: number,
  latSpan = 0.003,
): GaMapBounds | null {
  const aspect = overlayImageAspectRatio(imageWidth, imageHeight)
  if (!aspect) return null
  const halfLat = latSpan / 2
  const halfLng = lngSpanForLatSpan(latSpan, centerLat, aspect) / 2
  return {
    north: centerLat + halfLat,
    south: centerLat - halfLat,
    east: centerLng + halfLng,
    west: centerLng - halfLng,
  }
}

/** Ecken-Ziehen: gegenüberliegende Ecke bleibt, Seitenverhältnis des Bildes bleibt erhalten. */
export function boundsFromCornerDragWithAspect(
  corner: 'nw' | 'ne' | 'se' | 'sw',
  lat: number,
  lng: number,
  current: GaMapBounds,
  imageWidth: number,
  imageHeight: number,
): GaMapBounds | null {
  const aspect = overlayImageAspectRatio(imageWidth, imageHeight)
  if (!aspect) return null
  const minLatSpan = 0.00005

  if (corner === 'nw') {
    const south = current.south
    const east = current.east
    const latSpan = Math.max(minLatSpan, lat - south)
    const lngSpan = lngSpanForLatSpan(latSpan, (lat + south) / 2, aspect)
    return { north: lat, south, east, west: east - lngSpan }
  }
  if (corner === 'ne') {
    const south = current.south
    const west = current.west
    const latSpan = Math.max(minLatSpan, lat - south)
    const lngSpan = lngSpanForLatSpan(latSpan, (lat + south) / 2, aspect)
    return { north: lat, south, west, east: west + lngSpan }
  }
  if (corner === 'se') {
    const north = current.north
    const west = current.west
    const latSpan = Math.max(minLatSpan, north - lat)
    const lngSpan = lngSpanForLatSpan(latSpan, (north + lat) / 2, aspect)
    return { south: lat, north, west, east: west + lngSpan }
  }
  const north = current.north
  const east = current.east
  const latSpan = Math.max(minLatSpan, north - lat)
  const lngSpan = lngSpanForLatSpan(latSpan, (north + lat) / 2, aspect)
  return { south: lat, north, east, west: east - lngSpan }
}

/** Vorhandene Bounds an Bild-Seitenverhältnis anpassen (Mitte + Höhe bleiben). */
export function fitBoundsToAspectRatio(
  bounds: GaMapBounds,
  imageWidth: number,
  imageHeight: number,
): GaMapBounds | null {
  const aspect = overlayImageAspectRatio(imageWidth, imageHeight)
  if (!aspect) return null
  const centerLat = (bounds.north + bounds.south) / 2
  const centerLng = (bounds.east + bounds.west) / 2
  const latSpan = Math.max(0.00005, bounds.north - bounds.south)
  const halfLat = latSpan / 2
  const halfLng = lngSpanForLatSpan(latSpan, centerLat, aspect) / 2
  return {
    north: centerLat + halfLat,
    south: centerLat - halfLat,
    east: centerLng + halfLng,
    west: centerLng - halfLng,
  }
}

export function gaMapOverlayBounds(map: GaMap | null | undefined): GaMapBounds | null {
  if (!map?.image_url) return null
  const north = map.bounds_north
  const south = map.bounds_south
  const east = map.bounds_east
  const west = map.bounds_west
  if (
    north == null ||
    south == null ||
    east == null ||
    west == null ||
    !(north > south) ||
    !(east > west)
  ) {
    return null
  }
  return { north, south, east, west }
}
