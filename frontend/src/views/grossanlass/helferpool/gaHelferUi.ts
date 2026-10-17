import type { GaCoverage, GaHelper, GaStaffingNeedRow } from '@/api/grossanlassStaffing'

/** Reine Hilfen für den Helferpool (Anzeige, keine Geschäftslogik): Zeitformat, Deckungsstatus, Zeitplan-Balken. */

const pad = (n: number) => String(n).padStart(2, '0')

export function fullName(helper: Pick<GaHelper, 'first_name' | 'last_name'>): string {
  return `${helper.first_name} ${helper.last_name}`.trim()
}

/** `YYYY-MM-DD` + `HH:mm` → lokaler ISO-Zeitpunkt ohne Zeitzone, wie ihn die API erwartet und liefert. */
export function toIso(date: string, time: string): string {
  return date && time ? `${date}T${time.slice(0, 5)}:00` : ''
}

export function splitIso(iso: string | null | undefined): { date: string; time: string } {
  if (!iso) return { date: '', time: '08:00' }
  return { date: iso.slice(0, 10), time: iso.slice(11, 16) || '00:00' }
}

export function parseIso(iso: string): Date {
  const [d = '', t = '00:00'] = iso.split('T')
  const [y = 0, m = 1, day = 1] = d.split('-').map(Number)
  const [h = 0, min = 0] = t.split(':').map(Number)
  return new Date(y, m - 1, day, h, min, 0, 0)
}

export function clock(iso: string): string {
  return iso.slice(11, 16)
}

export function windowLabel(fromIso: string, toIso: string, locale: string): string {
  const from = parseIso(fromIso)
  const to = parseIso(toIso)
  const day = (date: Date) => date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'numeric' })
  const time = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`
  if (from.toDateString() === to.toDateString()) return `${day(from)} ${time(from)}–${time(to)}`
  return `${day(from)} ${time(from)} – ${day(to)} ${time(to)}`
}

export type NeedState = 'undated' | 'open' | 'partial' | 'covered' | 'over'

/** Deckungsstatus eines Bedarfs aus der berechneten Deckung (nie aus gespeicherten Zählern). */
export function needState(row: Pick<GaStaffingNeedRow, 'dated' | 'required_count' | 'assigned_count' | 'coverage'>): NeedState {
  if (!row.dated || !row.coverage) return 'undated'
  if (row.coverage.overbooked) return 'over'
  if (row.required_count === 0 || row.coverage.gaps.length === 0) return 'covered'
  return row.assigned_count > 0 ? 'partial' : 'open'
}

export const NEED_STATE_COLOR: Record<NeedState, string> = {
  undated: 'grey',
  open: 'error',
  partial: 'warning',
  covered: 'success',
  over: 'info',
}

export function percent(ratio: number | null | undefined): string {
  return ratio === null || ratio === undefined ? '–' : `${Math.round(ratio * 100)} %`
}

/** Grösste Lücke eines Bedarfs (fehlende Personen in einem Abschnitt), für die Kurzanzeige. */
export function maxMissing(coverage: GaCoverage | null): number {
  return coverage ? coverage.gaps.reduce((max, gap) => Math.max(max, gap.missing), 0) : 0
}

/** Zeitplan-Balken: Position und Breite in Prozent des Bereichs [start, start + days). */
export function barStyle(fromIso: string, toIso: string, start: Date, days: number): Record<string, string> {
  const total = days * 86_400_000
  const left = Math.max(0, (parseIso(fromIso).getTime() - start.getTime()) / total) * 100
  const right = Math.min(1, (parseIso(toIso).getTime() - start.getTime()) / total) * 100
  return { left: `${left}%`, width: `${Math.max(0, right - left)}%`, display: right <= left ? 'none' : '' }
}

export function dayList(start: Date, days: number): Date[] {
  return Array.from({ length: days }, (_, i) => new Date(start.getFullYear(), start.getMonth(), start.getDate() + i))
}

export function dateKey(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** Zeitraum des Zeitplans: Start (Datum) und Dauer in Tagen → Anfrage-Grenzen. */
export function scheduleRange(startDate: string, days: number): { from: string; to: string; start: Date } {
  const start = parseIso(`${startDate}T00:00:00`)
  const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + days)
  return { from: toIso(startDate, '00:00'), to: toIso(dateKey(end), '00:00'), start }
}

/** Frühester Start der datierten Bedarfe (als Datum), sonst heute. */
export function defaultScheduleStart(needs: GaStaffingNeedRow[], today = new Date()): string {
  const starts = needs.filter((n) => n.starts_at).map((n) => n.starts_at as string).sort()
  return starts[0] ? starts[0].slice(0, 10) : dateKey(today)
}

export type FunctionRow = { label: string; required: number; assigned: number; fill: number | null; needs: number }

/**
 * Funktionswünsche (`label`) über alle Bedarfe: benötigte Personen und zeitgewichtete Deckung. Jeder Bedarf zählt einmal;
 * Funktionswünsche sind Aufteilungen des Gesamtbedarfs einer Aufgabe und werden hier nicht zusätzlich zum Rest addiert.
 */
export function functionRows(needs: GaStaffingNeedRow[]): FunctionRow[] {
  const rows = new Map<string, { required: number; assigned: number; covered: number; weight: number; needs: number }>()
  for (const need of needs) {
    if (need.required_count <= 0) continue
    const row = rows.get(need.label) ?? { required: 0, assigned: 0, covered: 0, weight: 0, needs: 0 }
    row.required += need.required_count
    row.assigned += need.assigned_count
    row.needs += 1
    if (need.coverage) {
      const personMinutes = need.required_count * need.coverage.minutes
      row.weight += personMinutes
      row.covered += (need.coverage.fill_ratio ?? 0) * personMinutes
    }
    rows.set(need.label, row)
  }
  return [...rows.entries()]
    .map(([label, r]) => ({ label, required: r.required, assigned: r.assigned, fill: r.weight > 0 ? Math.round((r.covered / r.weight) * 10000) / 10000 : null, needs: r.needs }))
    .sort((a, b) => (a.label === '' ? 1 : b.label === '' ? -1 : a.label.localeCompare(b.label)))
}

export function conflictMessageKey(kind: string): string {
  return `grossanlass.helferpool.conflict.${kind}`
}
