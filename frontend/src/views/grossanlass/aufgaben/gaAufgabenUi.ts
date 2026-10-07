import type { GaAufgabe, GaAufgabeKind, GaAufgabeStatus } from './gaAufgabenMock'
import { isOverdue, isSameDay } from './gaAufgabenMock'

export const KIND_ICON: Record<GaAufgabeKind, string> = {
  arbeit: 'mdi-hammer-wrench',
  material: 'mdi-package-variant-closed',
  logistik: 'mdi-truck-fast-outline',
  werkstatt: 'mdi-wrench',
}

export const KIND_ORDER: GaAufgabeKind[] = ['arbeit', 'material', 'logistik', 'werkstatt']

export const STATUS_COLOR: Record<GaAufgabeStatus | 'overdue', string> = {
  open: 'warning',
  progress: 'primary',
  done: 'success',
  overdue: 'error',
}

export function statusKey(task: GaAufgabe, now = new Date()): GaAufgabeStatus | 'overdue' {
  return isOverdue(task, now) ? 'overdue' : task.status
}

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

export function clock(date: Date): string {
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function dayLabel(date: Date, locale: string): string {
  return date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short' }).replace(/\.$/, '')
}

/** «09:00–12:00» am gleichen Tag, sonst «Mo., 5. Okt. 09:00 – Di., 6. Okt. 12:00». */
export function whenLabel(task: Pick<GaAufgabe, 'startsAt' | 'endsAt'>, locale: string): string {
  if (isSameDay(task.startsAt, task.endsAt)) {
    return `${clock(task.startsAt)}–${clock(task.endsAt)}`
  }
  return `${dayLabel(task.startsAt, locale)} ${clock(task.startsAt)} – ${dayLabel(task.endsAt, locale)} ${clock(task.endsAt)}`
}
