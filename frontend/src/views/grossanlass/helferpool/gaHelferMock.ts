/**
 * UI-Prototyp «Helferpool» (Grossanlass): Demo-Helfer mit Verfügbarkeit, Fähigkeiten und Führerausweisen.
 * Zuteilungen kommen aus der Aufgaben-Demo (Verantwortliche) und der Disposition-Demo (Fahrer),
 * «Helfer zuteilen» nutzt dieselbe Aufgaben-Logik. Keine eigene Aufgabenlogik, keine Optimierung, keine API.
 */
import { computed } from 'vue'
import {
  anlassAt,
  assignPerson,
  unassignPerson,
  useGaAufgabenMock,
  type GaAufgabe,
} from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { driverName, useGaDispoMock } from '@/views/grossanlass/logistik/gaDispoMock'

export const GA_SKILLS = ['Holzbau', 'Elektro', 'Stapler', 'Anhänger', 'LKW', 'Küche', 'Logistik', 'Aufbau', 'Abbau'] as const
export const GA_LICENSES = ['B', 'BE', 'C1', 'C1E', 'C'] as const
export type GaSkill = (typeof GA_SKILLS)[number]
export type GaLicense = (typeof GA_LICENSES)[number]
export type GaHelperStatus = 'free' | 'partial' | 'busy' | 'unavailable'

export type GaTimeWindow = { from: Date; to: Date }

export type GaHelper = {
  id: string
  name: string
  org: string
  email: string
  phone: string
  windows: GaTimeWindow[]
  skills: GaSkill[]
  licenses: GaLicense[]
  note: string
}

export type GaHelperAssignment = {
  id: string
  source: 'task' | 'tour'
  label: string
  origin: string
  startsAt: Date
  endsAt: Date
  kind: string
}

/** Anlass-Woche der Demo. */
export const GA_ANLASS_PERIOD: GaTimeWindow = { from: anlassAt(2, 0), to: anlassAt(8, 23, 59) }

const w = (day: number, from: number, to: number): GaTimeWindow => ({ from: anlassAt(day, from), to: anlassAt(day, to) })
const days = (list: number[], from: number, to: number): GaTimeWindow[] => list.map((day) => w(day, from, to))

