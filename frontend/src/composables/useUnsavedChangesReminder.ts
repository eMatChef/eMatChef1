import { onMounted, onUnmounted } from 'vue'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { useToast } from '@/composables/useToast'

const REMINDER_AFTER_MS = 5 * 60 * 1000 // 5 Minuten
const CHECK_INTERVAL_MS = 60 * 1000 // jede Minute prüfen

/**
 * Zeigt nach 5 Minuten eine Erinnerung, wenn ein Tab ungespeicherte Änderungen hat.
 * Nach dem Speichern wird die Erinnerung zurückgesetzt (nächste Änderung → wieder nach 5 Min).
 */
export function useUnsavedChangesReminder() {
  const detailTabsStore = useDetailTabsStore()
  const toast = useToast()
  const remindedFor = new Set<string>()
  let intervalId: ReturnType<typeof setInterval> | null = null

  function tabKey(tab: { type: string; id: string; departmentId: string }) {
    return `${tab.type}-${tab.id}-${tab.departmentId}`
  }

  function check() {
    const now = Date.now()
    for (const tab of detailTabsStore.tabs) {
      const key = tabKey(tab)
      if (!tab.hasUnsavedChanges) {
        remindedFor.delete(key)
        continue
      }
      if (!tab.dirtySince) continue
      if (remindedFor.has(key)) continue
      if (now - tab.dirtySince < REMINDER_AFTER_MS) continue

      remindedFor.add(key)
      toast.info(`Ungespeicherte Änderungen in „${tab.label}“ – Tab bleibt offen.`)
    }
  }

  onMounted(() => {
    intervalId = setInterval(check, CHECK_INTERVAL_MS)
  })

  onUnmounted(() => {
    if (intervalId) {
      clearInterval(intervalId)
      intervalId = null
    }
  })
}
