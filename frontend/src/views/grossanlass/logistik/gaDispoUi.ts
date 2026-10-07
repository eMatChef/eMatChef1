import type { GaDispoPriority, GaDispoSource, GaDispoStatus, GaDispoStopKind } from './gaDispoMock'

export const SOURCE_ICON: Record<GaDispoSource, string> = {
  beschaffung: 'mdi-cart-outline',
  planung: 'mdi-calendar-clock',
  packen: 'mdi-package-variant-closed',
  ressort: 'mdi-home-group',
  retour: 'mdi-keyboard-return',
  werkstatt: 'mdi-wrench',
}

export const PRIORITY_COLOR: Record<GaDispoPriority, string> = {
  urgent: 'error',
  normal: 'primary',
  low: 'grey',
}

export const STATUS_COLOR: Record<GaDispoStatus, string> = {
  open: 'warning',
  planned: 'info',
  underway: 'primary',
  done: 'success',
}

export const STOP_ICON: Record<GaDispoStopKind, string> = {
  load: 'mdi-package-up',
  pickup: 'mdi-hand-extended-outline',
  unload: 'mdi-package-down',
}

export function hourLabel(hour: number): string {
  const h = Math.floor(hour)
  const m = Math.round((hour - h) * 60)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}
