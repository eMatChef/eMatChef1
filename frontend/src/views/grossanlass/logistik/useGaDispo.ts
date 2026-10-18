/**
 * Disposition «Logistik → Disposition» mit echten Backend-Daten (Fahraufträge, Packs, Fahrzeugwünsche, Standorte).
 * Gleiche Form und Ableitungen wie der Prototyp-State in `gaDispoMock.ts`, der weiter die Live-Ansicht und die
 * Aufträge-Demo speist. Lesend; Touren folgen mit der Tour-Entität.
 */
import { computed, ref } from 'vue'
import {
  addGrossanlassDispatchTourNeed,
  arriveGrossanlassPackManually,
  closeGrossanlassDispatchTour,
  decideGrossanlassChangeRequest,
  convertGrossanlassDispatchVehicleNeed,
  releaseGrossanlassDispatchNeed,
  releaseGrossanlassDispatchTour,
  requestGrossanlassTripChange,
  revokeGrossanlassDispatchRelease,
  confirmGrossanlassDispatchTour,
  createGrossanlassDispatchTour,
  deleteGrossanlassDispatchTour,
  getGrossanlassDispatch,
  removeGrossanlassDispatchTourNeed,
  replaceGrossanlassDispatchTourStops,
  unconfirmGrossanlassDispatchTour,
  updateGrossanlassDispatchTour,
  type GaChangeRequestPayload,
  type GaDispatchChangeRequest,
  type GaDispatchConflict,
  type GaDispatchNeed,
  type GaDispatchOverview,
  type GaDispatchStop,
  type GaDispatchSuggestion,
  type GaDispatchTour,
  type GaDispatchTourPayload,
} from '@/api/grossanlassLogistics'
import { i18n } from '@/i18n'
import { parseLocalDate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import type { GaDispoDriver, GaDispoNeed, GaDispoPriority, GaDispoTour, GaDispoVehicle, GaDispoView } from './gaDispoMock'

export type GaDispoLiveNeed = GaDispoNeed & {
  startsAt: Date
  endsAt: Date
  released: boolean
  chauffeurId: string | null
  vehicleId: string | null
  einsatzId: string | null
  purpose: 'site_use' | 'transport' | null
  tourStatus: 'draft' | 'confirmed' | 'closed' | null
  packStatus: GaDispatchNeed['pack_status']
  packIds: string[]
}

/** Echte Transporttour (Backend `department_grossanlass_tour`); `underway`/`done` folgen aus den Fahraufträgen. */
export type GaDispoLiveTour = {
  id: string
  name: string
  status: GaDispatchTour['status']
  confirmed: boolean
  vehicleId: string | null
  chauffeurId: string | null
  trailer: string
  needIds: string[]
  stops: Array<{ id: string; kind: GaDispatchStop['kind']; placeId: string | null; label: string; plannedAt: string | null; needId: string | null }>
  startsAt: Date | null
  endsAt: Date | null
  conflicts: GaDispatchConflict[]
  events: GaDispatchTour['events']
  outcomes: Array<{ needId: string; outcome: 'delivered' | 'not_executed' | null }>
  /** In Ausführung oder abgeschlossen: nicht mehr änderbar. */
  locked: boolean
}

const needs = ref<GaDispoLiveNeed[]>([])
const tours = ref<GaDispoLiveTour[]>([])
const suggestions = ref<GaDispatchSuggestion[]>([])
const canManage = ref(false)
const changeRequests = ref<GaDispatchChangeRequest[]>([])
const drivers = ref<GaDispoDriver[]>([])
const vehicles = ref<GaDispoVehicle[]>([])
const now = ref<Date>(new Date())
const loading = ref(false)
const loaded = ref(false)
const failed = ref(false)

function pad(value: number): string {
  return String(value).padStart(2, '0')
}
function clock(date: Date): string {
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`
}
function midnight(date: Date): number {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
}

export function priorityOf(status: GaDispoNeed['status'], startsAt: Date, at: Date): GaDispoPriority {
  if (status === 'done') return 'low'
  const hours = (startsAt.getTime() - at.getTime()) / 3_600_000
  if (status === 'open' && hours < 24) return 'urgent'
  return hours > 24 * 7 ? 'low' : 'normal'
}

export function mapDispatchNeed(row: GaDispatchNeed, at: Date): GaDispoLiveNeed {
  const t = i18n.global.t
  const startsAt = parseLocalDate(row.starts_at)
  const endsAt = parseLocalDate(row.ends_at)
  const readyNote = row.ready_note === 'noPack' ? t('grossanlass.dispo.noPack') : row.ready_note ?? undefined
  return {
    id: row.id,
    source: row.source,
    title: row.title,
    from: row.from ?? t('grossanlass.dispo.depot'),
    to: row.to || row.target || '–',
    timingKind: 'window',
    timingLabel: `${clock(startsAt)}–${clock(endsAt)}`,
    dayOffset: Math.round((midnight(startsAt) - midnight(at)) / 86_400_000),
    hour: startsAt.getHours() + startsAt.getMinutes() / 60,
    priority: priorityOf(row.status, startsAt, at),
    ready: row.ready,
    readyNote,
    cargo: row.cargo,
    target: row.target,
    requirements: row.requirements.map((key) => t(`grossanlass.dispo.req.${key}`)),
    scope: row.scope,
    status: row.status,
    tourId: row.tour_id ?? undefined,
    tourStatus: row.tour_status,
    packStatus: row.pack_status,
    packIds: row.pack_ids,
    startsAt,
    endsAt,
    released: row.released,
    chauffeurId: row.chauffeur_user_id,
    vehicleId: row.vehicle_id,
    einsatzId: row.einsatz_id,
    purpose: row.purpose ?? null,
  }
}

export function mapDispatchTour(row: GaDispatchTour): GaDispoLiveTour {
  return {
    id: row.id,
    name: row.name,
    status: row.status,
    confirmed: row.confirmed,
    vehicleId: row.vehicle_id,
    chauffeurId: row.chauffeur_user_id,
    trailer: row.trailer ?? '',
    needIds: row.need_ids,
    stops: row.stops.map((stop) => ({ id: stop.id, kind: stop.kind, placeId: stop.place_id, label: stop.label, plannedAt: stop.planned_at, needId: stop.need_id })),
    startsAt: row.starts_at ? parseLocalDate(row.starts_at) : null,
    endsAt: row.ends_at ? parseLocalDate(row.ends_at) : null,
    conflicts: row.conflicts,
    events: row.events,
    outcomes: row.outcomes.map((item) => ({ needId: item.need_id, outcome: item.outcome })),
    locked: row.status === 'underway' || row.status === 'done' || row.status === 'closed',
  }
}

export function applyDispatch(data: GaDispatchOverview): void {
  const at = parseLocalDate(data.now)
  now.value = at
  needs.value = data.needs.map((row) => mapDispatchNeed(row, at))
  drivers.value = data.drivers
  vehicles.value = data.vehicles.map((row) => ({ id: row.id, name: row.name, kind: row.kind, trailerOk: row.trailer_ok, defect: row.defect }))
  tours.value = data.tours.map(mapDispatchTour)
  suggestions.value = data.suggestions
  canManage.value = data.can_manage
  changeRequests.value = data.change_requests ?? []
}

export async function loadDispatch(departmentId: string): Promise<void> {
  loading.value = true
  failed.value = false
  try {
    applyDispatch(await getGrossanlassDispatch(departmentId))
    loaded.value = true
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

export function errorMessage(error: unknown): string {
  const response = (error as { response?: { data?: { error?: string } } })?.response
  return response?.data?.error || (error as Error)?.message || ''
}

export type GaDispoActionResult = { ok: true } | { ok: false; error: string }

/** Führt eine Tour-Aktion aus und lädt die Disposition danach neu (ein Datenbestand, kein lokaler Zwischenstand). */
async function run(departmentId: string, action: () => Promise<unknown>): Promise<GaDispoActionResult> {
  try {
    await action()
  } catch (error) {
    await loadDispatch(departmentId)
    return { ok: false, error: errorMessage(error) }
  }
  await loadDispatch(departmentId)
  return { ok: true }
}

export const dispatchActions = {
  createTour: (departmentId: string, payload: GaDispatchTourPayload & { need_ids: string[] }) => run(departmentId, () => createGrossanlassDispatchTour(departmentId, payload)),
  updateTour: (departmentId: string, tourId: string, payload: GaDispatchTourPayload) => run(departmentId, () => updateGrossanlassDispatchTour(departmentId, tourId, payload)),
  deleteTour: (departmentId: string, tourId: string) => run(departmentId, () => deleteGrossanlassDispatchTour(departmentId, tourId)),
  addNeed: (departmentId: string, tourId: string, needId: string) => run(departmentId, () => addGrossanlassDispatchTourNeed(departmentId, tourId, needId)),
  removeNeed: (departmentId: string, tourId: string, needId: string) => run(departmentId, () => removeGrossanlassDispatchTourNeed(departmentId, tourId, needId)),
  replaceStops: (departmentId: string, tourId: string, stops: GaDispoLiveTour['stops']) =>
    run(departmentId, () =>
      replaceGrossanlassDispatchTourStops(
        departmentId,
        tourId,
        stops.map((stop) => ({ kind: stop.kind, place_id: stop.placeId, label: stop.label, planned_at: stop.plannedAt, need_id: stop.needId })),
      ),
    ),
  confirmTour: (departmentId: string, tourId: string) => run(departmentId, () => confirmGrossanlassDispatchTour(departmentId, tourId)),
  unconfirmTour: (departmentId: string, tourId: string) => run(departmentId, () => unconfirmGrossanlassDispatchTour(departmentId, tourId)),
  convertVehicleNeed: (departmentId: string, needId: string) => run(departmentId, () => convertGrossanlassDispatchVehicleNeed(departmentId, needId)),
  releaseNeed: (departmentId: string, needId: string) => run(departmentId, () => releaseGrossanlassDispatchNeed(departmentId, needId)),
  revokeRelease: (departmentId: string, needId: string) => run(departmentId, () => revokeGrossanlassDispatchRelease(departmentId, needId)),
  releaseTour: (departmentId: string, tourId: string) => run(departmentId, () => releaseGrossanlassDispatchTour(departmentId, tourId)),
  closeTour: (departmentId: string, tourId: string, note = '') => run(departmentId, () => closeGrossanlassDispatchTour(departmentId, tourId, note)),
  arriveManually: (departmentId: string, packId: string, reason: string) => run(departmentId, () => arriveGrossanlassPackManually(departmentId, packId, reason)),
  requestChange: (departmentId: string, needId: string, payload: GaChangeRequestPayload) => run(departmentId, () => requestGrossanlassTripChange(departmentId, needId, payload)),
  decideChange: (departmentId: string, requestId: string, payload: Parameters<typeof decideGrossanlassChangeRequest>[2]) => run(departmentId, () => decideGrossanlassChangeRequest(departmentId, requestId, payload)),
}

export function useGaDispo() {
  return {
    suggestions: computed(() => suggestions.value),
    changeRequests: computed(() => changeRequests.value),
    canManage: computed(() => canManage.value),
    needs: computed(() => needs.value),
    tours: computed(() => tours.value),
    drivers: computed(() => drivers.value),
    vehicles: computed(() => vehicles.value),
    now: computed(() => now.value),
    loading: computed(() => loading.value),
    /** Echte Transportdaten sind geladen (sonst zeigen Demo-Leitstände ihren Demo-State). */
    loaded: computed(() => loaded.value),
    failed: computed(() => failed.value),
  }
}

// ── Ableitungen (rein lesend) ──────────────────────────────────

export function liveTourById(id: string | null | undefined): GaDispoLiveTour | undefined {
  return tours.value.find((tour) => tour.id === id)
}
export function liveNeedById(id: string | null | undefined): GaDispoLiveNeed | undefined {
  return needs.value.find((need) => need.id === id)
}
export function liveDriverName(id: string | null | undefined): string {
  return drivers.value.find((driver) => driver.id === id)?.name ?? ''
}
export function liveVehicleName(id: string | null | undefined): string {
  return vehicles.value.find((vehicle) => vehicle.id === id)?.name ?? ''
}

const busyOf = (pick: (need: GaDispoLiveNeed) => string | null) =>
  new Set(needs.value.filter((need) => need.status === 'planned' || need.status === 'underway').map(pick).filter((id): id is string => !!id))

const busyTours = (pick: (tour: GaDispoLiveTour) => string | null) =>
  new Set(tours.value.filter((tour) => tour.status !== 'done').map(pick).filter((id): id is string => !!id))

export function liveFreeDrivers(): GaDispoDriver[] {
  const busy = new Set([...busyOf((need) => need.chauffeurId), ...busyTours((tour) => tour.chauffeurId)])
  return drivers.value.filter((driver) => !busy.has(driver.id))
}
export function liveFreeVehicles(): GaDispoVehicle[] {
  const busy = new Set([...busyOf((need) => need.vehicleId), ...busyTours((tour) => tour.vehicleId)])
  return vehicles.value.filter((vehicle) => !busy.has(vehicle.id) && !vehicle.defect)
}

export function liveCounts() {
  const list = needs.value
  return {
    open: list.filter((need) => need.status === 'open').length,
    ready: list.filter((need) => need.status === 'open' && need.ready).length,
    urgent: list.filter((need) => need.status !== 'done' && need.priority === 'urgent').length,
    planned: list.filter((need) => need.status === 'planned').length,
    underway: list.filter((need) => need.status === 'underway').length,
    driversFree: liveFreeDrivers().length,
    vehiclesFree: liveFreeVehicles().length,
  }
}

export function liveMatchesView(need: GaDispoLiveNeed, view: GaDispoView): boolean {
  switch (view) {
    case 'open':
    case 'planned':
    case 'underway':
    case 'done':
      return need.status === view
    case 'problems':
      return !!need.problem || (need.status === 'open' && !need.ready && need.priority === 'urgent') || !!liveTourById(need.tourId)?.conflicts.length
    case 'scope':
      return need.scope !== 'intern' && need.status !== 'done'
  }
}

const RANK: Record<GaDispoPriority, number> = { urgent: 0, normal: 1, low: 2 }
export function liveSortNeeds(list: GaDispoLiveNeed[]): GaDispoLiveNeed[] {
  return list.slice().sort((a, b) => RANK[a.priority] - RANK[b.priority] || a.startsAt.getTime() - b.startsAt.getTime())
}

/** Echte Tour in der Form des Leitstand-Modells (`GaDispoTour`), damit Anzeigen nur eine Datenquelle brauchen. */
export function liveTourAsDispoTour(tour: GaDispoLiveTour): GaDispoTour {
  const hour = (date: Date | null, fallback: number) => (date ? date.getHours() + date.getMinutes() / 60 : fallback)
  const byNeed = (id: string | null) => needs.value.find((need) => need.id === id)
  return {
    id: tour.id,
    name: tour.name,
    driverId: tour.chauffeurId ?? '',
    vehicleId: tour.vehicleId ?? '',
    trailer: tour.trailer,
    status: tour.status === 'underway' ? 'underway' : tour.status === 'done' || tour.status === 'closed' ? 'done' : 'planned',
    startHour: hour(tour.startsAt, 0),
    endHour: hour(tour.endsAt, 0),
    delayMin: 0,
    stops: tour.stops.map((stop) => {
      const need = byNeed(stop.needId)
      const done = stop.kind === 'unload' ? need?.status === 'done' : need?.status === 'underway' || need?.status === 'done'
      return { id: stop.id, kind: stop.kind, place: stop.label, label: need?.title ?? '', needId: stop.needId ?? undefined, done }
    }),
  }
}
