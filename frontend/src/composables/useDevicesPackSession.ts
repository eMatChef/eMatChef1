import { onMounted, onUnmounted, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { getActivity } from '@/api/activities'
import {
  getPackItems,
  getPackProgress,
  type ActivityPackItem,
  type PackProgress,
} from '@/api/activityPackItems'

const POLL_MS = 4000

export function useDevicesPackSession(activityId: MaybeRefOrGetter<string>) {
  const loading = ref(true)
  const error = ref<string | null>(null)
  const activityName = ref('')
  const activityStatus = ref('')
  const activityType = ref('activity')
  const isPackListEditable = ref(false)
  const packItems = ref<ActivityPackItem[]>([])
  const progress = ref<PackProgress | null>(null)

  let pollTimer: ReturnType<typeof setInterval> | null = null

  async function refresh(silent = false) {
    const id = toValue(activityId)
    if (!id) return
    if (!silent) loading.value = true
    error.value = null
    try {
      const [act, items, prog] = await Promise.all([
        getActivity(id),
        getPackItems(id),
        getPackProgress(id),
      ])
      activityName.value = act.name || ''
      activityStatus.value = act.status || ''
      activityType.value = act.type || 'activity'
      isPackListEditable.value = Boolean(act.is_pack_list_editable)
      packItems.value = items
      progress.value = prog
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
      error.value = msg || 'load_failed'
      if (!silent) {
        packItems.value = []
        progress.value = null
      }
    } finally {
      if (!silent) loading.value = false
    }
  }

  function startPolling() {
    stopPolling()
    pollTimer = setInterval(() => {
      void refresh(true)
    }, POLL_MS)
  }

  function stopPolling() {
    if (pollTimer !== null) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  watch(
    () => toValue(activityId),
    (id) => {
      if (!id) {
        loading.value = false
        error.value = 'missing_activity'
        packItems.value = []
        progress.value = null
        return
      }
      void refresh(false)
    },
    { immediate: true },
  )

  onMounted(() => {
    startPolling()
  })

  onUnmounted(() => {
    stopPolling()
  })

  return {
    loading,
    error,
    activityName,
    activityStatus,
    activityType,
    isPackListEditable,
    packItems,
    progress,
    refresh,
  }
}
