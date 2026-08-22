import { onUnmounted, ref } from 'vue'

const DEFAULT_REVEAL_MS = 20_000

export function useTimedPasswordReveal(revealMs = DEFAULT_REVEAL_MS) {
  const visible = ref(false)
  let hideTimer: ReturnType<typeof setTimeout> | null = null

  function clearHideTimer() {
    if (hideTimer === null) return
    clearTimeout(hideTimer)
    hideTimer = null
  }

  function hide() {
    clearHideTimer()
    visible.value = false
  }

  function show() {
    clearHideTimer()
    visible.value = true
    hideTimer = setTimeout(() => {
      visible.value = false
      hideTimer = null
    }, revealMs)
  }

  function toggle() {
    if (visible.value) {
      hide()
    } else {
      show()
    }
  }

  onUnmounted(clearHideTimer)

  return { visible, toggle, hide }
}
