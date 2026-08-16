import { computed, ref, watch, type Ref } from 'vue'
import { useRoute } from 'vue-router'
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
  isValidJourneyStepForViewer,
  journeyStepsForViewer,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'
import { ONBOARDING_TOUR_QUERY } from '@/config/onboardingTours'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import { isJourneyStepWorkComplete } from '@/utils/materialJourneyStepWorkStatus'
import {
  journeyStepIndex,
  journeyStepsWithOpenWork,
  isQuickIssuePhaseClosed,
  isQuickReturnPhaseClosed,
  resolveEffectiveActiveJourneyStep,
} from '@/utils/materialJourneyNavigation'
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
  const route = useRoute()
  const { canManageMaterials } = useDepartmentMemberRole()

  const activity = ref<ActivityDetail | null>(null)
  const packItems = ref<ActivityPackItem[]>([])
  const packContainers = ref<ActivityPackContainer[]>([])
  const containerItemsByContainerId = ref<Record<string, ActivityPackContainerItem[]>>({})
  const cratePeekMaps = ref<MaterialJourneyCratePeekMaps>(emptyMaterialJourneyCratePeekMaps())
  const loading = ref(true)
  const hasLoaded = ref(false)
  const error = ref<string | null>(null)

  const profile = computed(() => packWorkflowProfileForActivityType(activity.value?.type ?? 'activity'))

  /** Tour darf Journey-Schritte vor dem DB-Checkpoint zeigen, wenn der User sie anklickt (Spotlight). */
  const allowFutureJourneyStepForTour = computed(() => {
    const raw = route.query[ONBOARDING_TOUR_QUERY]
    const tourId = Array.isArray(raw) ? raw[0] : raw
    return typeof tourId === 'string' && tourId.length > 0
  })

  const journeyMaterialContext = computed(() => ({
    packItems: packItems.value,
    packContainers: packContainers.value,
    containerItemsByContainerId: containerItemsByContainerId.value,
  }))

  const activeJourneyStep = computed((): JourneyStep => {
    if (!activity.value) return 'pack'
    return resolveEffectiveActiveJourneyStep(
      activity.value,
      profile.value,
      canManageMaterials.value,
      journeyMaterialContext.value,
    )
  })

  const stepsWithOpenWork = computed((): JourneyStep[] => {
    let open = journeyStepsWithOpenWork(activeJourneyStep.value, profile.value, {
      packItems: packItems.value,
      packContainers: packContainers.value,
      containerItemsByContainerId: containerItemsByContainerId.value,
    }, canManageMaterials.value)
    if (profile.value === 'logistics') return open
    if (isQuickIssuePhaseClosed(activity.value, profile.value, {
      packItems: packItems.value,
      packContainers: packContainers.value,
      containerItemsByContainerId: containerItemsByContainerId.value,
    })) {
      open = open.filter((step) => step !== 'issue')
    }
    // Nach Übergabe / Einlagern: Retour-Checkpoint administrativ geschlossen
    if (isQuickReturnPhaseClosed(activity.value, profile.value)) {
      open = open.filter((step) => step !== 'return')
    }
    return open
  })

  const journeyStepWorkComplete = computed(() => (step: JourneyStep) =>
    isJourneyStepWorkComplete(
      step,
      profile.value,
      packItems.value,
      packContainers.value,
      containerItemsByContainerId.value,
    ),
  )

  const steps = computed(() => journeyStepsForViewer(profile.value, canManageMaterials.value))

  const resolvedStep = computed((): JourneyStep => {
    const param = stepParam.value
    if (param && isValidJourneyStepForViewer(param, profile.value, canManageMaterials.value)) {
      return param as JourneyStep
    }
    if (!activity.value) return steps.value[0] ?? 'issue'
    return activeJourneyStep.value
  })

  const needsStepRedirect = computed(() => {
    const param = stepParam.value
    if (!param) return true
    if (!isValidJourneyStepForViewer(param, profile.value, canManageMaterials.value)) return true
    if (!activity.value) return false
    // Onboarding: packStep aus der Tour nicht auf den DB-Checkpoint zurückzwingen
    if (allowFutureJourneyStepForTour.value) return false
    const paramStep = param as JourneyStep
    const activeIdx = journeyStepIndex(activeJourneyStep.value, profile.value)
    const paramIdx = journeyStepIndex(paramStep, profile.value)
    if (paramIdx > activeIdx) return true
    return false
  })

  /** Ziel-Schritt bei Redirect (fehlend/ungültig/Zukunft → DB-aktiver Schritt). */
  const journeyStepRedirectTarget = computed((): JourneyStep => {
    if (needsStepRedirect.value) return activeJourneyStep.value
    return resolvedStep.value
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

  async function loadPackContainers(id: string): Promise<void> {
    const containers = await getActivityPackContainers(id).catch(() => [] as ActivityPackContainer[])
    packContainers.value = containers
    const map: Record<string, ActivityPackContainerItem[]> = {}
    await Promise.all(
      containers.map(async (c) => {
        map[c.id] = await getActivityPackContainerItems(id, c.id).catch(() => [])
      }),
    )
    containerItemsByContainerId.value = map
    cratePeekMaps.value = await loadMaterialJourneyCratePeekData(containers, packItems.value)
  }

  function applyContainerItem(containerId: string, item: ActivityPackContainerItem): void {
    const items = containerItemsByContainerId.value[containerId] ?? []
    const idx = items.findIndex((row) => row.id === item.id)
    const next =
      idx >= 0
        ? items.map((row, i) => (i === idx ? item : row))
        : [...items, item]
    containerItemsByContainerId.value = {
      ...containerItemsByContainerId.value,
      [containerId]: next,
    }
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

  async function reload(opts?: { forceFull?: boolean }): Promise<void> {
    if (!activityId.value) return
    const silent = !opts?.forceFull && hasLoaded.value
    if (!silent) {
      loading.value = true
      error.value = null
    }
    try {
      const [act, items] = await Promise.all([
        getActivity(activityId.value, departmentId.value),
        getPackItems(activityId.value).catch(() => [] as ActivityPackItem[]),
      ])
      activity.value = act
      packItems.value = items
      await loadPackContainers(activityId.value)
      hasLoaded.value = true
    } catch (e) {
      if (!silent) {
        error.value = e instanceof Error ? e.message : String(e)
        activity.value = null
        packItems.value = []
        packContainers.value = []
        containerItemsByContainerId.value = {}
        cratePeekMaps.value = emptyMaterialJourneyCratePeekMaps()
      }
    } finally {
      if (!silent) {
        loading.value = false
      }
    }
  }

  watch([departmentId, activityId], ([dept, id], [prevDept, prevId]) => {
    if ((prevId != null && id !== prevId) || (prevDept != null && dept !== prevDept)) {
      hasLoaded.value = false
    }
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
    journeyStepRedirectTarget,
    activeJourneyStep,
    /** @deprecated use activeJourneyStep */
    defaultJourneyStep: activeJourneyStep,
    journeyStepWorkComplete,
    stepsWithOpenWork,
    positionCount,
    isEarlyPackPreview,
    canManageMaterials,
    reload,
    reloadSilent,
    applyContainerItem,
  }
}
