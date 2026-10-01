import type { GaUebersichtEinsatz } from '@/api/grossanlassUebersicht'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import type { GrossanlassUserCard } from '@/api/grossanlassUserCards'
import {
  enrichEinsatzFromGroups,
  einsatzBarKind,
  parseLocalDate,
  type GaEinsatzOrgGroup,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { formatGaIsoLabel, isoDatePart, isoTimePart } from '@/views/grossanlass/grossanlassZusagePreviewData'

export type GaHelperTaskKind = 'einsatz' | 'fahrauftrag' | 'bauauftrag'

export type GaHelperAssignment = GaPreviewEinsatz & {
  taskKind: GaHelperTaskKind
  destinationPlaceName?: string
  timeRangeLabel: string
  operable?: boolean
}

export function groupsToOrgGroups(groups: GrossanlassGroup[]): GaEinsatzOrgGroup[] {
  return groups.map((group) => ({
    id: group.id,
    name: group.name,
    parent_id: group.parent_id,
    node_type: group.node_type,
    window_start: group.window_start,
    window_end: group.window_end,
    build_status: group.build_status,
  }))
}

export function formatGaClockRange(fromIso: string, toIso: string): string {
  if (!fromIso || !toIso) return '–'
  return `${isoTimePart(fromIso)} – ${isoTimePart(toIso)}`
}

export function formatHelperDayLabel(iso: string, locale: string): string {
  return parseLocalDate(iso)
    .toLocaleDateString(locale, {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    })
    .replace(/\.$/, '')
}

export function formatHelperWhenLabel(fromIso: string, toIso: string, locale: string): string {
  if (!fromIso || !toIso) return '–'
  const clock = formatGaClockRange(fromIso, toIso)
  if (isoDatePart(fromIso) === isoDatePart(toIso)) {
    return `${formatHelperDayLabel(fromIso, locale)} · ${clock}`
  }
  return `${formatHelperDayLabel(fromIso, locale)} ${isoTimePart(fromIso)} – ${formatHelperDayLabel(toIso, locale)} ${isoTimePart(toIso)}`
}

export function helperBarKind(
  assignment: Pick<GaPreviewEinsatz, 'status' | 'delivery' | 'tripReleased' | 'conflictId' | 'barRole'> & {
    taskKind?: GaHelperTaskKind
  },
): string {
  if (
    assignment.taskKind === 'fahrauftrag'
    && assignment.delivery === 'trip'
    && !assignment.tripReleased
    && assignment.status !== 'issued'
  ) {
    return 'unreleased'
  }
  return einsatzBarKind(assignment as GaPreviewEinsatz)
}

export function helperBarKindClass(
  assignment: Pick<GaPreviewEinsatz, 'status' | 'delivery' | 'tripReleased' | 'conflictId' | 'barRole'> & {
    taskKind?: GaHelperTaskKind
  },
): string {
  return `ga-helper-bar--${helperBarKind(assignment)}`
}

export function helperOrgLabel(
  assignment: Pick<GaPreviewEinsatz, 'ressort' | 'bauprojekt'>,
): string {
  const ressort = assignment.ressort?.trim()
  const bauprojekt = assignment.bauprojekt?.trim()
  if (ressort && bauprojekt) return `${ressort} · ${bauprojekt}`
  return ressort || bauprojekt || '–'
}

export function toHelperAssignment(
  row: GaUebersichtEinsatz,
  locale: string,
  taskKind?: GaHelperTaskKind,
  groups?: GaEinsatzOrgGroup[],
): GaHelperAssignment {
  let assignment: GaHelperAssignment = {
    id: row.id,
    objectId: row.object_id,
    objectName: row.object_name,
    kind: row.einsatz_kind,
    qty: row.qty,
    stock: row.stock,
    fromIso: row.from,
    toIso: row.to,
    fromLabel: formatGaIsoLabel(row.from, locale),
    toLabel: formatGaIsoLabel(row.to, locale),
    timeRangeLabel: formatHelperWhenLabel(row.from, row.to, locale),
    ressort: row.ressort,
    groupId: row.group_id,
    status: row.status,
    who: row.who,
    barRole: 'einsatz',
    delivery: row.delivery ?? 'pickup',
    tripReleased: !!row.trip_released,
    packed: row.packed,
    chauffeurUserId: row.chauffeur_user_id,
    destinationPlaceId: row.destination_place_id,
    destinationPlaceName: row.destination_place_name,
    place: row.place,
    taskKind: taskKind ?? row.task_kind ?? 'einsatz',
    operable: row.operable ?? true,
  }

  if (groups?.length) {
    assignment = {
      ...(enrichEinsatzFromGroups(assignment, groups) as GaHelperAssignment),
      timeRangeLabel: assignment.timeRangeLabel,
      taskKind: assignment.taskKind,
      destinationPlaceName: assignment.destinationPlaceName,
    }
  }

  return assignment
}

export function chauffeurNameForAssignment(
  assignment: GaHelperAssignment,
  cards: GrossanlassUserCard[],
): string {
  if (!assignment.chauffeurUserId) return ''
  return cards.find((card) => card.user_id === assignment.chauffeurUserId)?.name ?? ''
}