export const GA_HELPERS: GaHelper[] = [
  { id: 'h-peter', name: 'Peter', org: 'Pfadi Löwen', email: 'peter@example.ch', phone: '079 111 11 01', windows: days([2, 3, 4, 5, 6], 7, 18), skills: ['Holzbau', 'Stapler', 'Aufbau'], licenses: ['B', 'BE'], note: 'Kann ab 07:00, Stapler-Ausweis vorhanden.' },
  { id: 'h-lea', name: 'Lea', org: 'Pfadi Löwen', email: 'lea@example.ch', phone: '079 111 11 02', windows: days([3, 4, 5, 6, 7], 8, 17), skills: ['Logistik', 'Anhänger', 'Aufbau'], licenses: ['B', 'BE', 'C1'], note: 'Fährt gerne, auch Anhänger.' },
  { id: 'h-marco', name: 'Marco', org: 'Cevi Basel', email: 'marco@example.ch', phone: '079 111 11 03', windows: days([3, 4, 5, 6], 7, 18), skills: ['Holzbau', 'Elektro', 'Aufbau'], licenses: ['B'], note: '' },
  { id: 'h-sina', name: 'Sina', org: 'Jubla Aarau', email: 'sina@example.ch', phone: '079 111 11 04', windows: days([4, 5, 6], 8, 16), skills: ['Küche', 'Aufbau'], licenses: ['B'], note: 'Nur vormittags am Montag.' },
  { id: 'h-jonas', name: 'Jonas', org: 'Cevi Basel', email: 'jonas@example.ch', phone: '079 111 11 05', windows: days([2, 3, 4, 5, 6, 7], 8, 18), skills: ['Elektro', 'Abbau'], licenses: ['B'], note: 'Elektriker-Lehre.' },
  { id: 'h-nora', name: 'Nora', org: 'Cevi Basel', email: 'nora@example.ch', phone: '079 111 11 06', windows: days([3, 4, 5], 7, 17), skills: ['Holzbau', 'Aufbau'], licenses: ['B'], note: '' },
  { id: 'h-tim', name: 'Tim', org: 'Jubla Aarau', email: 'tim@example.ch', phone: '079 111 11 07', windows: [...days([3, 4], 7, 17), w(7, 8, 18)], skills: ['Holzbau', 'Abbau'], licenses: ['B'], note: 'Gelernter Zimmermann.' },
  { id: 'h-ben', name: 'Ben', org: 'Pfadi Löwen', email: 'ben@example.ch', phone: '079 111 11 08', windows: days([6, 7, 8], 8, 18), skills: ['Abbau', 'Stapler'], licenses: ['B', 'C1'], note: 'Erst ab Freitag.' },
  { id: 'h-felix', name: 'Felix', org: 'Abteilung Falkenstein', email: 'felix@example.ch', phone: '079 111 11 09', windows: days([3, 4, 5], 6, 14), skills: ['Stapler', 'Logistik'], licenses: ['B', 'C1E'], note: 'Frühaufsteher.' },
  { id: 'h-mira', name: 'Mira', org: 'Abteilung Falkenstein', email: 'mira@example.ch', phone: '079 111 11 10', windows: days([4, 5, 6], 7, 15), skills: ['Küche'], licenses: [], note: 'Vegetarische Küche.' },
  { id: 'h-anja', name: 'Anja', org: 'Jubla Aarau', email: 'anja@example.ch', phone: '079 111 11 11', windows: days([4, 5, 6, 7], 8, 16), skills: ['Küche', 'Logistik'], licenses: ['B'], note: '' },
  { id: 'h-luca', name: 'Luca', org: 'Pfadi Löwen', email: 'luca@example.ch', phone: '079 111 11 12', windows: days([3, 4, 5, 6], 6, 18), skills: ['LKW', 'Logistik', 'Anhänger'], licenses: ['B', 'BE', 'C1', 'C'], note: 'Berufschauffeur.' },
  { id: 'h-jana', name: 'Jana', org: 'Pfadi Wolfsburg', email: 'jana@example.ch', phone: '079 111 11 13', windows: days([3, 4, 5, 6], 7, 17), skills: ['Holzbau', 'Elektro'], licenses: ['B'], note: '' },
  { id: 'h-ole', name: 'Ole', org: 'Pfadi Wolfsburg', email: 'ole@example.ch', phone: '079 111 11 14', windows: [w(4, 13, 18), w(5, 8, 17)], skills: ['Holzbau', 'Aufbau'], licenses: ['B'], note: 'Am 04.11. erst ab 13:00.' },
  { id: 'h-kevin', name: 'Kevin', org: 'Cevi Basel', email: 'kevin@example.ch', phone: '079 111 11 15', windows: [w(2, 8, 17), w(7, 8, 17)], skills: ['Aufbau', 'Abbau'], licenses: [], note: 'Nur am Anfang und am Ende.' },
]

// ── Hilfsfunktionen ────────────────────────────────────────────

const HOUR = 3_600_000

function overlapMs(a: GaTimeWindow, b: GaTimeWindow): number {
  return Math.max(0, Math.min(a.to.getTime(), b.to.getTime()) - Math.max(a.from.getTime(), b.from.getTime()))
}

function overlaps(a: GaTimeWindow, b: GaTimeWindow): boolean {
  return overlapMs(a, b) > 0
}

