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

export type OnboardingTargetRect = {
  top: number
  left: number
  right: number
  bottom: number
  width: number
  height: number
}

const targetRect = ref<OnboardingTargetRect | null>(null)
let targetObserver: ResizeObserver | null = null
let pickerDomObserver: MutationObserver | null = null
let observedTarget: Element | null = null
let elevatedRoot: HTMLElement | null = null
let targetClickHandler: ((event: Event) => void) | null = null
let rectSyncRaf = 0
let rectSyncStopTimer = 0

function readTargetRect(el: Element): OnboardingTargetRect {
  const r = el.getBoundingClientRect()
  return {
    top: r.top,
    left: r.left,
    right: r.right,
    bottom: r.bottom,
    width: r.width,
    height: r.height,
  }
}

/** Offene Datums-/Zeit-Picker (Teleport) in den Spotlight einbeziehen. */
const PICKER_OVERLAY_SELECTOR =
  '.activity-date-picker-menu, .activity-date-picker-bottom-sheet__content, .v-time-picker, .v-overlay--active .v-picker'

function unionRectWithOpenPickers(base: OnboardingTargetRect): OnboardingTargetRect {
  if (typeof document === 'undefined') return base
  const pickers = document.querySelectorAll(PICKER_OVERLAY_SELECTOR)
  if (pickers.length === 0) return base

  let { top, left, right, bottom } = base
  let expanded = false
  pickers.forEach((node) => {
    if (!(node instanceof HTMLElement)) return
    if (!isTargetVisible(node)) return
    const r = node.getBoundingClientRect()
    if (r.width <= 0 || r.height <= 0) return
    top = Math.min(top, r.top)
    left = Math.min(left, r.left)
    right = Math.max(right, r.right)
    bottom = Math.max(bottom, r.bottom)
    expanded = true
  })
  if (!expanded) return base
  return {
    top,
    left,
    right,
    bottom,
    width: right - left,
    height: bottom - top,
  }
}

