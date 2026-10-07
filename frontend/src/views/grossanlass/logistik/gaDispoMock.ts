/**
 * UI-Prototyp «Logistik → Disposition»: Demo-Daten und lokaler State, ohne API und ohne Persistenz.
 * Begriffe: Transportbedarf = was muss von wo nach wo. Disposition = Bedarfe organisieren/kombinieren.
 * Tour = Fahrer + Fahrzeug + mehrere Stopps. Fahrauftrag = konkrete Ausführung für den Fahrer.
 */
import { logEvent } from '@/views/grossanlass/live/gaLiveEvents'
import { computed, reactive } from 'vue'

export type GaDispoSource = 'beschaffung' | 'planung' | 'packen' | 'ressort' | 'retour' | 'werkstatt'
export type GaDispoPriority = 'urgent' | 'normal' | 'low'
export type GaDispoStatus = 'open' | 'planned' | 'underway' | 'done'
export type GaDispoScope = 'intern' | 'gelaende' | 'extern'
export type GaDispoTimingKind = 'window' | 'latest' | 'fixed'
export type GaDispoProblemKind = 'delayed' | 'vehicleDefect' | 'supplierNotReady' | 'targetRefuses' | 'other'
export type GaDispoStopKind = 'load' | 'unload' | 'pickup'
export type GaDispoTourStatus = 'planned' | 'underway' | 'done'

export type GaDispoNeed = {
  id: string
  source: GaDispoSource
  title: string
  from: string
  to: string
  timingKind: GaDispoTimingKind
  /** Anzeige, z. B. «13:30–15:00», «spätestens 16:00», «fix 08:00» */
  timingLabel: string
  /** Tagesversatz (0 = heute) und Stunde, nur für Sortierung/Zeitplan */
  dayOffset: number
  hour: number
  priority: GaDispoPriority
  ready: boolean
  readyNote?: string
  cargo: string
  target: string
  requirements: string[]
  scope: GaDispoScope
  status: GaDispoStatus
  tourId?: string
  problem?: { kind: GaDispoProblemKind; note: string }
}

export type GaDispoStop = {
  id: string
  kind: GaDispoStopKind
  place: string
  label: string
  needId?: string
  done: boolean
}

export type GaDispoTour = {
  id: string
  name: string
  driverId: string
  vehicleId: string
  trailer: string
  status: GaDispoTourStatus
  /** Stunden (Dezimal) für den Zeitplan */
  startHour: number
  endHour: number
  delayMin: number
  stops: GaDispoStop[]
  problem?: { kind: GaDispoProblemKind; note: string }
}

export type GaDispoDriver = { id: string; name: string }
export type GaDispoVehicle = { id: string; name: string; kind: string; trailerOk: boolean; defect: boolean }

export const GA_DISPO_TRAILERS = ['Anhänger 2', 'Anhänger 4']

export type GaDispoState = {
  needs: GaDispoNeed[]
  tours: GaDispoTour[]
  drivers: GaDispoDriver[]
  vehicles: GaDispoVehicle[]
}

function stop(id: string, kind: GaDispoStopKind, place: string, label: string, needId?: string, done = false): GaDispoStop {
  return { id, kind, place, label, needId, done }
}

