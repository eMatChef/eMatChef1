/**
 * Fahraufträge (`delivery = trip`, Quelle: Grossanlass-Übersicht, Ansicht «Planung › Transporte») als Logistik-Kacheln
 * der Aufgabenübersicht. Es entsteht kein zweiter Datenbestand: die Kachel verweist auf den Einsatz der Übersicht.
 */
import type { GaUebersichtEinsatz } from '@/api/grossanlassUebersicht'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { parseLocalDate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { buildGrossanlassGroupPathLabel } from '@/utils/grossanlassGroupHierarchy'
import type { GaAufgabe, GaAufgabeStatus } from './gaAufgabenMock'

const SEP = '\u0000'

/** Anzeige-Status der Kachel; der echte API-Status bleibt in `fahrauftrag.status` erhalten. */
export function tripTaskStatus(status: GaUebersichtEinsatz['status']): GaAufgabeStatus {
  if (status === 'issued') return 'progress'
  if (status === 'returned') return 'done'
  return 'open'
}

export function isTripRow(row: GaUebersichtEinsatz): boolean {
  return row.kind === 'einsatz' && row.delivery === 'trip' && row.status !== 'returned'
}

/** Freigabe laut Backend (`released`); ohne dieses Feld gilt nur ausgegeben/zurückgegeben bzw. «Fahrt frei» ausserhalb der ausstehenden Freigabe. */
export function isTripReleased(row: GaUebersichtEinsatz): boolean {
  if (typeof row.released === 'boolean') return row.released
  if (row.status === 'issued' || row.status === 'returned') return true
  return row.status !== 'pending_approval' && row.trip_released
}

export function mapTripTask(
  row: GaUebersichtEinsatz,
  groups: GrossanlassGroup[],
  driverName: (userId: string) => string | null,
): GaAufgabe {
  const origin = row.group_id ? buildGrossanlassGroupPathLabel(row.group_id, groups, SEP).split(SEP).filter(Boolean) : []
  // Einsatzzeiten sind Wandzeit (Offset ignoriert), wie in Transportliste und Fahrauftragsdialog.
  const startsAt = parseLocalDate(row.from)
  const endsAt = parseLocalDate(row.to || row.from)
  const driver = row.chauffeur_user_id ? driverName(row.chauffeur_user_id) : null
  return {
    id: `trip:${row.id}`,
    groupId: undefined,
    assigneeId: row.chauffeur_user_id,
    scheduled: true,
    kind: 'logistik',
    title: row.object_name || row.who,
    origin: origin.length ? origin : row.ressort ? [row.ressort] : [],
    ressort: origin[0] ?? row.ressort ?? '',
    bereich: origin.length >= 3 ? origin[1]! : '',
    startsAt,
    endsAt: Number.isNaN(endsAt.getTime()) ? startsAt : endsAt,
    people: driver ? [driver] : [],
    status: tripTaskStatus(row.status),
    description: row.who,
    materials: [],
    steps: [],
    routeStepIndex: -1,
    fahrauftrag: {
      einsatzId: row.id,
      status: row.status,
      // Nur wenn der Einsatz ein Fahrzeug ist; sonst ist `object_name` das transportierte Material.
      vehicle: row.object_family === 'vehicle' ? row.object_name : '',
      destination: row.destination_place_name ?? '',
      released: !!row.trip_released,
      operable: row.operable ?? true,
    },
  }
}