function findElevateRoot(el: Element): HTMLElement | null {
  // Sidebar-Drawer nicht elevaten — sonst überdeckt die transformierte Drawer-Fläche
  // den Spotlight-Ring und schneidet ihn am rechten Rail-Rand ab.
  const userDropdown = el.closest('.user-dropdown')
  if (userDropdown instanceof HTMLElement) return userDropdown
  const userMenu = el.closest('.user-menu-wrapper')
  if (userMenu instanceof HTMLElement) return userMenu
  if (el.closest('.v-navigation-drawer')) return null
  // Dialog/Overlay: nicht elevaten. .v-overlay hat eigenen Stacking-Context unter dem
  // Dimmer; Loch + Klicks reichen. Elevaten nur vom Content triggert oft Layout-Races
  // (Spotlight versetzt zur Dialog-Open-Animation).
  if (el.closest('.v-overlay, .v-dialog, .v-menu, .profile-modal')) return null
  const header = el.closest('.v-app-bar, .top-header, header.top-header')
  if (header instanceof HTMLElement) return header
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

function stopRectSync() {
  if (rectSyncRaf) {
    cancelAnimationFrame(rectSyncRaf)
    rectSyncRaf = 0
  }
  if (rectSyncStopTimer) {
    window.clearTimeout(rectSyncStopTimer)
    rectSyncStopTimer = 0
  }
}

/** Während Dialog-/Scroll-Animationen Rect nachziehen (sonst Spotlight versetzt). */
function startRectSync(el: Element, durationMs = 450) {
  stopRectSync()
  const started = performance.now()
  const tick = (now: number) => {
    if (observedTarget !== el) return
    updateTargetRect(el)
    if (now - started < durationMs) {
      rectSyncRaf = requestAnimationFrame(tick)
    } else {
      rectSyncRaf = 0
    }
  }
  rectSyncRaf = requestAnimationFrame(tick)
  rectSyncStopTimer = window.setTimeout(() => stopRectSync(), durationMs + 50)
}

function clearTargetObserver() {
  stopRectSync()
  targetObserver?.disconnect()
  targetObserver = null
  pickerDomObserver?.disconnect()
  pickerDomObserver = null
  observedTarget = null
  targetRect.value = null
}

function updateTargetRect(el: Element | null) {
  if (!el) {
    targetRect.value = null
    return
  }
  const next = unionRectWithOpenPickers(readTargetRect(el))
  const prev = targetRect.value
  // Subpixel-/Layout-Jitter ignorieren — sonst «springt» der Spotlight bei wachsenden Blöcken.
  if (
    prev &&
    Math.abs(prev.top - next.top) < 1.5 &&
    Math.abs(prev.left - next.left) < 1.5 &&
    Math.abs(prev.width - next.width) < 1.5 &&
    Math.abs(prev.height - next.height) < 1.5
  ) {
    return
  }
  targetRect.value = next
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

/** Ziel in den sichtbaren Bereich scrollen (z. B. Profil-Modal). */
async function scrollTargetIntoView(el: Element) {
  const htmlEl = el as HTMLElement
  const rect = htmlEl.getBoundingClientRect()
  const vh = window.innerHeight
  const topMargin = 72
  const bottomMargin = 96
  const fullyVisible = rect.top >= topMargin && rect.bottom <= vh - bottomMargin
  if (fullyVisible) return

  // Bereits teilweise sichtbar und nicht riesig: kein Scroll — vermeidet Spotlight-Springen.
  const partiallyVisible = rect.bottom > topMargin && rect.top < vh - bottomMargin
  const tall = rect.height > vh * 0.45
  if (partiallyVisible && !tall) return

  htmlEl.scrollIntoView({
    // Instant: nach smooth-Scroll war das Rect oft noch in Bewegung → Spotlight sprang.
    behavior: 'auto',
    block: tall ? 'start' : 'nearest',
    inline: 'nearest',
  })
  await new Promise((resolve) => setTimeout(resolve, 80))
}

function observeTarget(el: Element) {
  if (observedTarget === el) {
    updateTargetRect(el)
    startRectSync(el)
    return
  }
  clearTargetObserver()
  observedTarget = el
  updateTargetRect(el)
  targetObserver = new ResizeObserver(() => updateTargetRect(el))
  targetObserver.observe(el)
  // Kalender/Uhr öffnen sich als Teleport — Spotlight mitziehen
  pickerDomObserver = new MutationObserver(() => {
    if (observedTarget) {
      updateTargetRect(observedTarget)
      startRectSync(observedTarget, 600)
    }
  })
  pickerDomObserver.observe(document.body, { childList: true, subtree: true, attributes: true })
  window.addEventListener('scroll', onScrollOrResize, true)
  window.addEventListener('resize', onScrollOrResize)
  startRectSync(el)
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

function isDocumentReload(): boolean {
  const nav = performance.getEntriesByType?.('navigation')?.[0] as
    | PerformanceNavigationTiming
    | undefined
  if (nav?.type === 'reload') return true
  // Legacy PerformanceNavigation (Safari/ältere Browser)
  const legacy = (performance as Performance & { navigation?: { type?: number } }).navigation
  return legacy?.type === 1
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

  let hubRedirectInFlight = false

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

  /**
   * Hard-Reload / fehlendes Wizard-Target: zurück zum Touren-Hub.
   * Wizard-Zustand lässt sich nach Reload nicht zuverlässig wiederherstellen.
   */
  async function returnToTourHub() {
    if (hubRedirectInFlight) return
    hubRedirectInFlight = true
    stopObservingTarget()
    const departmentId = route.params.departmentId
    try {
      if (typeof departmentId === 'string' && departmentId) {
        await router.replace({
          name: 'HelpTours',
          params: { departmentId },
        })
      } else {
        await router.replace({ query: clearTourQuery() })
      }
    } finally {
      hubRedirectInFlight = false
    }
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
    // waitFor = Dialog/Wizard-Inhalt: ohne Target nicht ewig warten → Touren-Hub
    if (!el && mode === 'waitFor') {
      if (activeStep.value?.id === stepId && activeTourId.value) {
        await returnToTourHub()
      }
      return
    }
    if (!el && mode === 'click') {
      el = await waitForTargetPersistent(
        step.target,
        () => activeStep.value?.id === stepId && activeTourId.value !== null
      )
    }
    if (!el || activeStep.value?.id !== stepId) return

    await scrollTargetIntoView(el)
    if (activeStep.value?.id !== stepId) return

    // Zwei Frames + kurze Pause: Dialog-Open/Layout erst abwarten, sonst Spotlight versetzt.
    await new Promise<void>((resolve) => {
      requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
    })
    if (activeStep.value?.id !== stepId) return
    updateTargetRect(el)

    observeTarget(el)
    elevateTargetRoot(el)
    updateTargetRect(el)
    startRectSync(el, 1000)
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
      if (hubRedirectInFlight) return
      void syncTarget(() => {
        void next()
      })
    }
  )

  onMounted(() => {
    // Hard-Reload mitten in der Tour: Zustand (Wizard etc.) ist weg → Touren-Hub
    if (activeTourId.value && isDocumentReload()) {
      void returnToTourHub()
      return
    }
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

  /** Abbrechen ohne «erledigt» — nur der letzte Schritt (Fertig) setzt den Fortschritt. */
  async function skip() {
    abortTour()
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