function build(): GaDispoState {
  return {
    drivers: [
      { id: 'd-peter', name: 'Peter' },
      { id: 'd-lea', name: 'Lea' },
      { id: 'd-marco', name: 'Marco' },
      { id: 'd-sina', name: 'Sina' },
    ],
    vehicles: [
      { id: 'v-sprinter-1', name: 'Sprinter 1', kind: 'Transporter', trailerOk: true, defect: false },
      { id: 'v-sprinter-2', name: 'Sprinter 2', kind: 'Transporter', trailerOk: true, defect: false },
      { id: 'v-pickup', name: 'Pickup 3', kind: 'Pickup', trailerOk: true, defect: false },
      { id: 'v-lkw', name: 'LKW 7.5 t', kind: 'LKW', trailerOk: false, defect: false },
    ],
    needs: [
      {
        id: 'n-holz',
        source: 'beschaffung',
        title: 'Häberli Holz → Zentrallager',
        from: 'Häberli Holz',
        to: 'Zentrallager',
        timingKind: 'window',
        timingLabel: '13:30–15:00',
        dayOffset: 0,
        hour: 13.5,
        priority: 'normal',
        ready: true,
        readyNote: 'Lieferant bereit, «Wir holen ab»',
        cargo: '14× Kantholz 6×12 cm, 1× Schwartenbund',
        target: 'Zentrallager · Wareneingang',
        requirements: ['Gewicht ca. 480 kg', 'Anhänger empfohlen'],
        scope: 'extern',
        status: 'planned',
        tourId: 't-1',
      },
      {
        id: 'n-pk42',
        source: 'packen',
        title: 'Palette PK-0042 → Bar West',
        from: 'Zentrallager',
        to: 'Bar West',
        timingKind: 'latest',
        timingLabel: 'spätestens 10:00',
        dayOffset: 0,
        hour: 10,
        priority: 'urgent',
        ready: true,
        readyNote: 'Palette fertig, Label gedruckt',
        cargo: 'Palette PK-0042 · Holz für Bar West',
        target: 'Demo-Bauten · Bar West',
        requirements: ['Palette', 'Gabelstapler am Ziel'],
        scope: 'gelaende',
        status: 'planned',
        tourId: 't-1',
      },
      {
        id: 'n-pk41',
        source: 'packen',
        title: 'Palette PK-0041 → Info-Pagode Eingang',
        from: 'Zentrallager',
        to: 'Info-Pagode Eingang',
        timingKind: 'fixed',
        timingLabel: 'fix 08:00',
        dayOffset: 1,
        hour: 8,
        priority: 'urgent',
        ready: true,
        readyNote: 'Palette fertig',
        cargo: 'Palette PK-0041 · Pagode komplett',
        target: 'Demo-Bauten · Info-Pagode Eingang',
        requirements: ['Palette'],
        scope: 'gelaende',
        status: 'open',
        problem: { kind: 'targetRefuses', note: 'Zugang zum Eingang bis 03.11. gesperrt.' },
      },
      {
        id: 'n-werkzeug',
        source: 'ressort',
        title: 'Werkzeug Lager A → Bar West',
        from: 'Lager A',
        to: 'Bar West',
        timingKind: 'latest',
        timingLabel: 'spätestens 16:00',
        dayOffset: 0,
        hour: 16,
        priority: 'urgent',
        ready: true,
        cargo: '2× Akkuschrauber, 1× Werkzeugkiste',
        target: 'Demo-Bauten · Bar West',
        requirements: ['Kleinmaterial'],
        scope: 'gelaende',
        status: 'open',
      },
      {
        id: 'n-pk4244',
        source: 'planung',
        title: 'Pack #44 → Festgelände Süd',
        from: 'Zentrallager',
        to: 'Festgelände Süd',
        timingKind: 'fixed',
        timingLabel: 'fix 08:00',
        dayOffset: 0,
        hour: 8,
        priority: 'normal',
        ready: true,
        cargo: 'Pack #42, Pack #44',
        target: 'Festgelände Süd',
        requirements: ['Palette'],
        scope: 'gelaende',
        status: 'underway',
        tourId: 't-2',
      },
      {
        id: 'n-zelt-retour',
        source: 'retour',
        title: 'Crew-Zelt → Zeltbau AG (Retour)',
        from: 'Crew-Zelt Backstage',
        to: 'Zeltbau AG',
        timingKind: 'window',
        timingLabel: '09:00–12:00',
        dayOffset: 2,
        hour: 9,
        priority: 'normal',
        ready: false,
        readyNote: 'Wartet: Zelt noch im Einsatz',
        cargo: 'Crew-Zelt 6×6 m, 4 Paletten',
        target: 'Verleiher Zeltbau AG',
        requirements: ['LKW', 'Gewicht ca. 1.2 t'],
        scope: 'extern',
        status: 'open',
      },
      {
        id: 'n-anhaenger',
        source: 'werkstatt',
        title: 'Anhänger 4 → Werkstatt Müller',
        from: 'Zentrallager',
        to: 'Werkstatt Müller',
        timingKind: 'window',
        timingLabel: 'bis morgen 17:00',
        dayOffset: 1,
        hour: 15,
        priority: 'low',
        ready: true,
        cargo: 'Anhänger 4 (Rücklicht defekt)',
        target: 'Werkstatt Müller, extern',
        requirements: ['Zugfahrzeug mit Anhängerkupplung'],
        scope: 'extern',
        status: 'open',
      },
      {
        id: 'n-festzelt',
        source: 'beschaffung',
        title: 'Festzelt AG → Catering-Zelt',
        from: 'Festzelt AG',
        to: 'Catering-Zelt',
        timingKind: 'window',
        timingLabel: '14:00–17:00',
        dayOffset: 1,
        hour: 14,
        priority: 'normal',
        ready: false,
        readyNote: 'Lieferant noch nicht bereit',
        cargo: 'Festzelt 6×12 m, 12× Festbank-Garnitur',
        target: 'Demo-Verpflegung · Catering-Zelt',
        requirements: ['LKW', 'Gewicht ca. 900 kg'],
        scope: 'extern',
        status: 'open',
        problem: { kind: 'supplierNotReady', note: 'Lieferant meldet Verzug um einen Tag.' },
      },
      {
        id: 'n-done',
        source: 'beschaffung',
        title: 'Sägerei Roth → Zentrallager',
        from: 'Sägerei Roth',
        to: 'Zentrallager',
        timingKind: 'window',
        timingLabel: '10:00–12:00',
        dayOffset: -1,
        hour: 10,
        priority: 'normal',
        ready: true,
        cargo: '60 lfm Latten',
        target: 'Zentrallager · Wareneingang',
        requirements: [],
        scope: 'extern',
        status: 'done',
      },
    ],
    tours: [
      {
        id: 't-1',
        name: 'Tour 1',
        driverId: 'd-peter',
        vehicleId: 'v-sprinter-2',
        trailer: 'Anhänger 2',
        status: 'planned',
        startHour: 9,
        endHour: 15.5,
        delayMin: 0,
        stops: [
          stop('s-1-1', 'load', 'Zentrallager', 'Palette PK-0042 laden', 'n-pk42'),
          stop('s-1-2', 'unload', 'Bar West', 'Palette abladen', 'n-pk42'),
          stop('s-1-3', 'pickup', 'Häberli Holz', 'Material abholen', 'n-holz'),
          stop('s-1-4', 'unload', 'Zentrallager', 'Material abladen', 'n-holz'),
        ],
      },
      {
        id: 't-2',
        name: 'Tour 2',
        driverId: 'd-lea',
        vehicleId: 'v-sprinter-1',
        trailer: '',
        status: 'underway',
        startHour: 8,
        endHour: 11,
        delayMin: 0,
        stops: [
          stop('s-2-1', 'load', 'Zentrallager', 'Pack #42 und #44 laden', 'n-pk4244', true),
          stop('s-2-2', 'unload', 'Festgelände Süd', 'Packs abladen', 'n-pk4244'),
        ],
      },
    ],
  }
}

