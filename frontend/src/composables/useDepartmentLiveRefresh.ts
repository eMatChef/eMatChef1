import { type MaybeRefOrGetter, toValue } from 'vue'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'

/** Standard-Intervall für Listen/Dashboard (andere User, ohne F5). */
export const DEPARTMENT_LIVE_REFRESH_MS = 30_000

/**
 * Periodisches Nachladen im sichtbaren Tab (pausiert bei hidden / optional busy).
 * Für Dashboard und Aktivitäten-Übersicht — damit Änderungen anderer User erscheinen.
 */
export function useDepartmentLiveRefresh(options: {
  departmentId: MaybeRefOrGetter<string | undefined>
  reload: (opts?: { silent?: boolean }) => void | Promise<void>
  enabled?: MaybeRefOrGetter<boolean>
  isBusy?: () => boolean
  intervalMs?: number
}) {
  const intervalMs = options.intervalMs ?? DEPARTMENT_LIVE_REFRESH_MS

  return useBackgroundPoll({
    intervalMs,
    enabled: () => {
      if (!toValue(options.departmentId)) return false
      if (options.enabled !== undefined && !toValue(options.enabled)) return false
      return true
    },
    isBusy: options.isBusy,
    poll: () => options.reload({ silent: true }),
  })
}
