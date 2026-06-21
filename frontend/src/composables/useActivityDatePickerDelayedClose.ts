import { onBeforeUnmount, watch, type Ref } from 'vue'

/** Nach Schnellauswahl: Kalender kurz offen lassen, damit Auswahl sichtbar bleibt */
export const ACTIVITY_PRESET_CLOSE_DELAY_MS = 3000

export function useActivityDatePickerDelayedClose(menuOpen: Ref<boolean>) {
  let closeTimer: ReturnType<typeof setTimeout> | null = null

  function clearCloseTimer() {
    if (closeTimer) {
      clearTimeout(closeTimer)
      closeTimer = null
    }
  }

  function scheduleClose(delayMs = ACTIVITY_PRESET_CLOSE_DELAY_MS) {
    clearCloseTimer()
    closeTimer = setTimeout(() => {
      menuOpen.value = false
      closeTimer = null
    }, delayMs)
  }

  watch(menuOpen, (open) => {
    if (!open) clearCloseTimer()
  })

  onBeforeUnmount(clearCloseTimer)

  return { scheduleClose, clearCloseTimer }
}