const state = reactive<GaDispoState>(build())

export function resetGaDispoDemo(): void {
  const fresh = build()
  state.needs = fresh.needs
  state.tours = fresh.tours
  state.drivers = fresh.drivers
  state.vehicles = fresh.vehicles
}

export function useGaDispoMock() {
  return {
    needs: computed(() => state.needs),
    tours: computed(() => state.tours),
    drivers: computed(() => state.drivers),
    vehicles: computed(() => state.vehicles),
  }
}

// ── Ableitungen ────────────────────────────────────────────────

export function tourById(id: string | undefined): GaDispoTour | undefined {
  return state.tours.find((tour) => tour.id === id)
}
export function needById(id: string | undefined): GaDispoNeed | undefined {
  return state.needs.find((need) => need.id === id)
}
export function driverName(id: string): string {
  return state.drivers.find((driver) => driver.id === id)?.name ?? ''
}
export function vehicleName(id: string): string {
  return state.vehicles.find((vehicle) => vehicle.id === id)?.name ?? ''
}

const isActive = (tour: GaDispoTour) => tour.status !== 'done'

export function freeDrivers(): GaDispoDriver[] {
  const busy = new Set(state.tours.filter(isActive).map((tour) => tour.driverId))
  return state.drivers.filter((driver) => !busy.has(driver.id))
}
export function freeVehicles(): GaDispoVehicle[] {
  const busy = new Set(state.tours.filter(isActive).map((tour) => tour.vehicleId))
  return state.vehicles.filter((vehicle) => !busy.has(vehicle.id) && !vehicle.defect)
}

export function hasProblem(need: GaDispoNeed): boolean {
  if (need.problem) return true
  const tour = tourById(need.tourId)
  return !!tour?.problem
}

export function dispoCounts() {
  const needs = state.needs
  return {
    open: needs.filter((need) => need.status === 'open').length,
    ready: needs.filter((need) => need.status === 'open' && need.ready).length,
    urgent: needs.filter((need) => need.status !== 'done' && need.priority === 'urgent').length,
    planned: needs.filter((need) => need.status === 'planned').length,
    underway: needs.filter((need) => need.status === 'underway').length,
    driversFree: freeDrivers().length,
    vehiclesFree: freeVehicles().length,
  }
}

