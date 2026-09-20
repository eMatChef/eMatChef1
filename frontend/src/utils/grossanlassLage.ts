import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import type { GaPlace } from '@/api/grossanlassLogistics'
import type { GaUebersichtEinsatz } from '@/api/grossanlassUebersicht'

export type GaLageTaskKind = 'einsatz' | 'fahrauftrag' | 'bauauftrag'

export function gaLageTaskKind(
  row: Pick<GaUebersichtEinsatz, 'delivery' | 'group_id' | 'destination_place_id'>,
  groups: Pick<GrossanlassGroup, 'id' | 'node_type'>[],
  places: Pick<GaPlace, 'id' | 'kind' | 'group_id'>[] = [],
): GaLageTaskKind {
  if (row.delivery === 'trip') return 'fahrauftrag'
  const group = groups.find((item) => item.id === row.group_id)
  if (group?.node_type === 'bauprojekt') return 'bauauftrag'
  const place = places.find((item) => item.id === row.destination_place_id)
  if (place?.kind === 'bauprojekt') return 'bauauftrag'
  if (place?.group_id) {
    const placeGroup = groups.find((item) => item.id === place.group_id)
    if (placeGroup?.node_type === 'bauprojekt') return 'bauauftrag'
  }
  return 'einsatz'
}

export function gaLageIsOpen(row: Pick<GaUebersichtEinsatz, 'status' | 'to'>): boolean {
  if (row.status === 'returned') return false
  const end = Date.parse(row.to)
  if (!Number.isFinite(end)) return true
  return end >= Date.now()
}

export function sortGaLageByStart(
  a: Pick<GaUebersichtEinsatz, 'from'>,
  b: Pick<GaUebersichtEinsatz, 'from'>,
): number {
  return String(a.from).localeCompare(String(b.from))
}

type GaLageFilterRow = Pick<GaUebersichtEinsatz, 'delivery' | 'group_id' | 'status' | 'from'> &
  Partial<Pick<GaUebersichtEinsatz, 'destination_place_id' | 'task_kind'>>

/** Alle nicht zurückgegebenen Einsätze einer Lage-Art — für die Übersichtsseiten. */
export function gaLageRowsOfKind<T extends GaLageFilterRow>(
  rows: T[],
  kind: GaLageTaskKind,
  groups: Pick<GrossanlassGroup, 'id' | 'node_type'>[],
  places: Pick<GaPlace, 'id' | 'kind' | 'group_id'>[] = [],
): T[] {
  return rows
    .filter((row) => row.status !== 'returned')
    .filter((row) => (row.task_kind ?? gaLageTaskKind(row, groups, places)) === kind)
    .slice()
    .sort(sortGaLageByStart)
}
