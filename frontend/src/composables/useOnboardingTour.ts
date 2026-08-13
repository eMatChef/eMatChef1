import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import {
  getOnboardingTour,
  getOnboardingTourStepIndex,
  getRouteNameForTourStep,
  ONBOARDING_TOUR_QUERY,
  ONBOARDING_TOUR_STEP_QUERY,
  type OnboardingTourDef,
  type OnboardingTourId,
  type OnboardingTourStepMode,
} from '@/config/onboardingTours'
import { useAuthStore } from '@/stores/auth'
import { markOnboardingTourCompleted } from '@/utils/onboardingTourProgress'

const TARGET_ACTIVE_CLASS = 'onboarding-tour-target-active'
/** Stacking-Context-Root über den Dimmer heben (Drawer/Header/Dialog). */
const ELEVATE_ROOT_CLASS = 'onboarding-tour-elevate-root'

const targetRect = ref<DOMRect | null>(null)
let targetObserver: ResizeObserver | null = null
let observedTarget: Element | null = null
let elevatedRoot: HTMLElement | null = null
let targetClickHandler: ((event: Event) => void) | null = null

function findElevateRoot(el: Element): HTMLElement | null {
  // Sidebar-Drawer nicht elevaten — sonst überdeckt die transformierte Drawer-Fläche
  // den Spotlight-Ring und schneidet ihn am rechten Rail-Rand ab.
  const userDropdown = el.closest('.user-dropdown')
  if (userDropdown instanceof HTMLElement) return userDropdown
  const userMenu = el.closest('.user-menu-wrapper')
  if (userMenu instanceof HTMLElement) return userMenu
  if (el.closest('.v-navigation-drawer')) return null
  const header = el.closest('.v-app-bar, .top-header, header.top-header')
  if (header instanceof HTMLElement) return header
  const overlay = el.closest('.v-overlay__content, .v-dialog, .v-menu__content, .profile-modal')
  if (overlay instanceof HTMLElement) return overlay
  return el instanceof HTMLElement ? el : (el.parentElement as HTMLElement)
}

function clearElevateRoot() {
  if (elevatedRoot) {
    elevatedRoot.classList.remove(ELEVATE_ROOT_CLASS)
    elevatedRoot = null
  }
}

function elevateTargetRoot(el: Element) {
  clearElevateRoot()
  const root = findElevateRoot(el)
  if (!root) return
  elevatedRoot = root
  elevatedRoot.classList.add(ELEVATE_ROOT_CLASS)
}

function clearTargetInteraction() {
  if (observedTarget) {
    observedTarget.classList.remove(TARGET_ACTIVE_CLASS)
    if (targetClickHandler) {
      observedTarget.removeEventListener('click', targetClickHandler)
      targetClickHandler = null
    }
  }
  clearElevateRoot()
}

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

async function waitForTarget(
  selector: string,
  attempts = 60,
  delayMs = 120
): Promise<Element | null> {
  for (let i = 0; i < attempts; i += 1) {
    const el = document.querySelector(selector)
    if (el && isTargetVisible(el)) return el
    await new Promise((resolve) => setTimeout(resolve, delayMs))
  }
  return null
}

/** Bleibt dran, bis das Ziel im DOM erscheint (z. B. User-Menü öffnet sich). */
function waitForTargetPersistent(
  selector: string,
  isStillCurrent: () => boolean,
  timeoutMs = 120_000
): Promise<Element | null> {
  return new Promise((resolve) => {
    const existing = document.querySelector(selector)
    if (existing && isTargetVisible(existing)) {
      resolve(existing)
      return
    }

    let done = false
    const finish = (el: Element | null) => {
      if (done) return
      done = true
      observer.disconnect()
      window.clearTimeout(timer)
      resolve(el)
    }

    const check = () => {
      if (!isStillCurrent()) {
        finish(null)
        return
      }
      const el = document.querySelector(selector)
      if (el && isTargetVisible(el)) finish(el)
    }

    const observer = new MutationObserver(check)
    observer.observe(document.body, { childList: true, subtree: true, attributes: true })
    const timer = window.setTimeout(() => finish(null), timeoutMs)
    check()
  })
}