export type GaDispoView = 'open' | 'planned' | 'underway' | 'done' | 'problems' | 'scope'

export function matchesView(need: GaDispoNeed, view: GaDispoView): boolean {
  switch (view) {
    case 'open':
      return need.status === 'open'
    case 'planned':
      return need.status === 'planned'
    case 'underway':
      return need.status === 'underway'
    case 'done':
      return need.status === 'done'
    case 'problems':
      return hasProblem(need)
    case 'scope':
      return need.scope !== 'intern' && need.status !== 'done'
  }
}

const PRIORITY_RANK: Record<GaDispoPriority, number> = { urgent: 0, normal: 1, low: 2 }

export function sortNeeds(list: GaDispoNeed[]): GaDispoNeed[] {
  return list.slice().sort((a, b) => {
    if (PRIORITY_RANK[a.priority] !== PRIORITY_RANK[b.priority]) return PRIORITY_RANK[a.priority] - PRIORITY_RANK[b.priority]
    if (a.dayOffset !== b.dayOffset) return a.dayOffset - b.dayOffset
    return a.hour - b.hour
  })
}

/** Legs einer Tour: Strecke zwischen zwei Stopps, leer = nichts geladen. */
export type GaDispoLeg = { from: string; to: string; empty: boolean }

export function tourLegs(tour: GaDispoTour): GaDispoLeg[] {
  const legs: GaDispoLeg[] = []
  let loaded = 0
  for (let index = 0; index < tour.stops.length - 1; index += 1) {
    const current = tour.stops[index]!
    const next = tour.stops[index + 1]!
    if (current.kind === 'load' || current.kind === 'pickup') loaded += 1
    if (current.kind === 'unload') loaded = Math.max(0, loaded - 1)
    legs.push({ from: current.place, to: next.place, empty: loaded === 0 })
  }
  return legs
}

export type GaDispoSuggestionReason = 'passesTarget' | 'passesSource' | 'emptyLeg'
export type GaDispoSuggestion = { tour: GaDispoTour; reason: GaDispoSuggestionReason; place: string }

/** Bestehende Touren, die den Bedarf mitnehmen könnten (nur Hinweis, keine Optimierung). */
export function suggestionsFor(need: GaDispoNeed): GaDispoSuggestion[] {
  const out: GaDispoSuggestion[] = []
  for (const tour of state.tours) {
    if (tour.status === 'done' || tour.id === need.tourId) continue
    const toStop = tour.stops.find((item) => item.place === need.to && !item.done)
    if (toStop) {
      out.push({ tour, reason: 'passesTarget', place: need.to })
      continue
    }
    const fromStop = tour.stops.find((item) => item.place === need.from && !item.done)
    if (fromStop) {
      out.push({ tour, reason: 'passesSource', place: need.from })
      continue
    }
    const empty = tourLegs(tour).find((leg) => leg.empty)
    if (empty) out.push({ tour, reason: 'emptyLeg', place: `${empty.from} → ${empty.to}` })
  }
  return out
}

// ── lokale Aktionen ────────────────────────────────────────────

export type GaDispoAssign =
  | { mode: 'new'; driverId: string; vehicleId: string; trailer?: string }
  | { mode: 'existing'; tourId: string }

function stopsForNeed(need: GaDispoNeed, seed: string): GaDispoStop[] {
  const loadKind: GaDispoStopKind = need.source === 'beschaffung' || need.source === 'retour' ? 'pickup' : 'load'
  return [
    { id: `${seed}-a`, kind: loadKind, place: need.from, label: need.cargo, needId: need.id, done: false },
    { id: `${seed}-b`, kind: 'unload', place: need.to, label: need.title, needId: need.id, done: false },
  ]
}

let tourSeq = 3

