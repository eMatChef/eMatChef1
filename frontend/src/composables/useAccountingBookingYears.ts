import { ref, watch, type ComputedRef, type Ref } from 'vue'
import { getBookingYears } from '@/api/accountingBookings'

/**
 * Kalenderjahre, in denen für das Department mindestens eine Buchung existiert (API).
 */
export function useAccountingBookingYears(departmentId: Ref<string> | ComputedRef<string>) {
  const years = ref<number[]>([])
  const loadingYears = ref(false)

  async function refreshYears(): Promise<void> {
    const id = departmentId.value
    if (!id) {
      years.value = []
      return
    }
    loadingYears.value = true
    try {
      years.value = await getBookingYears(id)
    } catch {
      years.value = []
    } finally {
      loadingYears.value = false
    }
  }

  /** Aktuelles Jahr, falls Buchungen vorhanden — sonst jüngstes Jahr mit Buchungen. */
  function defaultYear(): number | null {
    const cy = new Date().getFullYear()
    if (years.value.includes(cy)) return cy
    return years.value.length > 0 ? years.value[0] : null
  }

  watch(departmentId, () => void refreshYears(), { immediate: true })

  return { years, loadingYears, refreshYears, defaultYear }
}