function isTargetVisible(el: Element): boolean {
  const htmlEl = el as HTMLElement
  const rect = htmlEl.getBoundingClientRect()
  if (rect.width <= 0 || rect.height <= 0) return false
  const style = window.getComputedStyle(htmlEl)
  return style.display !== 'none' && style.visibility !== 'hidden'
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
  clearTargetInteraction()
  clearTargetObserver()
}

export function useOnboardingTour() {
  const route = useRoute()
  const router = useRouter()
  const authStore = useAuthStore()
  /** Start nur ab md (≥960px). Laufende Tour bricht bei Hochformat/Resize nicht ab. */
  const { mdAndUp } = useDisplay()

  const canStartToursOnViewport = computed(() => mdAndUp.value)
  /** Alias für Tour-Liste (Start-Erlaubnis). */
  const toursSupportedOnViewport = canStartToursOnViewport

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

  const activeStepMode = computed<OnboardingTourStepMode>(() => activeStep.value?.mode ?? 'info')

  const isActive = computed(() => !!activeTour.value && !!activeStep.value)

  const isLastStep = computed(() => {
    if (!activeTour.value) return true
    return activeStepIndex.value >= activeTour.value.steps.length - 1
  })

  const expectsTargetClick = computed(() => activeStepMode.value === 'click')

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

  /** Tour abbrechen ohne als erledigt zu markieren. */
  function abortTour() {
    stopObservingTarget()
    if (!activeTourId.value) return
    void router.replace({ query: clearTourQuery() })
  }

  async function syncTarget(onTargetClick?: () => void) {
    stopObservingTarget()
    const step = activeStep.value
    if (!step?.target) return

    const stepId = step.id
    const mode = step.mode ?? 'info'
    const maxAttempts = mode === 'waitFor' || mode === 'click' ? 80 : 40
    await nextTick()
    let el = await waitForTarget(step.target, maxAttempts)
    if (!el && (mode === 'waitFor' || mode === 'click')) {
      el = await waitForTargetPersistent(
        step.target,
        () => activeStep.value?.id === stepId && activeTourId.value !== null
      )
    }
    if (!el || activeStep.value?.id !== stepId) return

    observeTarget(el)
    elevateTargetRoot(el)
    el.classList.add(TARGET_ACTIVE_CLASS)

    if (mode === 'click' && onTargetClick) {
      targetClickHandler = () => {
        onTargetClick()
      }
      el.addEventListener('click', targetClickHandler, { once: true })
    }
  }

  watch(
    [activeTourId, activeStepId, () => route.name],
    () => {
      void syncTarget(() => {
        void next()
      })
    }
  )

  onMounted(() => {
    void syncTarget(() => {
      void next()
    })
  })

  onUnmounted(() => {
    stopObservingTarget()
  })

  function startTour(tourId: OnboardingTourId, departmentId: string) {
    if (!canStartToursOnViewport.value) return
    const tour = getOnboardingTour(tourId)
    if (!tour) return
    const firstStep = tour.steps[0]
    router.push({
      name: getRouteNameForTourStep(tour, 0),
      params: { departmentId },
      query: {
        [ONBOARDING_TOUR_QUERY]: tour.id,
        [ONBOARDING_TOUR_STEP_QUERY]: firstStep?.id ?? '1',
      },
    })
  }

  async function navigateToStep(stepIndex: number) {
    if (!activeTour.value) return
    const step = activeTour.value.steps[stepIndex]
    if (!step) return

    const targetRouteName = getRouteNameForTourStep(activeTour.value, stepIndex)
    const departmentId = route.params.departmentId
    const query = {
      ...route.query,
      ...buildTourQuery(step.id),
    }

    if (route.name === targetRouteName) {
      await router.replace({ query })
    } else if (typeof departmentId === 'string') {
      await router.push({
        name: targetRouteName,
        params: { departmentId },
        query,
      })
    }
  }

  async function next() {
    if (!activeTour.value || !activeStep.value) return
    if (isLastStep.value) {
      finish()
      return
    }
    await navigateToStep(activeStepIndex.value + 1)
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
    activeStepMode,
    isActive,
    isLastStep,
    expectsTargetClick,
    targetRect,
    toursSupportedOnViewport,
    canStartToursOnViewport,
    startTour,
    next,
    skip,
    finish,
    abortTour,
  }
}
