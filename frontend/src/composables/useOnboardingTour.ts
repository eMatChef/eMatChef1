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
  /** Target liegt in Dialog/Overlay → Spotlight darf höher sein */
  inOverlay?: boolean
  /** Haupt-Sidebar oder Settings-Subnav (Hover-Expand) */
  inSidebar?: boolean
}

const targetRect = ref<OnboardingTargetRect | null>(null)
let targetObserver: ResizeObserver | null = null
let pickerDomObserver: MutationObserver | null = null
let advanceWhenVisibleObserver: MutationObserver | null = null
let observedTarget: Element | null = null
let elevatedRoot: HTMLElement | null = null
let targetClickHandler: ((event: Event) => void) | null = null
let advanceOnClickEl: Element | null = null
let advanceOnClickHandler: ((event: Event) => void) | null = null
let advanceOnClickWaitObserver: MutationObserver | null = null
let rectSyncRaf = 0
let rectSyncStopTimer = 0
let sidebarExpandRoot: Element | null = null
let sidebarExpandHandler: (() => void) | null = null
let scrollParentEls: Element[] = []
let advanceInFlight = false

/** Scrollcontainer des Targets (Dialog-Body, Wizard-Form, …) — window-scroll reicht nicht. */
function findScrollParents(el: Element): Element[] {
  const parents: Element[] = []
  let node: Element | null = el.parentElement
  while (node && node !== document.documentElement) {
    if (node instanceof HTMLElement) {
      const style = window.getComputedStyle(node)
      const oy = style.overflowY
      const ox = style.overflowX
      const canY = oy === 'auto' || oy === 'scroll' || oy === 'overlay'
      const canX = ox === 'auto' || ox === 'scroll' || ox === 'overlay'
      if (canY || canX) {
        parents.push(node)
      }
    }
    node = node.parentElement
  }
  return parents
}

function readTargetRect(el: Element): OnboardingTargetRect {
  const r = el.getBoundingClientRect()
  const inOverlay = !!el.closest(
    '.v-overlay, .v-dialog, .v-overlay__content, .material-wizard-overlay, .material-wizard-modal, .modal-overlay, .modal-dialog'
  )
  const inSidebar = !!el.closest(
    '.v-navigation-drawer, .emc-sidebar-drawer, .settings-subnav-rail'
  )
  return {
    top: r.top,
    left: r.left,
    right: r.right,
    bottom: r.bottom,
    width: r.width,
    height: r.height,
    inOverlay,
    inSidebar,
  }
}

/** Offene Datums-/Zeit-Picker, Select-/Autocomplete- und Material-Lookup-Menüs (Teleport) in den Spotlight. */
const PICKER_OVERLAY_SELECTOR =
  [
    '.activity-date-picker-menu',
    '.activity-date-picker-bottom-sheet__content',
    '.v-time-picker',
    '.v-overlay--active .v-picker',
    '.onboarding-tour-menu-union',
    '.v-overlay--active .v-autocomplete__content',
    '.v-overlay--active .v-select__content',
    '.v-overlay--active .v-combobox__content',
    /* VSelect/VMenu: Listen-Inhalt im Teleport */
    '.v-overlay.v-menu > .v-overlay__content',
    '.v-overlay--active.v-menu > .v-overlay__content',
    /* Material-Suche (Teleport an body) */
    '.material-lookup-dropdown',
    '.material-lookup-dropdown--teleported',
    /* Eventstandort-Autocomplete (Teleport an body) */
    '.activity-address-autocomplete-dropdown--teleported',
    /* Adress-Suche im AddressModal */
    '.address-search-dropdown',
  ].join(', ')

/** Ziele innerhalb/neben dem Spotlight-Target («+», Pin setzen, Dialog-Actions). */
const RELATED_TARGET_SELECTOR = [
  '[data-onboarding="activity-venue-add"]',
  '[data-onboarding="activity-venue-set-pin"]',
  '[data-onboarding="activity-venue-delivery-actions"]',
  '[data-onboarding="activity-venue-delivery-submit"]',
  '.address-modal-actions',
  '.e-dialog__actions',
  '.v-card-actions',
].join(', ')

