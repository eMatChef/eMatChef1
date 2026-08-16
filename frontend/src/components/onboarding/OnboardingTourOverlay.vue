<template>
  <Teleport to="body">
    <template v-if="isActive && activeStep">
      <!--
        Vier Dimmer-Paneele lassen ein echtes Loch (kein Element über dem Ziel).
        Ring + Karte liegen darüber; elevate-root zieht Drawer/Header zusätzlich nach vorne.
      -->
      <div
        class="onboarding-tour-dim"
        aria-hidden="true"
      >
        <template v-if="hole">
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--top" :style="hole.top" />
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--left" :style="hole.left" />
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--right" :style="hole.right" />
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--bottom" :style="hole.bottom" />
        </template>
        <div
          v-else
          class="onboarding-tour-dim__pane onboarding-tour-dim__pane--full"
        />
      </div>

      <div class="onboarding-tour-card-layer">
        <!-- Ring in der obersten Layer, sonst schneidet der elevatete Drawer den rechten Rand ab -->
        <div
          v-if="hole"
          class="onboarding-tour-ring"
          :style="ringStyle"
          aria-hidden="true"
        />
        <div class="onboarding-tour__card" :style="cardStyle" role="dialog" aria-modal="true">
          <header class="onboarding-tour__header">
            <p v-if="activeTour" class="onboarding-tour__eyebrow">
              {{ t(activeTour.titleKey) }}
            </p>
            <p v-if="activeTour" class="onboarding-tour__step">
              {{ t('onboarding.tours.stepOf', { current: activeStepIndex + 1, total: activeTour.steps.length }) }}
            </p>
          </header>
          <h2 class="onboarding-tour__title">{{ t(activeStep.titleKey) }}</h2>
          <div class="onboarding-tour__content">
            <p class="onboarding-tour__body">{{ t(activeStep.bodyKey) }}</p>
            <ul v-if="bodyItems.length" class="onboarding-tour__list">
              <li v-for="(item, idx) in bodyItems" :key="idx">{{ item }}</li>
            </ul>
          </div>
          <p v-if="expectsTargetClick" class="onboarding-tour__hint">
            {{ t('onboarding.tours.clickTargetHint') }}
          </p>
          <footer class="onboarding-tour__footer">
            <EButton variant="text" size="small" class="onboarding-tour__skip" @click="skip">
              {{ t('onboarding.tours.skip') }}
            </EButton>
            <div v-if="showCompletionCtas" class="onboarding-tour__cta-group">
              <EButton
                v-for="cta in activeTour?.completionCtas"
                :key="cta.labelKey"
                :variant="cta.action === 'helpTours' ? 'primary' : 'secondary'"
                size="small"
                @click="finish(cta.action)"
              >
                {{ t(cta.labelKey) }}
              </EButton>
            </div>
            <EButton
              v-else-if="!expectsTargetClick"
              variant="primary"
              size="small"
              class="onboarding-tour__next"
              @click="next"
            >
              {{ isLastStep ? t('onboarding.tours.finish') : t('onboarding.tours.next') }}
            </EButton>
          </footer>
        </div>
      </div>
    </template>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { useOnboardingTour } from '@/composables/useOnboardingTour'

const { t, tm, te } = useI18n()
const {
  activeTour,
  activeStep,
  activeStepIndex,
  isActive,
  isLastStep,
  expectsTargetClick,
  targetRect,
  next,
  skip,
  finish,
} = useOnboardingTour({ bindTargetSync: true })

const bodyItems = computed((): string[] => {
  const key = activeStep.value?.bodyItemsKey
  if (!key || !te(key)) return []
  const raw = tm(key)
  if (!Array.isArray(raw)) return []
  return raw.map((item) => String(item)).filter(Boolean)
})

watch(
  isActive,
  (active) => {
    if (typeof document === 'undefined') return
    document.body.classList.toggle('onboarding-tour-active', active)
  },
  { immediate: true },
)

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.classList.remove('onboarding-tour-active')
  }
})

