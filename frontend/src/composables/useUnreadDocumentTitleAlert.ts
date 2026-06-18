import { onUnmounted, watch, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { i18n } from '@/i18n'
import { applyPageHead, resolvePageHead } from '@/composables/usePageHead'

const BLINK_MS = 1200

/**
 * Wechselt den Browser-Tab-Titel, wenn ungelesene Meldungen vorliegen und der Tab im Hintergrund ist.
 */
export function useUnreadDocumentTitleAlert(unreadCount: Ref<number>) {
  const route = useRoute()
  let intervalId: ReturnType<typeof setInterval> | null = null
  let showingAlert = false

  function baseHead() {
    return resolvePageHead(route)
  }

  function stopBlink() {
    if (intervalId !== null) {
      clearInterval(intervalId)
      intervalId = null
    }
    showingAlert = false
  }

  function restoreTitle() {
    stopBlink()
    const { title, description } = baseHead()
    applyPageHead(title, description, route)
  }

  function alertTitle(): string {
    const n = unreadCount.value
    return i18n.global.t('layout.notifications.tabAlertTitle', { count: n })
  }

  function startBlink() {
    if (intervalId !== null) return
    const { description } = baseHead()
    const normalTitle = baseHead().title
    const alert = alertTitle()
    showingAlert = false
    intervalId = setInterval(() => {
      showingAlert = !showingAlert
      applyPageHead(showingAlert ? alert : normalTitle, description, route)
    }, BLINK_MS)
  }

  function sync() {
    if (unreadCount.value <= 0 || !document.hidden) {
      restoreTitle()
      return
    }
    startBlink()
  }

  function onVisibilityChange() {
    if (document.hidden) {
      sync()
    } else {
      restoreTitle()
    }
  }

  watch(unreadCount, sync)
  watch(
    () => route.fullPath,
    () => {
      if (intervalId === null) {
        restoreTitle()
      }
    },
  )

  document.addEventListener('visibilitychange', onVisibilityChange)
  onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange)
    restoreTitle()
  })
}