export function assignNeed(needId: string, assign: GaDispoAssign): GaDispoTour | null {
  const need = needById(needId)
  if (!need || need.status === 'done') return null
  if (assign.mode === 'new') {
    if (!assign.driverId || !assign.vehicleId) return null
    const id = `t-${tourSeq}`
    tourSeq += 1
    const tour: GaDispoTour = {
      id,
      name: `Tour ${id.slice(2)}`,
      driverId: assign.driverId,
      vehicleId: assign.vehicleId,
      trailer: assign.trailer ?? '',
      status: 'planned',
      startHour: Math.max(6, need.hour - 1),
      endHour: need.hour + 1,
      delayMin: 0,
      stops: stopsForNeed(need, id),
    }
    state.tours.push(tour)
    need.tourId = id
    need.status = 'planned'
    return tour
  }
  const tour = tourById(assign.tourId)
  if (!tour || tour.status === 'done') return null
  const fresh = stopsForNeed(need, `${tour.id}-${need.id}`)
  // Laufende Tour: nach dem aktuellen Stopp einfügen, geplante Tour: vor einen abschliessenden Rückweg anhängen.
  const insertAt = tour.status === 'underway'
    ? Math.min(tour.stops.length, tour.stops.findIndex((item) => !item.done) + 1)
    : tour.stops.length
  tour.stops.splice(insertAt < 0 ? tour.stops.length : insertAt, 0, ...fresh)
  need.tourId = tour.id
  need.status = tour.status === 'underway' ? 'underway' : 'planned'
  return tour
}

export function startTour(tour: GaDispoTour): void {
  if (tour.status !== 'planned') return
  tour.status = 'underway'
  logEvent({ area: 'logistics', actor: `${driverName(tour.driverId)} · ${vehicleName(tour.vehicleId)}`, text: `${tour.name} gestartet`, detail: tour.stops.map((stop) => stop.place).join(' → '), project: '' })
  for (const item of tour.stops) {
    const need = needById(item.needId)
    if (need && need.status !== 'done') need.status = 'underway'
  }
}

/** Nächster Stopp erledigt; ist die Tour komplett, gelten die Bedarfe als erledigt. */
export function advanceTour(tour: GaDispoTour): void {
  if (tour.status === 'planned') startTour(tour)
  if (tour.status === 'done') return
  const next = tour.stops.find((item) => !item.done)
  if (next) {
    next.done = true
    logEvent({ area: 'logistics', actor: `${driverName(tour.driverId)} · ${vehicleName(tour.vehicleId)}`, text: `${tour.name}: ${next.label} bei ${next.place}`, detail: '', project: '' })
  }
  if (tour.stops.every((item) => item.done)) {
    tour.status = 'done'
    for (const item of tour.stops) {
      const need = needById(item.needId)
      if (need) need.status = 'done'
    }
  }
}

export function removeNeedFromTour(needId: string): void {
  const need = needById(needId)
  if (!need?.tourId) return
  const tour = tourById(need.tourId)
  if (tour) {
    tour.stops = tour.stops.filter((item) => item.needId !== needId)
    if (!tour.stops.length) state.tours = state.tours.filter((row) => row.id !== tour.id)
  }
  need.tourId = undefined
  need.status = 'open'
}

export function markNeedProblem(needId: string, kind: GaDispoProblemKind, note = ''): void {
  const need = needById(needId)
  if (!need) return
  need.problem = { kind, note }
  logEvent({ area: 'problem', severity: 'warning', actor: 'Disposition', text: `Problem: ${need.title}`, detail: kind, project: '' })
  if (kind === 'supplierNotReady') need.ready = false
}

export function resolveNeedProblem(needId: string): void {
  const need = needById(needId)
  if (!need) return
  need.problem = undefined
}

export function delayTour(tour: GaDispoTour, minutes: number): void {
  tour.delayMin += minutes
  tour.endHour += minutes / 60
  tour.problem = { kind: 'delayed', note: `+${tour.delayMin} min` }
  logEvent({ area: 'problem', severity: 'warning', actor: `${driverName(tour.driverId)} · ${vehicleName(tour.vehicleId)}`, text: `${tour.name} verspätet (+${tour.delayMin} min)`, detail: '', project: '' })
}

export function vehicleDefect(tour: GaDispoTour): void {
  const vehicle = state.vehicles.find((row) => row.id === tour.vehicleId)
  if (vehicle) vehicle.defect = true
  tour.problem = { kind: 'vehicleDefect', note: vehicle?.name ?? '' }
  logEvent({ area: 'problem', severity: 'error', actor: driverName(tour.driverId), text: `Fahrzeug defekt: ${vehicle?.name ?? ''}`, detail: tour.name, project: '' })
}

export function clearTourProblem(tour: GaDispoTour): void {
  tour.problem = undefined
}

/** Defektes Fahrzeug ersetzen (Demo: gleiche Tour bleibt bestehen). */
export function replaceVehicle(tour: GaDispoTour, vehicleId: string): void {
  tour.vehicleId = vehicleId
  if (tour.problem?.kind === 'vehicleDefect') tour.problem = undefined
}