const showCompletionCtas = computed(
  () =>
    isLastStep.value &&
    !expectsTargetClick.value &&
    (activeTour.value?.completionCtas?.length ?? 0) > 0,
)

const CARD_WIDTH = 360
const CARD_GAP = 20
const CARD_EST_HEIGHT = 240
/** Unter App-Header / Drawer — Karte und Spotlight nicht in die Kopfzeile schieben */
const HEADER_SAFE_TOP = 72
/** Fallback für Karten-Position neben Rail (wenn noch nicht expandiert) */
const SIDEBAR_RIGHT_EDGE = 64
/** Innenabstand Loch/Ring — Border (2) + Glow (3) müssen hineinpassen */
const HOLE_PAD = 10
const VIEWPORT_INSET = 4
/** Sidebar/Subnav: enger Pad am Element — Breite folgt dem Hover-Expand */
const SIDEBAR_HOLE_PAD = 6

type PaneStyle = Record<string, string>

function clampHoleFrame(
  rect: {
    top: number
    left: number
    right: number
    bottom: number
    width: number
    height: number
    inOverlay?: boolean
    inSidebar?: boolean
  },
  viewportW: number,
  viewportH: number
) {
  const isSidebarTarget = !!rect.inSidebar || rect.left < SIDEBAR_RIGHT_EDGE
  const pad = isSidebarTarget ? SIDEBAR_HOLE_PAD : HOLE_PAD
  const padT =
    rect.top < VIEWPORT_INSET + pad ? Math.max(0, rect.top - VIEWPORT_INSET) : pad

  let top = Math.max(VIEWPORT_INSET, rect.top - padT)
  let left = Math.max(VIEWPORT_INSET, rect.left - pad)
  let right = Math.min(viewportW - VIEWPORT_INSET, rect.right + pad)
  let bottom = Math.min(viewportH - VIEWPORT_INSET, rect.bottom + pad)

  // Sehr hohe Blöcke (z. B. Zeitraum mit Datetime-Feldern): Loch deckeln,
  // sonst wächst/springt der Ring mit jeder Layout-Änderung.
  // Offener Kalender/Uhr: nicht abschneiden — Union mit Picker-Overlay.
  // Dialoge/Overlays: fast volle Höhe, damit z. B. Vorlagen-Dialog inkl. Actions sichtbar ist.
  const pickerOpen =
    typeof document !== 'undefined' &&
    !!document.querySelector(
      '.activity-date-picker-menu, .activity-date-picker-bottom-sheet__content, .v-time-picker, .v-overlay--active .v-picker, .onboarding-tour-menu-union, .v-overlay--active .v-autocomplete__content',
    )
  const allowTallHole =
    pickerOpen || !!rect.inOverlay || !!activeStep.value?.tallSpotlight
  const maxHoleH = Math.round(viewportH * (allowTallHole ? 0.88 : 0.42))
  if (bottom - top > maxHoleH) {
    bottom = top + maxHoleH
  }

  if (right <= left) right = left + Math.max(rect.width, 8)
  if (bottom <= top) bottom = top + Math.max(rect.height, 8)

  return {
    top,
    left,
    width: right - left,
    height: bottom - top,
    right,
    bottom,
  }
}

const hole = computed(() => {
  const rect = targetRect.value
  if (!rect) return null

  const viewportW = typeof window !== 'undefined' ? window.innerWidth : 800
  const viewportH = typeof window !== 'undefined' ? window.innerHeight : 600
  const frame = clampHoleFrame(rect, viewportW, viewportH)

  const topPane: PaneStyle = {
    top: '0',
    left: '0',
    width: '100%',
    height: `${frame.top}px`,
  }
  const leftPane: PaneStyle = {
    top: `${frame.top}px`,
    left: '0',
    width: `${frame.left}px`,
    height: `${frame.height}px`,
  }
  const rightPane: PaneStyle = {
    top: `${frame.top}px`,
    left: `${frame.right}px`,
    width: `${Math.max(0, viewportW - frame.right)}px`,
    height: `${frame.height}px`,
  }
  const bottomPane: PaneStyle = {
    top: `${frame.bottom}px`,
    left: '0',
    width: '100%',
    height: `${Math.max(0, viewportH - frame.bottom)}px`,
  }

  return {
    top: topPane,
    left: leftPane,
    right: rightPane,
    bottom: bottomPane,
    frame,
  }
})

