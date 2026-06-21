import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getDepartmentOnboardingStatus } from '@/api/departmentSettings'
import { useDepartmentOnboardingAccess } from '@/composables/useDepartmentOnboardingAccess'
import {
  countOpenChecklistItems,
  countResolvedChecklistItems,
  isChecklistItemDone,
  isChecklistItemResolved,
  isChecklistItemSkipped,
  ONBOARDING_CHECKLIST_ITEMS,
  refreshOnboardingCompletionStatus,
  skipChecklistItem,
  type OnboardingChecklistItemDef,
} from '@/utils/onboardingChecklist'
import {
  createDefaultOnboardingState,
  readOnboardingState,
  type DepartmentOnboardingState,
} from '@/utils/departmentOnboarding'

export function useOnboardingChecklist() {
  const route = useRoute()
  const authStore = useAuthStore()
  const { canUseOnboarding, profileId } = useDepartmentOnboardingAccess()

  const departmentId = computed(() => {
    const fromRoute = route.params.departmentId
    if (typeof fromRoute === 'string' && fromRoute) return fromRoute
    return authStore.activeDepartmentId || ''
  })

  const isLoading = ref(false)
  const backendDone = ref<boolean | null>(null)
  const onboardingState = ref<DepartmentOnboardingState>(createDefaultOnboardingState())

  const skipped = computed(() => onboardingState.value.skipped || {})

  const openCount = computed(() =>
    countOpenChecklistItems(onboardingState.value.completed, skipped.value)
  )

  const resolvedCount = computed(() =>
    countResolvedChecklistItems(onboardingState.value.completed, skipped.value)
  )

  const isFullyDone = computed(() => {
    if (backendDone.value === true) return true
    return openCount.value === 0
  })

  const progressPercent = computed(() =>
    Math.round((resolvedCount.value / ONBOARDING_CHECKLIST_ITEMS.length) * 100)
  )

  const checklistItems = computed(() =>
    ONBOARDING_CHECKLIST_ITEMS.map((def) => ({
      ...def,
      alwaysResolved: def.alwaysResolved === true,
      done: isChecklistItemDone(def.key, onboardingState.value.completed),
      skipped: isChecklistItemSkipped(def.key, skipped.value),
      resolved: isChecklistItemResolved(def.key, onboardingState.value.completed, skipped.value),
    }))
  )

  async function reload() {
    const depId = departmentId.value
    const profId = profileId.value
    if (!canUseOnboarding.value || !depId || !profId) {
      onboardingState.value = createDefaultOnboardingState()
      backendDone.value = null
      return
    }

    isLoading.value = true
    try {
      const status = await getDepartmentOnboardingStatus(depId)
      backendDone.value = status.doneAll
    } catch {
      backendDone.value = null
    }

    try {
      onboardingState.value = await refreshOnboardingCompletionStatus(profId, depId)
    } catch {
      onboardingState.value = readOnboardingState(profId, depId)
    } finally {
      isLoading.value = false
    }
  }

  function markItemSkipped(key: OnboardingChecklistItemDef['key']) {
    const profId = profileId.value
    const depId = departmentId.value
    if (!profId || !depId) return
    onboardingState.value = skipChecklistItem(profId, depId, key)
  }

  watch([departmentId, profileId, canUseOnboarding], reload, { immediate: true })

  return {
    departmentId,
    profileId,
    canUseOnboarding,
    isLoading,
    backendDone,
    onboardingState,
    isFullyDone,
    doneCount: resolvedCount,
    openCount,
    progressPercent,
    checklistItems,
    reload,
    markItemSkipped,
    totalItems: ONBOARDING_CHECKLIST_ITEMS.length,
  }
}

export type OnboardingChecklistRow = OnboardingChecklistItemDef & {
  alwaysResolved: boolean
  done: boolean
  skipped: boolean
  resolved: boolean
}
