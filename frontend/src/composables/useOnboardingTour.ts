import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getOnboardingTour,
  getOnboardingTourStepIndex,
  ONBOARDING_TOUR_QUERY,
  ONBOARDING_TOUR_STEP_QUERY,
  type OnboardingTourDef,
  type OnboardingTourId,
} from '@/config/onboardingTours'
import { useAuthStore } from '@/stores/auth'
import { markOnboardingTourCompleted } from '@/utils/onboardingTourProgress'

const targetRect = ref<DOMRect | null>(null)
let targetObserver: ResizeObserver | null = null
let observedTarget: Element | null = null

function clearTargetObserver() {
  targetObserver?.disconnect()
  targetObserver = null
  observedTarget = null
  targetRect.value = null
}

function updateTargetRect(el: Element | null) {
  if (!el) {
    targetRect.value = null
    return
  }
  targetRect.value = el.getBoundingClientRect()
}

async function waitForTarget(selector: string, attempts = 40, delayMs = 100): Promise<Element | null> {
  for (let i = 0; i < attempts; i += 1) {
    const el = document.querySelector(selector)
    if (el) return el
    await new Promise((resolve) => setTimeout(resolve, delayMs))
  }
  return null
}

function observeTarget(el: Element) {
  if (observedTarget === el) return
  clearTargetObserver()
  observedTarget = el
  updateTargetRect(el)
  targetObserver = new ResizeObserver(() => updateTargetRect(el))
  targetObserver.observe(el)
  window.addEventListener('scroll', onScrollOrResize, true)
  window.addEventListener('resize', onScrollOrResize)
}

function onScrollOrResize() {
  if (observedTarget) updateTargetRect(observedTarget)
}

function stopObservingTarget() {
  window.removeEventListener('scroll', onScrollOrResize, true)
  window.removeEventListener('resize', onScrollOrResize)
  clearTargetObserver()
}

export function useOnboardingTour() {
  const route = useRoute()
  const router = useRouter()
  const authStore = useAuthStore()

  const activeTourId = computed(() => {
    const raw = route.query[ONBOARDING_TOUR_QUERY]
    return typeof raw === 'string' && raw ? raw : null
  })

  const activeStepId = computed(() => {
    const raw = route.query[ONBOARDING_TOUR_STEP_QUERY]
    return typeof raw === 'string' && raw ? raw : '1'
  })

  const activeTour = computed<OnboardingTourDef | null>(() => {
    if (!activeTourId.value) return null
    return getOnboardingTour(activeTourId.value) ?? null
  })

  const activeStepIndex = computed(() => {
    if (!activeTour.value) return 0
    return getOnboardingTourStepIndex(activeTour.value, activeStepId.value)
  })

  const activeStep = computed(() => {
    if (!activeTour.value) return null
    return activeTour.value.steps[activeStepIndex.value] ?? null
  })

  const isActive = computed(() => !!activeTour.value && !!activeStep.value)

  const isLastStep = computed(() => {
    if (!activeTour.value) return true
    return activeStepIndex.value >= activeTour.value.steps.length - 1
  })

  async function syncTarget() {
    stopObservingTarget()
    const selector = activeStep.value?.target
    if (!selector) return
    await nextTick()
    const el = await waitForTarget(selector)
    if (el) observeTarget(el)
  }

  watch([activeTourId, activeStepId, () => route.name], () => {
    void syncTarget()
  })

  onMounted(() => {
    void syncTarget()
  })

  onUnmounted(() => {
    stopObservingTarget()
  })

  function buildTourQuery(stepId: string) {
    return {
      [ONBOARDING_TOUR_QUERY]: activeTourId.value,
      [ONBOARDING_TOUR_STEP_QUERY]: stepId,
    }
  }

  function clearTourQuery() {
    const nextQuery = { ...route.query }
    delete nextQuery[ONBOARDING_TOUR_QUERY]
    delete nextQuery[ONBOARDING_TOUR_STEP_QUERY]
    return nextQuery
  }

  function startTour(tourId: OnboardingTourId, departmentId: string) {
    const tour = getOnboardingTour(tourId)
    if (!tour) return
    router.push({
      name: tour.routeName,
      params: { departmentId },
      query: {
        [ONBOARDING_TOUR_QUERY]: tour.id,
        [ONBOARDING_TOUR_STEP_QUERY]: tour.steps[0]?.id ?? '1',
      },
    })
  }

  async function next() {
    if (!activeTour.value || !activeStep.value) return
    if (isLastStep.value) {
      finish()
      return
    }
    const nextStep = activeTour.value.steps[activeStepIndex.value + 1]
    await router.replace({ query: { ...route.query, ...buildTourQuery(nextStep.id) } })
  }

  async function skip() {
    finish()
  }

  function finish() {
    const profileId = authStore.profileId
    const departmentId = route.params.departmentId
    if (profileId && typeof departmentId === 'string' && activeTourId.value) {
      markOnboardingTourCompleted(profileId, departmentId, activeTourId.value as OnboardingTourId)
    }
    stopObservingTarget()
    router.replace({ query: clearTourQuery() })
  }

  return {
    activeTour,
    activeStep,
    activeStepIndex,
    isActive,
    isLastStep,
    targetRect,
    startTour,
    next,
    skip,
    finish,
  }
}