const ringStyle = computed(() => {
  const frame = hole.value?.frame
  if (!frame) return {}
  return {
    top: `${frame.top}px`,
    left: `${frame.left}px`,
    width: `${frame.width}px`,
    height: `${frame.height}px`,
  }
})

const cardStyle = computed(() => {
  const rect = targetRect.value
  if (!rect) {
    return {
      top: '50%',
      left: '50%',
      transform: 'translate(-50%, -50%)',
      width: `${CARD_WIDTH}px`,
    }
  }

  const viewportW = typeof window !== 'undefined' ? window.innerWidth : 800
  const viewportH = typeof window !== 'undefined' ? window.innerHeight : 600
  const frame = hole.value?.frame
  const guideTop = frame?.top ?? rect.top
  const guideLeft = frame?.left ?? rect.left
  const guideRight = frame?.right ?? rect.right
  const guideBottom = frame?.bottom ?? rect.bottom

  const gutterLeft = Math.max(0, guideLeft - CARD_GAP)
  const gutterRight = Math.max(0, viewportW - guideRight - CARD_GAP)
  const spaceBelow = viewportH - guideBottom - CARD_GAP
  const spaceAbove = guideTop - HEADER_SAFE_TOP - CARD_GAP

  // Breite Accordion-/Content-Ziele wie Overlays behandeln — sonst liegt die Karte im Loch
  const isWideContentTarget = rect.width > Math.min(420, viewportW * 0.45)
  const isOverlayTarget =
    !!rect.inOverlay || (rect.width > 400 && rect.height > viewportH * 0.35) || isWideContentTarget

  /** Karte darf das Spotlight (frame) nicht überdecken. */
  function overlapsSpotlight(left: number, top: number, width: number, height: number) {
    const right = left + width
    const bottom = top + height
    return !(
      right <= guideLeft + 2 ||
      left >= guideRight - 2 ||
      bottom <= guideTop + 2 ||
      top >= guideBottom - 2
    )
  }

  // Grosse Übersicht (z. B. Lager): Karte fest rechts unten — volle Breite, nichts abschneiden
  if (activeStep.value?.cardPlacement === 'bottom-right' || activeStep.value?.tallSpotlight) {
    const width = Math.min(CARD_WIDTH, Math.max(280, viewportW - CARD_GAP * 2))
    const left = Math.max(CARD_GAP, viewportW - width - CARD_GAP)
    const top = Math.max(HEADER_SAFE_TOP, viewportH - CARD_EST_HEIGHT - CARD_GAP)
    return {
      top: `${top}px`,
      left: `${left}px`,
      width: `${width}px`,
      minWidth: `${Math.min(width, CARD_WIDTH)}px`,
    }
  }

  let top = Math.max(HEADER_SAFE_TOP, guideTop)
  let left = guideRight + CARD_GAP
  let width = Math.min(CARD_WIDTH, viewportW - CARD_GAP * 2)

  if (rect.left < SIDEBAR_RIGHT_EDGE + 40 && !isWideContentTarget) {
    left = Math.max(SIDEBAR_RIGHT_EDGE + CARD_GAP, guideRight + CARD_GAP)
    top = Math.min(Math.max(HEADER_SAFE_TOP, guideTop - 8), viewportH - CARD_EST_HEIGHT - CARD_GAP)
  } else if (isOverlayTarget || left + width > viewportW - CARD_GAP) {
    const preferLeft = gutterLeft >= gutterRight

    if (preferLeft && gutterLeft >= 240) {
      width = Math.min(CARD_WIDTH, Math.max(240, gutterLeft - CARD_GAP))
      left = Math.max(CARD_GAP, guideLeft - width - CARD_GAP)
      top = Math.min(Math.max(HEADER_SAFE_TOP, guideTop), viewportH - CARD_EST_HEIGHT - CARD_GAP)
    } else if (gutterRight >= 240) {
      width = Math.min(CARD_WIDTH, Math.max(240, gutterRight - CARD_GAP))
      left = Math.min(guideRight + CARD_GAP, viewportW - width - CARD_GAP)
      top = Math.min(Math.max(HEADER_SAFE_TOP, guideTop), viewportH - CARD_EST_HEIGHT - CARD_GAP)
    } else if (spaceBelow >= CARD_EST_HEIGHT) {
      width = Math.min(CARD_WIDTH, viewportW - CARD_GAP * 2)
      left = Math.min(
        viewportW - width - CARD_GAP,
        Math.max(CARD_GAP, guideLeft + (guideRight - guideLeft - width) / 2),
      )
      top = Math.max(HEADER_SAFE_TOP, guideBottom + CARD_GAP)
    } else if (spaceAbove >= CARD_EST_HEIGHT) {
      width = Math.min(CARD_WIDTH, viewportW - CARD_GAP * 2)
      left = Math.min(
        viewportW - width - CARD_GAP,
        Math.max(CARD_GAP, guideLeft + (guideRight - guideLeft - width) / 2),
      )
      top = Math.max(HEADER_SAFE_TOP, guideTop - CARD_EST_HEIGHT - CARD_GAP)
    } else {
      const side = gutterLeft >= gutterRight ? 'left' : 'right'
      width = Math.min(
        CARD_WIDTH,
        Math.max(200, (side === 'left' ? gutterLeft : gutterRight) - CARD_GAP),
      )
      left = side === 'left' ? CARD_GAP : viewportW - width - CARD_GAP
      top = HEADER_SAFE_TOP
    }
  }

  // Immer: Überlappung mit Spotlight vermeiden (Accordion, breite Panels, …)
  if (overlapsSpotlight(left, top, width, CARD_EST_HEIGHT)) {
    if (spaceBelow >= CARD_EST_HEIGHT) {
      width = Math.min(CARD_WIDTH, viewportW - CARD_GAP * 2)
      left = Math.min(
        viewportW - width - CARD_GAP,
        Math.max(CARD_GAP, guideLeft + Math.max(0, (guideRight - guideLeft - width) / 2)),
      )
      top = Math.max(HEADER_SAFE_TOP, guideBottom + CARD_GAP)
    } else if (spaceAbove >= CARD_EST_HEIGHT) {
      width = Math.min(CARD_WIDTH, viewportW - CARD_GAP * 2)
      left = Math.min(
        viewportW - width - CARD_GAP,
        Math.max(CARD_GAP, guideLeft + Math.max(0, (guideRight - guideLeft - width) / 2)),
      )
      top = Math.max(HEADER_SAFE_TOP, guideTop - CARD_EST_HEIGHT - CARD_GAP)
    } else if (gutterRight >= 200) {
      width = Math.min(CARD_WIDTH, gutterRight - CARD_GAP)
      left = viewportW - width - CARD_GAP
      top = HEADER_SAFE_TOP
    } else if (gutterLeft >= 200) {
      width = Math.min(CARD_WIDTH, gutterLeft - CARD_GAP)
      left = CARD_GAP
      top = HEADER_SAFE_TOP
    }
  }

  left = Math.min(Math.max(CARD_GAP, left), Math.max(CARD_GAP, viewportW - width - CARD_GAP))
  top = Math.min(Math.max(HEADER_SAFE_TOP, top), viewportH - CARD_EST_HEIGHT - CARD_GAP)

  return {
    top: `${top}px`,
    left: `${left}px`,
    width: `${width}px`,
  }
})
</script>

