import type { GrossanlassProcurementStatus } from '@/api/grossanlassProcurement'

export function procurementStatusLabel(
  status: GrossanlassProcurementStatus | string,
  t: (key: string) => string,
): string {
  const key = `grossanlass.beschaffung.status.${status}`
  const translated = t(key)
  return translated !== key ? translated : status
}

export function procurementStatusClass(status: string): string {
  return `proc-status--${status.replace(/_/g, '-')}`
}

export const PROCUREMENT_STATUS_ORDER: GrossanlassProcurementStatus[] = [
  'bedarf',
  'offerte_eingeholt',
  'budgetiert',
  'bestellt',
  'teilweise_erhalten',
  'erhalten',
]
