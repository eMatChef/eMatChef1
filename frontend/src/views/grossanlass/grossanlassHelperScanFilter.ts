import type { GaHelperScanContextPayload, GaUebersichtEinsatz } from '@/api/grossanlassUebersicht'
import type { GaLogisticsPack, GaPlace } from '@/api/grossanlassLogistics'
import type { GaEinsatzOrgGroup } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  toHelperAssignment,
  type GaHelperAssignment,
  type GaHelperTaskKind,
} from '@/views/grossanlass/grossanlassHelperAssignment'

function allContextRows(payload: GaHelperScanContextPayload | null): GaUebersichtEinsatz[] {
  if (!payload) return []
  return [...payload.fahrauftraege, ...payload.bauauftraege, ...payload.einsaetze]
}

export function assignmentsFromScanContext(
  payload: GaHelperScanContextPayload,
  locale: string,
  groups: GaEinsatzOrgGroup[],
): GaHelperAssignment[] {
  return allContextRows(payload).map((row) =>
    toHelperAssignment(row, locale, row.task_kind as GaHelperTaskKind | undefined, groups),
  )
}

export function splitHelperAssignments(assignments: GaHelperAssignment[]) {
  return {
    fahrauftraege: assignments.filter((row) => row.taskKind === 'fahrauftrag'),
    bauauftraege: assignments.filter((row) => row.taskKind === 'bauauftrag'),
    einsaetze: assignments.filter((row) => row.taskKind === 'einsatz'),
  }
}

/** @deprecated client-side filter — use assignmentsFromScanContext via API */
export function filterHelperAssignmentsForPlace(
  _place: GaPlace,
  _payload: null,
  _groups: GaEinsatzOrgGroup[],
  _locale: string,
): GaHelperAssignment[] {
  return []
}

/** @deprecated client-side filter — use assignmentsFromScanContext via API */
export function filterHelperAssignmentsForPack(
  _pack: GaLogisticsPack,
  _payload: null,
  _groups: GaEinsatzOrgGroup[],
  _locale: string,
): GaHelperAssignment[] {
  return []
}