<style scoped>
.onboarding-tour-dim {
  position: fixed;
  inset: 0;
  z-index: 20040;
  pointer-events: none;
}

.onboarding-tour-dim__pane {
  position: fixed;
  background: rgba(15, 23, 42, 0.52);
  pointer-events: auto;
}

.onboarding-tour-ring {
  position: fixed;
  z-index: 1;
  pointer-events: none;
  border-radius: 10px;
  border: 2px solid #fff;
  /* Glow nach innen + aussen, ohne am Rail abgeschnitten zu wirken */
  box-shadow:
    0 0 0 3px rgba(22, 163, 74, 0.5),
    inset 0 0 0 1px rgba(22, 163, 74, 0.25);
  box-sizing: border-box;
}

.onboarding-tour-card-layer {
  position: fixed;
  inset: 0;
  z-index: 20060;
  pointer-events: none;
}

.onboarding-tour__card {
  --tour-accent: #16a34a;
  position: fixed;
  z-index: 2;
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  gap: 0;
  background: #fff;
  border-radius: 14px;
  padding: 0;
  box-shadow:
    0 1px 0 rgba(15, 23, 42, 0.04),
    0 18px 40px rgba(15, 23, 42, 0.2);
  max-height: min(70vh, 440px);
  overflow: hidden;
  border: 1px solid rgba(15, 23, 42, 0.08);
}

