import { onMounted, onUnmounted, watch, type MaybeRefOrGetter, toValue } from 'vue'

/** Intervall-Polling im Hintergrund; pausiert bei verstecktem Tab, optional bei Busy. */
export function useBackgroundPoll(options: {
  intervalMs: MaybeRefOrGetter<number>
  enabled: MaybeRefOrGetter<boolean>
  isBusy?: () => boolean
  poll: () => void | Promise<void>
}) {
  let timer: ReturnType<typeof setInterval> | null = null

  function tick(): void {
    if (document.hidden) return
    if (options.isBusy?.()) return
    void options.poll()
  }

  function start(): void {
    stop()
    if (!toValue(options.enabled)) return
    timer = setInterval(tick, toValue(options.intervalMs))
  }

  function stop(): void {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  function onVisibilityChange(): void {
    if (!document.hidden) tick()
  }

  watch(
    () => toValue(options.enabled),
    (en) => {
      if (en) start()
      else stop()
    },
    { immediate: true },
  )

  watch(
    () => toValue(options.intervalMs),
    () => {
      if (toValue(options.enabled)) start()
    },
  )

  onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange)
  })

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange)
    stop()
  })

  return { tick, stop }
}
