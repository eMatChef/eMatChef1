import { computed, onUnmounted, ref, watch, type Ref } from 'vue'
import {
  getPackSessionPresence,
  patchPackSessionPresence,
  type PackSessionViewer,
} from '@/api/activityPackSession'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'

const HEARTBEAT_MS = 25_000

export function useMaterialJourneyPresence(options: {
  activityId: Ref<string>
  journeyStep: Ref<JourneyStep>
  enabled: Ref<boolean>
  shelf?: Ref<string | null | undefined>
  containerId?: Ref<string | null | undefined>
}) {
  const viewers = ref<PackSessionViewer[]>([])
  let heartbeatTimer: ReturnType<typeof setInterval> | null = null

  const presenceLabels = computed(() =>
    viewers.value.map((v) => {
      const shelf = v.shelf?.trim()
      return shelf ? `${v.displayName} · Regal ${shelf}` : v.displayName
    }),
  )

  async function sendHeartbeat(): Promise<void> {
    if (!options.enabled.value || !options.activityId.value) return
    try {
      viewers.value = await patchPackSessionPresence(options.activityId.value, {
        shelf: options.shelf?.value ?? null,
        containerId: options.containerId?.value ?? null,
        journeyStep: options.journeyStep.value,
      })
    } catch {
      /* presence is best-effort */
    }
  }

  async function refreshViewers(): Promise<void> {
    if (!options.enabled.value || !options.activityId.value) return
    try {
      viewers.value = await getPackSessionPresence(options.activityId.value)
    } catch {
      /* ignore */
    }
  }

  function startHeartbeat(): void {
    stopHeartbeat()
    if (!options.enabled.value) return
    void sendHeartbeat()
    heartbeatTimer = setInterval(() => {
      void sendHeartbeat()
    }, HEARTBEAT_MS)
  }

  function stopHeartbeat(): void {
    if (heartbeatTimer !== null) {
      clearInterval(heartbeatTimer)
      heartbeatTimer = null
    }
  }

  watch(
    [options.enabled, options.activityId],
    ([en]) => {
      if (en) {
        startHeartbeat()
        void refreshViewers()
      } else {
        stopHeartbeat()
        viewers.value = []
      }
    },
    { immediate: true },
  )

  watch(
    [options.journeyStep, options.shelf, options.containerId],
    () => {
      if (options.enabled.value) void sendHeartbeat()
    },
  )

  onUnmounted(() => {
    stopHeartbeat()
  })

  return {
    viewers,
    presenceLabels,
    refreshViewers,
    sendHeartbeat,
  }
}
