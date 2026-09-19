import type { GaMap, GaMapBounds, GaPlaceKind } from '@/api/grossanlassLogistics'

export const GA_PLACE_KINDS: GaPlaceKind[] = ['bauprojekt', 'anfahrt', 'matplatz', 'unterlager', 'poi']

export const GA_PLACE_COLORS: Record<GaPlaceKind, string> = {
  bauprojekt: '#d97706',
  anfahrt: '#0ea5e9',
  matplatz: '#16a34a',
  unterlager: '#7c3aed',
  poi: '#64748b',
}

export const DRAFT_GA_PIN_ID = 'draft-ga'

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

export function gaPlaceColor(kind: string | null | undefined): string {
  return GA_PLACE_COLORS[gaPlaceKind(kind)]
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