function unionRectWithOpenPickers(
  base: OnboardingTargetRect,
  targetEl?: Element | null,
): OnboardingTargetRect {
  if (typeof document === 'undefined') return base

  let { top, left, right, bottom } = base
  let expanded = false
  let inOverlay = !!base.inOverlay

  const expandWith = (node: Element) => {
    if (!(node instanceof HTMLElement)) return
    if (!isTargetPresent(node)) return
    const r = node.getBoundingClientRect()
    if (r.width <= 0 || r.height <= 0) return
    top = Math.min(top, r.top)
    left = Math.min(left, r.left)
    right = Math.max(right, r.right)
    bottom = Math.max(bottom, r.bottom)
    expanded = true
    if (
      node.closest(
        '.v-overlay, .v-dialog, .v-overlay__content, .material-wizard-overlay, .material-wizard-modal, .modal-overlay, .modal-dialog',
      )
    ) {
      inOverlay = true
    }
  }

  document.querySelectorAll(PICKER_OVERLAY_SELECTOR).forEach(expandWith)

  if (targetEl) {
    // Nur Elemente innerhalb des Targets (oder des Adress-Wraps selbst) — keine Geschwister im Formular.
    targetEl.querySelectorAll(RELATED_TARGET_SELECTOR).forEach(expandWith)
    if (
      targetEl.matches('.activity-external-address-wrap, [data-onboarding="activity-camp-venue"]') ||
      targetEl.closest('[data-onboarding="activity-camp-venue"]')
    ) {
      const venueRoot =
        targetEl.closest('[data-onboarding="activity-camp-venue"]') ??
        targetEl.closest('.activity-external-address-wrap') ??
        targetEl
      venueRoot.querySelectorAll(RELATED_TARGET_SELECTOR).forEach(expandWith)
    }
    if (
      targetEl.matches('[data-onboarding="activity-venue-create"]') ||
      targetEl.closest('[data-onboarding="activity-venue-create"]')
    ) {
      const createRoot =
        targetEl.closest('[data-onboarding="activity-venue-create"]') ?? targetEl
      createRoot.querySelectorAll(RELATED_TARGET_SELECTOR).forEach(expandWith)
    }
    if (
      targetEl.matches('[data-onboarding="activity-venue-delivery-modal"]') ||
      targetEl.closest('[data-onboarding="activity-venue-delivery-modal"]')
    ) {
      const modalRoot =
        targetEl.closest('[data-onboarding="activity-venue-delivery-modal"]') ?? targetEl
      modalRoot.querySelectorAll(RELATED_TARGET_SELECTOR).forEach(expandWith)
    }
  }

  if (!expanded) return base
  return {
    top,
    left,
    right,
    bottom,
    width: right - left,
    height: bottom - top,
    inOverlay,
    inSidebar: base.inSidebar,
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
  if (
    el.closest(
      '.v-overlay, .v-dialog, .v-menu, .profile-modal, .material-wizard-overlay, .material-wizard-modal, .modal-overlay, .modal-dialog'
    )
  ) {
    return null
  }
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

function clearAdvanceHooks() {
  if (advanceOnClickEl && advanceOnClickHandler) {
    advanceOnClickEl.removeEventListener('click', advanceOnClickHandler)
  }
  advanceOnClickEl = null
  advanceOnClickHandler = null
  advanceOnClickWaitObserver?.disconnect()
  advanceOnClickWaitObserver = null
  advanceWhenVisibleObserver?.disconnect()
  advanceWhenVisibleObserver = null
  advanceInFlight = false
}

function clearTargetInteraction() {
  clearAdvanceHooks()
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

function clearScrollParentListeners() {
  for (const parent of scrollParentEls) {
    parent.removeEventListener('scroll', onScrollOrResize)
  }
  scrollParentEls = []
}

function bindScrollParentListeners(el: Element) {
  clearScrollParentListeners()
  scrollParentEls = findScrollParents(el)
  for (const parent of scrollParentEls) {
    parent.addEventListener('scroll', onScrollOrResize, { passive: true })
  }
}

function clearTargetObserver() {
  stopRectSync()
  clearScrollParentListeners()
  targetObserver?.disconnect()
  targetObserver = null
  pickerDomObserver?.disconnect()
  pickerDomObserver = null
  if (sidebarExpandRoot && sidebarExpandHandler) {
    sidebarExpandRoot.removeEventListener('mouseenter', sidebarExpandHandler)
    sidebarExpandRoot.removeEventListener('mouseleave', sidebarExpandHandler)
    sidebarExpandRoot.removeEventListener('transitionend', sidebarExpandHandler)
  }
  sidebarExpandRoot = null
  sidebarExpandHandler = null
  observedTarget = null
  targetRect.value = null
}

function updateTargetRect(el: Element | null) {
  if (!el) {
    targetRect.value = null
    return
  }
  const next = unionRectWithOpenPickers(readTargetRect(el), el)
  const prev = targetRect.value
  // Subpixel-/Layout-Jitter ignorieren — sonst «springt» der Spotlight bei wachsenden Blöcken.
  // Breite/Höhe: kleinere Schwelle, damit Sidebar-Hover-Expand flüssig mitgeht.
  const posEps = 1.5
  const sizeEps = next.inSidebar ? 0.5 : 1.5
  if (
    prev &&
    Math.abs(prev.top - next.top) < posEps &&
    Math.abs(prev.left - next.left) < posEps &&
    Math.abs(prev.width - next.width) < sizeEps &&
    Math.abs(prev.height - next.height) < sizeEps
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
    // Auch Elemente unterhalb des Viewports akzeptieren — danach scrollt syncTarget.
    if (el && isTargetPresent(el)) return el
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
    if (existing && isTargetPresent(existing)) {
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
      if (el && isTargetPresent(el)) finish(el)
    }

    const observer = new MutationObserver(check)
    observer.observe(document.body, { childList: true, subtree: true, attributes: true })
    const timer = window.setTimeout(() => finish(null), timeoutMs)
    check()
  })
}

/** Im DOM vorhanden und messbar (auch ausserhalb des Viewports). */
function isTargetPresent(el: Element): boolean {
  const htmlEl = el as HTMLElement
  const rect = htmlEl.getBoundingClientRect()
  if (rect.width <= 0 || rect.height <= 0) return false
  const style = window.getComputedStyle(htmlEl)
  if (style.display === 'none' || style.visibility === 'hidden') return false
  return true
}

/** Nach Scroll: Spotlicht braucht sichtbares Ziel im Viewport. */
function isTargetVisible(el: Element): boolean {
  if (!isTargetPresent(el)) return false
  const htmlEl = el as HTMLElement
  const rect = htmlEl.getBoundingClientRect()
  const vh = typeof window !== 'undefined' ? window.innerHeight : 800
  const vw = typeof window !== 'undefined' ? window.innerWidth : 1200
  if (rect.bottom < 56 || rect.top > vh - 40 || rect.right < 8 || rect.left > vw - 8) return false
  return true
}

/** Ziel in den sichtbaren Bereich scrollen (Wizard-Formular oder Viewport). */
async function scrollTargetIntoView(el: Element, preferStart = false) {
  const htmlEl = el as HTMLElement

  // Material-Wizard: im Form-Scrollcontainer zentrieren — nicht window.scrollIntoView
  // (sonst wandert das Ziel unter den App-Header → Spotlight auf «HardScout» o.ä.).
  const wizardForm = htmlEl.closest('.material-wizard-form') as HTMLElement | null
  if (wizardForm) {
    const parentRect = wizardForm.getBoundingClientRect()
    const elRect = htmlEl.getBoundingClientRect()
    const offset = elRect.top - parentRect.top + wizardForm.scrollTop
    const targetScroll = Math.max(0, offset - Math.min(48, parentRect.height * 0.12))
    wizardForm.scrollTo({ top: targetScroll, behavior: 'auto' })
    await new Promise((resolve) => setTimeout(resolve, 80))
    return
  }

  const rect = htmlEl.getBoundingClientRect()
  const vh = window.innerHeight
  const topMargin = 72
  const bottomMargin = 96
  const fullyVisible = rect.top >= topMargin && rect.bottom <= vh - bottomMargin
  if (fullyVisible && !preferStart) return

  // Unterer Viewport / explizit start: nach oben holen (Tour-Karten brauchen Platz darunter)
  const inLowerHalf = rect.top > vh * 0.4
  const tall = rect.height > vh * 0.45
  const useStart = preferStart || inLowerHalf || tall

  if (!useStart) {
    const partiallyVisible = rect.bottom > topMargin && rect.top < vh - bottomMargin
    if (partiallyVisible) return
  }

  htmlEl.scrollIntoView({
    // Instant: nach smooth-Scroll war das Rect oft noch in Bewegung → Spotlight sprang.
    behavior: 'auto',
    block: useStart ? 'start' : 'nearest',
    inline: 'nearest',
  })
  await new Promise((resolve) => setTimeout(resolve, 80))
}

function observeTarget(el: Element) {
  if (observedTarget === el) {
    updateTargetRect(el)
    bindScrollParentListeners(el)
    startRectSync(el)
    return
  }
  clearTargetObserver()
  observedTarget = el
  updateTargetRect(el)
  bindScrollParentListeners(el)
  targetObserver = new ResizeObserver(() => {
    updateTargetRect(el)
    bindScrollParentListeners(el)
    startRectSync(el, 450)
  })
  targetObserver.observe(el)
  // Sidebar / Settings-Subnav: beim Hover-Expand Spotlight mitziehen
  const expandRoot = el.closest(
    '.v-navigation-drawer, .emc-sidebar-drawer, .settings-subnav-rail'
  )
  if (expandRoot) {
    targetObserver.observe(expandRoot)
    sidebarExpandRoot = expandRoot
    sidebarExpandHandler = () => {
      updateTargetRect(el)
      startRectSync(el, 550)
    }
    expandRoot.addEventListener('mouseenter', sidebarExpandHandler)
    expandRoot.addEventListener('mouseleave', sidebarExpandHandler)
    expandRoot.addEventListener('transitionend', sidebarExpandHandler)
  }
  // Kalender/Uhr öffnen sich als Teleport — Spotlight mitziehen
  pickerDomObserver = new MutationObserver(() => {
    if (observedTarget) {
      updateTargetRect(observedTarget)
      bindScrollParentListeners(observedTarget)
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

/** Nur das Overlay darf Target-Sync + Cleanup besitzen — sonst killt TourList-Unmount den Spotlight. */
let hubRedirectInFlight = false

export function useOnboardingTour(options?: { bindTargetSync?: boolean }) {
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

    // Tab/Panel zuerst öffnen, bevor auf den (dann erst sichtbaren) Inhalt gewartet wird
    if (step.clickOnEnter && step.clickOnEnter !== step.target) {
      const openEl = document.querySelector(step.clickOnEnter)
      if (openEl instanceof HTMLElement && isTargetPresent(openEl)) {
        openEl.click()
        await nextTick()
        await new Promise((resolve) => setTimeout(resolve, 80))
      }
    }

    let el = await waitForTarget(step.target, maxAttempts)
    // waitFor = Dialog/Wizard-Inhalt: ohne Target nicht ewig warten → Touren-Hub
    // Optional-Touren (browseComplete): weiterklicken ohne Pflicht-UI erlauben
    if (!el && mode === 'waitFor') {
      if (activeStep.value?.id === stepId && activeTourId.value) {
        if (activeTour.value?.browseComplete) {
          targetRect.value = null
          // Modal kann trotzdem schon offen sein (z. B. nach «+» ohne Spotlight-Ziel)
          bindAdvanceHooks(step, stepId, onTargetClick)
          return
        }
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

    await scrollTargetIntoView(el, step.scroll === 'start')
    if (activeStep.value?.id !== stepId) return

    // Nach Scroll nochmals prüfen / ggf. erneut scrollen (Accordion unter dem Fold)
    if (!isTargetVisible(el)) {
      await scrollTargetIntoView(el, true)
      await new Promise((resolve) => setTimeout(resolve, 100))
    }
    if (activeStep.value?.id !== stepId) return
    if (!isTargetPresent(el)) return

    // Hover-/Opacity-Reveals zuerst aktivieren, dann messen (sonst zu kleines Spotlight)
    el.classList.add(TARGET_ACTIVE_CLASS)
    await nextTick()
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

    if (step.clickOnEnter && step.clickOnEnter === step.target) {
      if (el instanceof HTMLElement && isTargetPresent(el)) {
        el.click()
        await nextTick()
        updateTargetRect(el)
      }
    }

    if (mode === 'click' && onTargetClick) {
      targetClickHandler = () => {
        onTargetClick()
      }
      el.addEventListener('click', targetClickHandler, { once: true })
    }

    bindAdvanceHooks(step, stepId, onTargetClick)
  }

  function bindAdvanceHooks(
    step: NonNullable<typeof activeStep.value>,
    stepId: string,
    onTargetClick?: () => void,
  ) {
    clearAdvanceHooks()

    const runAdvance = () => {
      if (advanceInFlight) return
      if (activeStep.value?.id !== stepId || !activeTourId.value) return
      advanceInFlight = true
      const toId = step.advanceToStepId
      if (toId && activeTour.value) {
        const idx = getOnboardingTourStepIndex(activeTour.value, toId)
        if (idx >= 0) {
          void navigateToStep(idx)
          return
        }
      }
      if (onTargetClick) {
        onTargetClick()
        return
      }
      void next()
    }

    if (step.advanceOnClick) {
      const selector = step.advanceOnClick
      const attach = () => {
        if (advanceOnClickEl && advanceOnClickHandler) return
        const clickEl = document.querySelector(selector)
        if (!clickEl) return
        advanceOnClickEl = clickEl
        advanceOnClickHandler = () => {
          void (async () => {
            const waitGone = step.advanceOnClickWaitGone
            if (waitGone) {
              for (let i = 0; i < 50; i += 1) {
                const still = document.querySelector(waitGone)
                if (!still || !isTargetPresent(still)) break
                await new Promise((resolve) => setTimeout(resolve, 100))
              }
            } else {
              await new Promise((resolve) => setTimeout(resolve, 50))
            }
            for (const dismissSel of step.advanceOnClickThenDismiss ?? []) {
              const el = document.querySelector(dismissSel)
              if (el instanceof HTMLElement && isTargetPresent(el)) {
                el.click()
                await new Promise((resolve) => setTimeout(resolve, 80))
              }
            }
            runAdvance()
          })()
        }
        clickEl.addEventListener('click', advanceOnClickHandler)
        advanceOnClickWaitObserver?.disconnect()
        advanceOnClickWaitObserver = null
      }
      attach()
      // Button kann erst nach Accordion/Pin erscheinen
      if (!advanceOnClickEl) {
        advanceOnClickWaitObserver = new MutationObserver(() => {
          attach()
        })
        advanceOnClickWaitObserver.observe(document.body, {
          childList: true,
          subtree: true,
          attributes: true,
        })
      }
    }

    if (step.advanceWhenVisible) {
      const selector = step.advanceWhenVisible
      const check = () => {
        if (activeStep.value?.id !== stepId || !activeTourId.value) return
        const visible = document.querySelector(selector)
        if (visible && isTargetPresent(visible)) runAdvance()
      }
      advanceWhenVisibleObserver = new MutationObserver(check)
      advanceWhenVisibleObserver.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
      })
      check()
    }
  }

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

    const departmentId = route.params.departmentId
    const query = {
      ...route.query,
      ...buildTourQuery(step.id),
    }

    // Nach Einreichen: auf Aktivitäts-Detail bleiben (nicht zurück zur Liste)
    const activityCreateDetailSteps = step.id === '8' || step.id === '9'
    if (
      activeTour.value.id === 'activity-create' &&
      activityCreateDetailSteps &&
      typeof route.params.activityId === 'string' &&
      route.params.activityId
    ) {
      const detailQuery: Record<string, string | string[] | null | undefined> = { ...query }
      if (step.id === '8') detailQuery.tab = 'overview'
      if (step.id === '9') detailQuery.tab = 'material'
      await router.replace({
        name: route.name ?? 'ActivityDetail',
        params: { ...route.params },
        query: detailQuery,
      })
      return
    }
    if (activeTour.value.id === 'activity-create' && activityCreateDetailSteps) {
      // Submit noch unterwegs → nur Query setzen; ActivitiesView navigiert zum Detail
      await router.replace({ query })
      return
    }

    // Camp-Tour: nach Entwurf speichern auf Detail bleiben + Tabs öffnen
    const campDetailSteps = ['20', '21', '22', '23', '24', '25', '26']
    if (
      activeTour.value.id === 'activity-camp-create' &&
      campDetailSteps.includes(step.id) &&
      typeof route.params.activityId === 'string' &&
      route.params.activityId
    ) {
      const detailQuery: Record<string, string | string[] | null | undefined> = { ...query }
      if (step.id === '20' || step.id === '21' || step.id === '25' || step.id === '26') {
        detailQuery.tab = 'overview'
      } else if (step.id === '22') {
        detailQuery.tab = 'material'
      } else if (step.id === '23') {
        detailQuery.tab = 'vehicles'
      } else if (step.id === '24') {
        detailQuery.tab = 'js'
      }
      await router.replace({
        name: route.name ?? 'ActivityDetail',
        params: { ...route.params },
        query: detailQuery,
      })
      return
    }
    if (activeTour.value.id === 'activity-camp-create' && campDetailSteps.includes(step.id)) {
      await router.replace({ query })
      return
    }

    // Freigabe-Tour: Detail-Schritte auf geöffnetem Lager/Event halten
    const approveDetailSteps = ['3', '4', '5']
    if (
      activeTour.value.id === 'activity-approve' &&
      approveDetailSteps.includes(step.id) &&
      typeof route.params.activityId === 'string' &&
      route.params.activityId
    ) {
      const detailQuery: Record<string, string | string[] | null | undefined> = { ...query }
      if (step.id === '4') detailQuery.tab = 'material'
      else detailQuery.tab = 'overview'
      await router.replace({
        name: route.name ?? 'ActivityDetail',
        params: { ...route.params },
        query: detailQuery,
      })
      return
    }
    if (activeTour.value.id === 'activity-approve' && approveDetailSteps.includes(step.id)) {
      await router.replace({ query })
      return
    }

    // Pack-Tour: Detail-Schritte auf geöffnetem Lager/Aktivität halten
    const packDetailSteps = ['3', '4', '5', '6', '7', '8', '9']
    if (
      activeTour.value.id === 'issue-return' &&
      packDetailSteps.includes(step.id) &&
      typeof route.params.activityId === 'string' &&
      route.params.activityId
    ) {
      const detailQuery: Record<string, string | string[] | null | undefined> = { ...query }
      if (step.id === '4') detailQuery.tab = 'material'
      else if (step.id === '6' || step.id === '7' || step.id === '8') detailQuery.tab = 'packs'
      else if (step.id === '9') detailQuery.tab = 'packs'
      else detailQuery.tab = 'overview'
      await router.replace({
        name: route.name ?? 'ActivityDetail',
        params: { ...route.params },
        query: detailQuery,
      })
      return
    }
    if (activeTour.value.id === 'issue-return' && packDetailSteps.includes(step.id)) {
      await router.replace({ query })
      return
    }

    const targetRouteName = getRouteNameForTourStep(activeTour.value, stepIndex)

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

  async function runDismissOnNext(step: NonNullable<typeof activeStep.value>): Promise<boolean> {
    const selectors = step.dismissOnNext
    if (!selectors?.length || typeof document === 'undefined') return true

    const clickIfPresent = (selector: string) => {
      const el = document.querySelector(selector)
      if (!(el instanceof HTMLElement) || !isTargetPresent(el)) return false
      // Disabled Buttons (z. B. Wizard-Weiter) nicht klicken
      if (el instanceof HTMLButtonElement && el.disabled) return false
      if (el.getAttribute('aria-disabled') === 'true') return false
      if (el.classList.contains('v-btn--disabled')) return false
      el.click()
      return true
    }

    for (const selector of selectors) {
      clickIfPresent(selector)
      await new Promise((resolve) => setTimeout(resolve, 80))
    }

    // Bestätigung «Verwerfen» kann erst nach Abbrechen erscheinen
    if (selectors.some((s) => s.includes('activity-venue-delivery'))) {
      clickIfPresent('[data-onboarding="activity-venue-delivery-discard"]')
      await new Promise((resolve) => setTimeout(resolve, 60))
      clickIfPresent('[data-onboarding="activity-venue-create-close"]')

      for (let i = 0; i < 30; i += 1) {
        const deliveryOpen = !!document.querySelector('[data-onboarding="activity-venue-delivery-modal"]')
        const venueOpen = !!document.querySelector('[data-onboarding="activity-venue-create"]')
        if (!deliveryOpen && !venueOpen) break
        if (deliveryOpen) {
          clickIfPresent('[data-onboarding="activity-venue-delivery-discard"]')
          clickIfPresent('[data-onboarding="activity-venue-delivery-cancel"]')
        }
        if (venueOpen) clickIfPresent('[data-onboarding="activity-venue-create-close"]')
        await new Promise((resolve) => setTimeout(resolve, 80))
      }
    }

    const waitSel = step.waitVisibleOnNext
    if (waitSel) {
      for (let i = 0; i < 40; i += 1) {
        const el = document.querySelector(waitSel)
        if (el && isTargetPresent(el)) return true
        await new Promise((resolve) => setTimeout(resolve, 100))
      }
      return false
    }
    return true
  }

  async function next() {
    if (!activeTour.value || !activeStep.value) return
    if (isLastStep.value) {
      finish('stay')
      return
    }
    const dismissed = await runDismissOnNext(activeStep.value)
    if (!dismissed) return
    await navigateToStep(activeStepIndex.value + 1)
  }

  /** Abbrechen ohne «erledigt» — nur der letzte Schritt (Fertig) setzt den Fortschritt. */
  async function skip() {
    abortTour()
  }

  function finish(action: 'stay' | 'helpTours' = 'stay') {
    const profileId = authStore.profileId
    const departmentId = route.params.departmentId
    if (profileId && typeof departmentId === 'string' && activeTourId.value) {
      markOnboardingTourCompleted(profileId, departmentId, activeTourId.value as OnboardingTourId)
    }
    stopObservingTarget()
    if (action === 'helpTours' && typeof departmentId === 'string') {
      void router.replace({
        name: 'HelpTours',
        params: { departmentId },
      })
      return
    }
    void router.replace({ query: clearTourQuery() })
  }

  if (options?.bindTargetSync) {
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
