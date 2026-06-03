import { computed, type ComputedRef } from 'vue'
import { useMdAndUp } from '@/composables/useMdAndUp'
import { useSmAndUp } from '@/composables/useSmAndUp'

/**
 * Responsive Host-Modifier für Activity-CSS (Phase 6 / Schritt 082).
 * Klassen entsprechen useSmAndUp (600px) und useMdAndUp (960px), nicht useDisplay().smAndDown.
 */
export function useDisplayHostClasses(hostClass: string): ComputedRef<Record<string, boolean>> {
  const smAndUp = useSmAndUp()
  const mdAndUp = useMdAndUp()

  return computed(() => ({
    [`${hostClass}--sm-and-up`]: smAndUp.value,
    [`${hostClass}--sm-down`]: !smAndUp.value,
    [`${hostClass}--md-and-up`]: mdAndUp.value,
    [`${hostClass}--sm-only`]: smAndUp.value && !mdAndUp.value,
  }))
}
