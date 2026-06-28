import { computed, ref } from 'vue'

/**
 * Einheitliches Tab-Laden in der Aktivitätsdetail-Ansicht.
 *
 * Zusammenspiel:
 * - `ActivityDetailTabPane` — Wechsel per v-show (kein Vuetify-Slide)
 * - `ActivityTabPanelShell` — Spinner nur beim Erstladen, sonst Soft-Refresh-Balken
 * - `useActivityTabLoad` — Lade-Logik für alle Daten-Tabs
 */
export function useActivityTabLoad() {
  const isLoading = ref(false)
  const isRefreshing = ref(false)
  const hasLoaded = ref(false)

  const showFullLoading = computed(() => isLoading.value)

  function resetTabLoad(): void {
    hasLoaded.value = false
  }

  function markHydrated(): void {
    hasLoaded.value = true
  }

  async function withTabLoad<T>(task: () => Promise<T>, opts?: { forceFull?: boolean }): Promise<T> {
    const silent = !opts?.forceFull && hasLoaded.value
    if (silent) {
      isRefreshing.value = true
    } else {
      isLoading.value = true
    }
    try {
      const result = await task()
      hasLoaded.value = true
      return result
    } finally {
      isLoading.value = false
      isRefreshing.value = false
    }
  }

  return {
    isLoading,
    isRefreshing,
    showFullLoading,
    hasLoaded,
    resetTabLoad,
    markHydrated,
    withTabLoad,
  }
}
