import { computed, ref, watch, type Ref } from 'vue'
import { getActivity, type ActivityDetail } from '@/api/activities'
import {
  getActivityPackContainerItems,
  getActivityPackContainers,
  type ActivityPackContainer,
  type ActivityPackContainerItem,
} from '@/api/activityContainers'
import { getPackItems, type ActivityPackItem } from '@/api/activityPackItems'
import { packWorkflowProfileForActivityType } from '@/components/activities/packWorkflowProfile'
import {
  defaultJourneyStepForStatus,
  isValidJourneyStep,
  journeyStepsForProfile,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import {
  emptyMaterialJourneyCratePeekMaps,
  loadMaterialJourneyCratePeekData,
  type MaterialJourneyCratePeekMaps,
} from '@/composables/materialJourneyCratePeekLoad'

const POLL_ACTIVE_MS = 5_000
const POLL_IDLE_MS = 20_000

export function useMaterialJourneyData(
  departmentId: Ref<string>,
  activityId: Ref<string>,
  stepParam: Ref<string | undefined>,
  options?: {
    pollEnabled?: Ref<boolean>
    pollFast?: Ref<boolean>
    isPollBusy?: () => boolean
  },
) {
  const { canManageMaterials } = useDepartmentMemberRole()

  const activity = ref<ActivityDetail | null>(null)
  const packItems = ref<ActivityPackItem[]>([])
  const packContainers = ref<ActivityPackContainer[]>([])
  const containerItemsByContainerId = ref<Record<string, ActivityPackContainerItem[]>>({})
  const cratePeekMaps = ref<MaterialJourneyCratePeekMaps>(emptyMaterialJourneyCratePeekMaps())
  const loading = ref(true)
  const error = ref<string | null>(null)

  const profile = computed(() => packWorkflowProfileForActivityType(activity.value?.type ?? 'activity'))

  const steps = computed(() => journeyStepsForProfile(profile.value))

  const resolvedStep = computed((): JourneyStep => {
    const param = stepParam.value
    if (param && isValidJourneyStep(param, profile.value)) return param
    if (!activity.value) return 'pack'
    return defaultJourneyStepForStatus(
      activity.value.status ?? 'packing',
      profile.value,
      canManageMaterials.value,
    )
  })

  const needsStepRedirect = computed(() => {
    const param = stepParam.value
    if (!param) return true
    return !isValidJourneyStep(param, profile.value)
  })

  const positionCount = computed(() => {
    if (packItems.value.length > 0) return packItems.value.length
    return activity.value?.item_count ?? 0
  })

  const isEarlyPackPreview = computed(() => {
    const status = activity.value?.status
    if (!status) return false
    if (status === 'draft') return true
    if (['submitted', 'approved', 'packing'].includes(status) && !canManageMaterials.value) return true
    return false
  })

  async function loadPackContainers(activityId: string): Promise<void> {
    const containers = await getActivityPackContainers(activityId).catch(() => [] as ActivityPackContainer[])
    packContainers.value = containers
    const map: Record<string, ActivityPackContainerItem[]> = {}
    await Promise.all(
      containers.map(async (c) => {
        map[c.id] = await getActivityPackContainerItems(activityId, c.id).catch(() => [])
      }),
    )
    containerItemsByContainerId.value = map
    cratePeekMaps.value = await loadMaterialJourneyCratePeekData(containers, packItems.value)
  }

  async function reloadSilent(): Promise<void> {
    if (!activityId.value) return
    try {
      const [act, items] = await Promise.all([
        getActivity(activityId.value, departmentId.value),
        getPackItems(activityId.value).catch(() => [] as ActivityPackItem[]),
      ])
      activity.value = act
      packItems.value = items
      await loadPackContainers(activityId.value)
    } catch {
      /* silent refresh */
    }
  }

  async function reload(): Promise<void> {
    if (!activityId.value) return
    loading.value = true
    error.value = null
    try {
      const [act, items] = await Promise.all([
        getActivity(activityId.value, departmentId.value),
        getPackItems(activityId.value).catch(() => [] as ActivityPackItem[]),
      ])
      activity.value = act
      packItems.value = items
      await loadPackContainers(activityId.value)
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e)
      activity.value = null
      packItems.value = []
      packContainers.value = []
      containerItemsByContainerId.value = {}
      cratePeekMaps.value = emptyMaterialJourneyCratePeekMaps()
    } finally {
      loading.value = false
    }
  }

  watch([departmentId, activityId], () => {
    void reload()
  }, { immediate: true })

  const pollIntervalMs = computed(() =>
    options?.pollFast?.value ? POLL_ACTIVE_MS : POLL_IDLE_MS,
  )

  const pollEnabled = computed(() => {
    if (options?.pollEnabled?.value === false) return false
    return !loading.value && !!activity.value && !error.value
  })

  if (options?.pollEnabled !== undefined || options?.pollFast !== undefined) {
    useBackgroundPoll({
      intervalMs: pollIntervalMs,
      enabled: pollEnabled,
      isBusy: options?.isPollBusy,
      poll: reloadSilent,
    })
  }

  return {
    activity,
    packItems,
    packContainers,
    containerItemsByContainerId,
    cratePeekMaps,
    loading,
    error,
    profile,
    steps,
    resolvedStep,
    needsStepRedirect,
    positionCount,
    isEarlyPackPreview,
    canManageMaterials,
    reload,
    reloadSilent,
  }
}
