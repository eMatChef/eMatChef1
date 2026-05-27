/** Anlass-Typen pro Infoscreen. */
export const DISPLAY_ACTIVITY_TYPES = ['activity', 'camp', 'event', 'external'] as const
export type DisplayActivityType = (typeof DISPLAY_ACTIVITY_TYPES)[number]

/** Anlass-Status pro Infoscreen (ohne abgeschlossen/storniert). */
export const DISPLAY_ACTIVITY_STATUSES = [
  'draft',
  'submitted',
  'approved',
  'packing',
  'packed',
  'at_event',
  'returned',
] as const
export type DisplayActivityStatus = (typeof DISPLAY_ACTIVITY_STATUSES)[number]

export const DEFAULT_DISPLAY_ACTIVITY_STATUSES: DisplayActivityStatus[] = [
  'submitted',
  'approved',
  'packing',
  'packed',
  'at_event',
]

/** Werkstatt-Status pro Infoscreen. */
export const DISPLAY_WORKSHOP_STATUSES = [
  'open',
  'in_progress',
  'waiting_parts',
  'completed',
  'cancelled',
] as const
export type DisplayWorkshopStatus = (typeof DISPLAY_WORKSHOP_STATUSES)[number]

export const DEFAULT_DISPLAY_WORKSHOP_STATUSES: DisplayWorkshopStatus[] = [
  'open',
  'in_progress',
  'waiting_parts',
]

function normalizeList<T extends string>(
  raw: string[] | null | undefined,
  allowed: readonly T[],
  fallback: readonly T[],
): T[] {
  if (!raw?.length) return [...fallback]
  const set = new Set(allowed)
  return allowed.filter((v) => raw.includes(v) && set.has(v))
}

export function normalizeDisplayActivityTypes(raw: string[] | null | undefined): DisplayActivityType[] {
  return normalizeList(raw, DISPLAY_ACTIVITY_TYPES, DISPLAY_ACTIVITY_TYPES)
}

export function normalizeDisplayActivityStatuses(raw: string[] | null | undefined): DisplayActivityStatus[] {
  return normalizeList(raw, DISPLAY_ACTIVITY_STATUSES, DEFAULT_DISPLAY_ACTIVITY_STATUSES)
}

export function normalizeDisplayWorkshopStatuses(raw: string[] | null | undefined): DisplayWorkshopStatus[] {
  return normalizeList(raw, DISPLAY_WORKSHOP_STATUSES, DEFAULT_DISPLAY_WORKSHOP_STATUSES)
}

export function recordFromList<T extends string>(
  keys: readonly T[],
  enabled: readonly string[],
): Record<T, boolean> {
  const set = new Set(enabled)
  return Object.fromEntries(keys.map((k) => [k, set.has(k)])) as Record<T, boolean>
}

export function listFromRecord<T extends string>(keys: readonly T[], record: Record<T, boolean>): T[] {
  return keys.filter((k) => record[k])
}
