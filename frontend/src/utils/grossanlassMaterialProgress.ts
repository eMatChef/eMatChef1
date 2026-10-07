import type { GaMaterialProgressQuantities } from '@/api/grossanlassUebersicht'

export type GaProgressKey = keyof GaMaterialProgressQuantities

export type GaProgressSegment = {
  key: GaProgressKey
  value: number
  tone: 'base' | 'done' | 'flow' | 'warn'
}

/** Reihenfolge entlang des Materialwegs. Bedarf und gedeckt immer, der Rest nur, wenn schon Menge da ist. */
const FLOW: Array<{ key: GaProgressKey; tone: GaProgressSegment['tone']; always?: boolean }> = [
  { key: 'required', tone: 'base', always: true },
  { key: 'covered', tone: 'done', always: true },
  { key: 'received', tone: 'done' },
  { key: 'allocated', tone: 'flow' },
  { key: 'packed', tone: 'flow' },
  { key: 'ready_for_transport', tone: 'flow' },
  { key: 'in_transit', tone: 'flow' },
  { key: 'at_place', tone: 'done' },
  { key: 'return_open', tone: 'flow' },
  { key: 'returned', tone: 'done' },
  { key: 'disposed', tone: 'base' },
  { key: 'open', tone: 'warn' },
]

export function materialProgressSegments(item: GaMaterialProgressQuantities): GaProgressSegment[] {
  return FLOW
    .filter((step) => step.always || (Number(item[step.key]) || 0) > 0)
    .map((step) => ({ key: step.key, value: Math.max(0, Number(item[step.key]) || 0), tone: step.tone }))
}

/** z. B. «100 benötigt · 80 gedeckt · 60 eingegangen · 20 offen» */
export function materialProgressText(
  item: GaMaterialProgressQuantities,
  label: (key: GaProgressKey, n: number) => string,
): string {
  return materialProgressSegments(item).map((segment) => label(segment.key, segment.value)).join(' · ')
}

export function findMaterialProgress<T extends { id: string }>(items: T[] | undefined, id: string | null | undefined): T | null {
  if (!id || !items?.length) return null
  return items.find((item) => item.id === id) ?? null
}