.onboarding-tour__card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--tour-accent);
}

.onboarding-tour__header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px 0 20px;
}

.onboarding-tour__eyebrow {
  margin: 0;
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.onboarding-tour__step {
  margin: 0;
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: #16a34a;
  background: #f0fdf4;
  border-radius: 999px;
  padding: 3px 8px;
  white-space: nowrap;
}

.onboarding-tour__title {
  margin: 10px 18px 0 20px;
  font-size: 1.125rem;
  font-weight: 650;
  line-height: 1.25;
  color: #0f172a;
}

.onboarding-tour__content {
  margin: 8px 18px 0 20px;
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  max-height: min(48vh, 320px);
}

.onboarding-tour__body {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: #475569;
  white-space: pre-line;
}

.onboarding-tour__list {
  margin: 10px 0 0;
  padding: 0 0 0 1.15rem;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 13.5px;
  line-height: 1.45;
  color: #334155;
}

.onboarding-tour__list li {
  padding-left: 2px;
}

.onboarding-tour__list li::marker {
  color: #16a34a;
}

.onboarding-tour__hint {
  margin: 10px 18px 0 20px;
  font-size: 13px;
  line-height: 1.4;
  color: #0369a1;
  font-weight: 500;
}

.onboarding-tour__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 16px;
  padding: 12px 14px 12px 12px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  flex-shrink: 0;
  min-width: 0;
}

.onboarding-tour__skip {
  color: #64748b !important;
  margin-inline-start: 4px;
  flex-shrink: 0;
}

.onboarding-tour__next {
  min-width: 96px;
  justify-content: center;
  flex-shrink: 0;
}

.onboarding-tour__cta-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
  margin-left: auto;
}
</style>

<style>
/* Ziel-Root über den Dimmer (zusätzlich zum echten Loch) */
.onboarding-tour-elevate-root {
  z-index: 20050 !important;
}

.onboarding-tour-target-active {
  pointer-events: auto;
  filter: none !important;
  opacity: 1 !important;
  scroll-margin: 88px 20px 120px;
}

/* App-Header hinter Tour-Dimmer/Karte — sonst liegt das Modal «unter» dem Header */
body.onboarding-tour-active .top-header.v-app-bar,
body.onboarding-tour-active .v-app-bar.top-header,
body.onboarding-tour-active .v-app-bar {
  z-index: 100 !important;
}
</style>