/** Zuteilungen eines Helfers: Aufgaben (als Verantwortlicher) und Touren (als Fahrer). */
export function assignmentsOf(helper: Pick<GaHelper, 'name'>): GaHelperAssignment[] {
  const { tasks } = useGaAufgabenMock()
  const { tours } = useGaDispoMock()
  const out: GaHelperAssignment[] = []
  for (const task of tasks.value) {
    if (task.people.includes(helper.name)) {
      out.push({ id: task.id, source: 'task', label: task.title, origin: task.origin[task.origin.length - 1] ?? '', startsAt: task.startsAt, endsAt: task.endsAt, kind: task.kind })
    }
  }
  for (const tour of tours.value) {
    if (tour.status === 'done' || driverName(tour.driverId) !== helper.name) continue
    const start = new Date()
    start.setHours(Math.floor(tour.startHour), Math.round((tour.startHour % 1) * 60), 0, 0)
    const end = new Date()
    end.setHours(Math.floor(tour.endHour), Math.round((tour.endHour % 1) * 60), 0, 0)
    out.push({ id: tour.id, source: 'tour', label: `${tour.name} (Fahrauftrag)`, origin: tour.stops.map((stop) => stop.place).join(' → '), startsAt: start, endsAt: end, kind: 'logistik' })
  }
  return out.sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
}

export function availableMs(helper: GaHelper, period: GaTimeWindow): number {
  return helper.windows.reduce((sum, window) => sum + overlapMs(window, period), 0)
}

/** Belegte Zeit innerhalb der Verfügbarkeit und des Zeitraums. */
export function busyMs(helper: GaHelper, period: GaTimeWindow): number {
  let total = 0
  for (const assignment of assignmentsOf(helper)) {
    const win = { from: assignment.startsAt, to: assignment.endsAt }
    for (const avail of helper.windows) {
      const clipped = { from: new Date(Math.max(avail.from.getTime(), period.from.getTime())), to: new Date(Math.min(avail.to.getTime(), period.to.getTime())) }
      total += overlapMs(win, clipped)
    }
  }
  return total
}

export function helperStatus(helper: GaHelper, period: GaTimeWindow): GaHelperStatus {
  const available = availableMs(helper, period)
  if (available <= 0) return 'unavailable'
  const busy = busyMs(helper, period)
  if (busy <= 0) return 'free'
  if (busy >= available) return 'busy'
  return 'partial'
}

export function hoursLabel(ms: number): string {
  return `${Math.round((ms / HOUR) * 10) / 10} h`
}

/** Freie Zeitfenster = Verfügbarkeit minus Zuteilungen (im Zeitraum). */
export function freeWindows(helper: GaHelper, period: GaTimeWindow): GaTimeWindow[] {
  const busy = assignmentsOf(helper).map((entry) => ({ from: entry.startsAt, to: entry.endsAt }))
  const out: GaTimeWindow[] = []
  for (const avail of helper.windows) {
    let pieces: GaTimeWindow[] = [{
      from: new Date(Math.max(avail.from.getTime(), period.from.getTime())),
      to: new Date(Math.min(avail.to.getTime(), period.to.getTime())),
    }].filter((piece) => piece.to > piece.from)
    for (const block of busy) {
      pieces = pieces.flatMap((piece) => {
        if (!overlaps(piece, block)) return [piece]
        const left = { from: piece.from, to: new Date(Math.min(piece.to.getTime(), block.from.getTime())) }
        const right = { from: new Date(Math.max(piece.from.getTime(), block.to.getTime())), to: piece.to }
        return [left, right].filter((part) => part.to.getTime() - part.from.getTime() > 0)
      })
    }
    out.push(...pieces)
  }
  return out
}

// ── Filter ─────────────────────────────────────────────────────

export type GaHelperFilters = {
  org: string
  skill: string
  license: string
  ressort: string
  availability: 'all' | 'free' | 'available'
  search: string
}

export const emptyHelperFilters = (): GaHelperFilters => ({ org: '', skill: '', license: '', ressort: '', availability: 'all', search: '' })

/** Ressort/Bereich aus den Aufgaben, in denen der Helfer eingeteilt ist. */
export function ressortsOf(helper: GaHelper): string[] {
  const { tasks } = useGaAufgabenMock()
  return [...new Set(tasks.value.filter((task) => task.people.includes(helper.name)).map((task) => task.ressort))]
}

