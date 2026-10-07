import type { GaCondition, GaFate } from './gaRueckbauMock'

export const FATE_ICON: Record<GaFate, string> = {
  lager: 'mdi-warehouse',
  reuse: 'mdi-recycle-variant',
  sale: 'mdi-tag-outline',
  return: 'mdi-keyboard-return',
  dispose: 'mdi-delete-outline',
  workshop: 'mdi-wrench',
}

export const FATE_COLOR: Record<GaFate, string> = {
  lager: 'primary',
  reuse: 'success',
  sale: 'info',
  return: 'warning',
  dispose: 'grey',
  workshop: 'error',
}

export const CONDITION_COLOR: Record<GaCondition, string> = {
  good: 'success',
  used: 'warning',
  damaged: 'error',
}
