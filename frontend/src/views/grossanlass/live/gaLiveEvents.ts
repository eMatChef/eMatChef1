/**
 * Gemeinsames Live-Lagebild (Demo-State): Ereignislog, Fahrt-Tracking-Zustand und Display-Konfigurationen.
 * Keine WebSockets/SSE/GPS. Die bestehenden Mocks (Aufgaben, Packen, Disposition, Ausgabe, Weiterverkauf)
 * protokollieren hier ihre Aktionen; die Displays lesen dieselben Mocks und dieses Log.
 * Dieses Modul importiert absichtlich keine anderen Mocks (keine Zyklen).
 */
import { computed, reactive } from 'vue'

export type GaLiveArea = 'task' | 'material' | 'logistics' | 'sale' | 'problem'
export type GaLiveSeverity = 'info' | 'success' | 'warning' | 'error'

export type GaLiveEvent = {
  id: string
  at: Date
  area: GaLiveArea
  severity: GaLiveSeverity
  actor: string
  text: string
  detail: string
  /** Bauprojekt/Auftrag-Bezug (Titel), für Display-Filter */
  project: string
}

export type GaTrackingMode = 'none' | 'eta' | 'gps'

export type GaDisplayType = 'material' | 'logistik' | 'projekt' | 'gesamt'
export type GaDisplayConfig = {
  id: string
  name: string
  type: GaDisplayType
  location: string
  filter: { ressort: string; bereich: string; project: string }
  rotate: boolean
  rotateSec: number
}

type State = {
  events: GaLiveEvent[]
  /** Fahrten-Tracking pro Fahrauftrag (Aufgaben-ID) */
  tracking: Record<string, GaTrackingMode>
  /** Startzeit «Unterwegs» pro Fahrauftrag, Grundlage der ETA-Simulation */
  tripStarted: Record<string, Date>
  displays: GaDisplayConfig[]
  nextId: number
}

function minutesAgo(min: number): Date {
  return new Date(Date.now() - min * 60_000)
}

function build(): State {
  return {
    nextId: 20,
    tracking: { 'ga-demo-logistik-1': 'eta', 'ga-demo-logistik-2': 'none', 'ga-demo-nov-logistik': 'gps' },
    tripStarted: {},
    events: [
      { id: 'ev-1', at: minutesAgo(4), area: 'material', severity: 'success', actor: 'Peter', text: 'Pack PK-0042 fertig', detail: 'Bar West', project: 'Bar West' },
      { id: 'ev-2', at: minutesAgo(6), area: 'task', severity: 'success', actor: 'Lea', text: 'Aufgabe «Absperrung Eingang» erledigt', detail: 'Info-Pagode Eingang', project: 'Info-Pagode Eingang' },
      { id: 'ev-3', at: minutesAgo(8), area: 'logistics', severity: 'info', actor: 'Marco · Sprinter 2', text: 'Häberli Holz → Zentrallager: beladen, unterwegs', detail: 'ETA ca. 16:05', project: 'Bar West' },
      { id: 'ev-4', at: minutesAgo(14), area: 'material', severity: 'info', actor: 'Sina', text: 'Wareneingang Schwartenbund', detail: 'Zentrallager', project: 'Bar West' },
      { id: 'ev-5', at: minutesAgo(21), area: 'problem', severity: 'warning', actor: 'Jonas', text: 'Problem gemeldet: Stromverteiler fehlt', detail: 'Crew-Zelt Backstage', project: 'Crew-Zelt Backstage' },
    ],
    displays: [
      { id: 'dsp-material', name: 'Materiallager', type: 'material', location: 'Zentrallager, Eingang', filter: { ressort: '', bereich: '', project: '' }, rotate: true, rotateSec: 12 },
      { id: 'dsp-logistik', name: 'Disposition', type: 'logistik', location: 'Disposition, Büro', filter: { ressort: '', bereich: '', project: '' }, rotate: false, rotateSec: 12 },
      { id: 'dsp-baubuero', name: 'Baubüro', type: 'gesamt', location: 'Baubüro', filter: { ressort: '', bereich: '', project: '' }, rotate: true, rotateSec: 15 },
      { id: 'dsp-holzbau', name: 'Holzbau', type: 'projekt', location: 'Holzbau-Platz', filter: { ressort: 'Demo-Bauten', bereich: 'Bühne & Gerüst', project: '' }, rotate: false, rotateSec: 12 },
    ],
  }
}

const state = reactive<State>(build())

export function resetGaLiveDemo(): void {
  const fresh = build()
  state.events = fresh.events
  state.tracking = fresh.tracking
  state.tripStarted = fresh.tripStarted
  state.displays = fresh.displays
  state.nextId = fresh.nextId
}

export function useGaLive() {
  return {
    events: computed(() => state.events),
    tracking: computed(() => state.tracking),
    tripStarted: computed(() => state.tripStarted),
    displays: computed(() => state.displays),
  }
}

/** Von den Mocks aufgerufen, wenn sich etwas ändert. Neueste Ereignisse stehen vorne. */
export function logEvent(input: Partial<GaLiveEvent> & Pick<GaLiveEvent, 'area' | 'actor' | 'text'>): GaLiveEvent {
  const event: GaLiveEvent = {
    id: `ev-${state.nextId}`,
    at: new Date(),
    severity: 'info',
    detail: '',
    project: '',
    ...input,
  }
  state.nextId += 1
  state.events.unshift(event)
  if (state.events.length > 60) state.events.length = 60
  return event
}

export function recentEvents(limit = 8, match?: (event: GaLiveEvent) => boolean): GaLiveEvent[] {
  const list = match ? state.events.filter(match) : state.events
  return list.slice(0, limit)
}

// ── Fahrten ────────────────────────────────────────────────────

export function trackingOf(taskId: string): GaTrackingMode {
  return state.tracking[taskId] ?? 'none'
}
export function setTracking(taskId: string, mode: GaTrackingMode): void {
  state.tracking[taskId] = mode
}
export function markTripStarted(taskId: string, at = new Date()): void {
  state.tripStarted[taskId] = at
}
export function clearTripStarted(taskId: string): void {
  delete state.tripStarted[taskId]
}

/**
 * Geschätzter Fortschritt auf der Route (0–1) aus Startzeit und geschätzter Fahrzeit.
 * Nur Simulation: klar als «geschätzt» anzuzeigen.
 */
export function etaProgress(taskId: string, etaMinutes: number, now = new Date()): number | null {
  const start = state.tripStarted[taskId]
  if (!start || etaMinutes <= 0) return null
  return Math.min(1, Math.max(0, (now.getTime() - start.getTime()) / (etaMinutes * 60_000)))
}

export function etaAt(taskId: string, etaMinutes: number): Date | null {
  const start = state.tripStarted[taskId]
  return start ? new Date(start.getTime() + etaMinutes * 60_000) : null
}

// ── Displays ───────────────────────────────────────────────────

export function displayById(id: string): GaDisplayConfig | undefined {
  return state.displays.find((row) => row.id === id)
}

export function saveDisplay(config: Omit<GaDisplayConfig, 'id'> & { id?: string }): GaDisplayConfig {
  const existing = config.id ? displayById(config.id) : undefined
  if (existing) {
    Object.assign(existing, { ...config, id: existing.id })
    return existing
  }
  const created: GaDisplayConfig = { ...config, id: `dsp-${state.nextId}` }
  state.nextId += 1
  state.displays.push(created)
  return created
}

export function removeDisplay(id: string): void {
  state.displays = state.displays.filter((row) => row.id !== id)
}
