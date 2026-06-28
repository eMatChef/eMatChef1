/** Kanonische Aktivitäts-Status (siehe docs/activities/status.md) */
export const ACTIVITY_STATUS_KEYS = [
  'draft',
  'submitted',
  'approved',
  'packing',
  'packed',
  'transport_out',
  'at_event',
  'transport_back',
  'returned',
  'storing',
  'completed',
  'cancelled',
] as const

export type ActivityStatusKey = (typeof ACTIVITY_STATUS_KEYS)[number]

/** CSS-Klasse für status-dot / status-label / status-pill (Legacy issued → at_event). */
export function activityStatusClass(status: string | null | undefined): string {
  if (!status) return ''
  return status === 'issued' ? 'at_event' : status
}

/** i18n-Key-Suffix für Aktivitäts-Status-Labels */
export function activityStatusI18nKey(status: string | null | undefined): string {
  return activityStatusClass(status)
}
