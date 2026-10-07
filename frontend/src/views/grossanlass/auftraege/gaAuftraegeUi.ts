import type { GaOrderStatus, GaOrderType } from './gaAuftraegeMock'

export const TYPE_ICON: Record<GaOrderType, string> = { order: 'mdi-clipboard-text-outline', build: 'mdi-hammer-wrench' }
export const TYPE_COLOR: Record<GaOrderType, string> = { order: 'info', build: 'deep-orange' }
export const STATUS_COLOR: Record<GaOrderStatus, string> = { draft: 'grey', planned: 'info', active: 'primary', done: 'success' }

const pad = (n: number) => String(n).padStart(2, '0')

export function toInput(date: Date | null): string {
  if (!date) return ''
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}
export function fromInput(value: string): Date | null {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}
export function rangeLabel(from: Date | null, to: Date | null, locale: string): string {
  if (!from || !to) return '–'
  const day = (date: Date) => date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'numeric' })
  const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`
  if (from.toDateString() === to.toDateString()) return `${day(from)} ${clock(from)}–${clock(to)}`
  return `${day(from)} ${clock(from)} – ${day(to)} ${clock(to)}`
}
