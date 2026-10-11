/**
 * Gemeinsame Auftragsliste der Planung. Ein «Auftrag» und ein «Bauauftrag» sind dieselbe Gruppen-Entität
 * (Blatt unter Ressort/Bereich) und unterscheiden sich nur im Knotentyp (`auftrag` bzw. `bauprojekt`).
 */
import type { GrossanlassGroup, GrossanlassOrderCard } from '@/api/grossanlassGroups'
import { buildGrossanlassGroupPathLabel, isBauprojektGroup } from '@/utils/grossanlassGroupHierarchy'
import { resolveBuildStatus, type GaBuildStatus } from '@/utils/grossanlassBuildStatus'

export type GaOrderType = 'order' | 'build'
export type GaOrderFilter = 'all' | GaOrderType

export const TYPE_ICON: Record<GaOrderType, string> = { order: 'mdi-clipboard-text-outline', build: 'mdi-hammer-wrench' }
export const TYPE_COLOR: Record<GaOrderType, string> = { order: 'info', build: 'deep-orange' }
export const STATUS_COLOR: Record<GaBuildStatus, string> = {
  planned: 'grey',
  quoted: 'info',
  build: 'primary',
  use: 'success',
  teardown: 'warning',
  done: 'success',
  aborted: 'error',
}

export type GaOrderRow = {
  id: string
  type: GaOrderType
  group: GrossanlassGroup
  title: string
  path: string
  status: GaBuildStatus
  windowStart: string | null
  windowEnd: string | null
  place: string
  responsible: string[]
  card: GrossanlassOrderCard | null
}

/** Anteil 0–100 oder null, wenn es keine Grundlage gibt (nie ein geschätzter Wert). */
export function percentOf(done: number, total: number): number | null {
  return total > 0 ? Math.round((done / total) * 100) : null
}

export const materialPercent = (card: GrossanlassOrderCard | null) =>
  card ? percentOf(card.material.at_place_items, card.material.items) : null
export const tasksPercent = (card: GrossanlassOrderCard | null) =>
  card ? percentOf(card.tasks.done, card.tasks.total) : null

export type GaOrderFilters = { type: GaOrderFilter; search: string; ressort: string | null; status: string | null }

export const emptyOrderFilters = (): GaOrderFilters => ({ type: 'all', search: '', ressort: null, status: null })

export function orderTypeOf(group: Pick<GrossanlassGroup, 'node_type' | 'kind'>): GaOrderType | null {
  if (isBauprojektGroup(group as GrossanlassGroup)) return 'build'
  if (group.node_type === 'auftrag' || group.kind === 'auftrag') return 'order'
  return null
}

/** Erstes Element des Pfads = Ressort. */
function ressortOf(group: GrossanlassGroup, groups: GrossanlassGroup[]): string {
  return buildGrossanlassGroupPathLabel(group.id, groups, '\u0000').split('\u0000')[0] ?? ''
}

export function buildOrderRows(
  visible: GrossanlassGroup[],
  all: GrossanlassGroup[],
  cards: GrossanlassOrderCard[] = [],
): GaOrderRow[] {
  const cardById = new Map(cards.map((card) => [card.group_id, card]))
  const rows: GaOrderRow[] = []
  for (const group of visible) {
    const type = orderTypeOf(group)
    if (!type) continue
    const status = resolveBuildStatus(group)
    rows.push({
      id: group.id,
      type,
      group,
      title: group.name,
      path: group.parent_id ? buildGrossanlassGroupPathLabel(group.parent_id, all, ' › ') : '',
      status,
      windowStart: group.window_start ?? null,
      windowEnd: group.window_end ?? null,
      place: group.place?.name ?? '',
      responsible: (group.leaders ?? []).map((leader) => leader.name),
      card: cardById.get(group.id) ?? null,
    })
  }
  return rows.sort((a, b) => a.title.localeCompare(b.title, 'de'))
}

export function ressortNameOf(row: GaOrderRow, all: GrossanlassGroup[]): string {
  return ressortOf(row.group, all)
}

export function matchesOrder(row: GaOrderRow, filters: GaOrderFilters, all: GrossanlassGroup[]): boolean {
  if (filters.type !== 'all' && row.type !== filters.type) return false
  if (filters.status && row.status !== filters.status) return false
  if (filters.ressort && ressortNameOf(row, all) !== filters.ressort) return false
  const needle = filters.search.trim().toLocaleLowerCase()
  if (!needle) return true
  return [row.title, row.path, row.place, ...row.responsible].some((part) => part.toLocaleLowerCase().includes(needle))
}

export function orderCounts(rows: GaOrderRow[]): Record<GaOrderFilter, number> {
  return {
    all: rows.length,
    order: rows.filter((row) => row.type === 'order').length,
    build: rows.filter((row) => row.type === 'build').length,
  }
}
