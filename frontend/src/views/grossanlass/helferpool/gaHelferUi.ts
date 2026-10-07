import type { GaHelperStatus, GaTimeWindow } from './gaHelferMock'

export const STATUS_COLOR: Record<GaHelperStatus, string> = {
  free: 'success',
  partial: 'warning',
  busy: 'error',
  unavailable: 'grey',
}

const pad = (n: number) => String(n).padStart(2, '0')
export const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`

export function windowLabel(window: GaTimeWindow, locale: string): string {
  const day = window.from.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'numeric' })
  const sameDay = window.from.toDateString() === window.to.toDateString()
  if (sameDay) return `${day} ${clock(window.from)}–${clock(window.to)}`
  const endDay = window.to.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'numeric' })
  return `${day} ${clock(window.from)} – ${endDay} ${clock(window.to)}`
}