export function matchesHelper(helper: GaHelper, filters: GaHelperFilters, period: GaTimeWindow): boolean {
  if (filters.org && helper.org !== filters.org) return false
  if (filters.skill && !helper.skills.includes(filters.skill as GaSkill)) return false
  if (filters.license && !helper.licenses.includes(filters.license as GaLicense)) return false
  if (filters.ressort && !ressortsOf(helper).includes(filters.ressort)) return false
  const status = helperStatus(helper, period)
  if (filters.availability === 'free' && status !== 'free') return false
  if (filters.availability === 'available' && (status === 'unavailable' || status === 'busy')) return false
  const q = filters.search.trim().toLowerCase()
  if (q && !`${helper.name} ${helper.org} ${helper.skills.join(' ')}`.toLowerCase().includes(q)) return false
  return true
}

export function statusCounts(period: GaTimeWindow): Record<GaHelperStatus, number> {
  const counts: Record<GaHelperStatus, number> = { free: 0, partial: 0, busy: 0, unavailable: 0 }
  for (const helper of GA_HELPERS) counts[helperStatus(helper, period)] += 1
  return counts
}

export function skillMatrix(): Array<{ skill: GaSkill; helpers: GaHelper[] }> {
  return GA_SKILLS.map((skill) => ({ skill, helpers: GA_HELPERS.filter((helper) => helper.skills.includes(skill)) }))
}

// ── Bedarf-Matching (nur Sichtbarmachen, keine Optimierung) ────

export type GaMatchKind = 'fit' | 'busy' | 'unavailable' | 'assigned'
export type GaHelperMatch = { helper: GaHelper; kind: GaMatchKind; conflict?: GaHelperAssignment }

export function needTasks(): GaAufgabe[] {
  return useGaAufgabenMock().tasks.value.filter((task) => task.helperNeed)
}

export function staffing(task: GaAufgabe) {
  const names = new Set(GA_HELPERS.map((helper) => helper.name))
  const assigned = task.people.filter((name) => names.has(name))
  return { assigned: assigned.length, need: task.helperNeed?.count ?? 0 }
}

/** Fachlich + zeitlich passende Personen für einen Aufgaben-Bedarf. */
export function matchNeed(task: GaAufgabe): GaHelperMatch[] {
  const need = task.helperNeed
  if (!need) return []
  const window = { from: task.startsAt, to: task.endsAt }
  const out: GaHelperMatch[] = []
  for (const helper of GA_HELPERS) {
    if (!helper.skills.includes(need.skill as GaSkill)) continue
    if (task.people.includes(helper.name)) {
      out.push({ helper, kind: 'assigned' })
      continue
    }
    const covers = helper.windows.some((entry) => entry.from <= window.from && entry.to >= window.to)
    if (!covers) {
      out.push({ helper, kind: 'unavailable' })
      continue
    }
    const conflict = assignmentsOf(helper).find((entry) => entry.id !== task.id && overlaps({ from: entry.startsAt, to: entry.endsAt }, window))
    out.push(conflict ? { helper, kind: 'busy', conflict } : { helper, kind: 'fit' })
  }
  const rank: Record<GaMatchKind, number> = { assigned: 0, fit: 1, busy: 2, unavailable: 3 }
  return out.sort((a, b) => rank[a.kind] - rank[b.kind])
}

/** UI-only: Helfer zuteilen (ruft die Aufgaben-Logik auf; erscheint damit auch im Auftrag). */
export function assignHelper(task: GaAufgabe, helper: GaHelper): boolean {
  if (task.people.includes(helper.name)) return false
  assignPerson(task, helper.name)
  return true
}

export function unassignHelper(task: GaAufgabe, helper: GaHelper): void {
  unassignPerson(task, helper.name)
}

export function useGaHelferMock() {
  const { tasks } = useGaAufgabenMock()
  return { helpers: computed(() => GA_HELPERS), tasks }
}
