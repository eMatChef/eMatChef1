<template>
  <Teleport to="body">
    <template v-if="isActive && activeStep">
      <!--
        Vier Dimmer-Paneele lassen ein echtes Loch (kein Element über dem Ziel).
        Ring + Karte liegen darüber; elevate-root zieht Drawer/Header zusätzlich nach vorne.
      -->
      <div
        class="onboarding-tour-dim"
        :class="{ 'onboarding-tour-dim--passive': expectsTargetClick }"
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
          <p class="onboarding-tour__body">{{ t(activeStep.bodyKey) }}</p>
          <p v-if="expectsTargetClick" class="onboarding-tour__hint">
            {{ t('onboarding.tours.clickTargetHint') }}
          </p>
          <footer class="onboarding-tour__footer">
            <EButton variant="text" size="small" class="onboarding-tour__skip" @click="skip">
              {{ t('onboarding.tours.skip') }}
            </EButton>
            <EButton
              v-if="!expectsTargetClick"
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
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { useOnboardingTour } from '@/composables/useOnboardingTour'

const { t } = useI18n()
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
} = useOnboardingTour()

const CARD_WIDTH = 360
const CARD_GAP = 20
const CARD_EST_HEIGHT = 240
const SIDEBAR_RIGHT_EDGE = 72
/** Innenabstand Loch/Ring — Border (2) + Glow (3) müssen hineinpassen */
const HOLE_PAD = 10
const VIEWPORT_INSET = 4
/** Extra rechts bei Sidebar, damit Ring nicht am Rail-Rand endet */
const SIDEBAR_EXTRA_RIGHT = 14

type PaneStyle = Record<string, string>

function clampHoleFrame(rect: DOMRect, viewportW: number, viewportH: number) {
  const isSidebarTarget = rect.left < SIDEBAR_RIGHT_EDGE
  const padL = isSidebarTarget
    ? Math.max(0, rect.left - VIEWPORT_INSET)
    : HOLE_PAD
  const padT = rect.top < VIEWPORT_INSET + HOLE_PAD ? Math.max(0, rect.top - VIEWPORT_INSET) : HOLE_PAD
  const padR = isSidebarTarget ? HOLE_PAD + SIDEBAR_EXTRA_RIGHT : HOLE_PAD
  const padB = HOLE_PAD

  let top = Math.max(VIEWPORT_INSET, rect.top - padT)
  let left = Math.max(VIEWPORT_INSET, rect.left - padL)
  let right = Math.min(viewportW - VIEWPORT_INSET, rect.right + padR)
  let bottom = Math.min(viewportH - VIEWPORT_INSET, rect.bottom + padB)

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
  const maxLeft = viewportW - CARD_WIDTH - CARD_GAP

  let top = rect.top
  let left = rect.right + CARD_GAP

  if (rect.left < SIDEBAR_RIGHT_EDGE + 40) {
    left = Math.max(SIDEBAR_RIGHT_EDGE + CARD_GAP, rect.right + CARD_GAP)
    top = Math.min(Math.max(CARD_GAP, rect.top - 8), viewportH - CARD_EST_HEIGHT - CARD_GAP)
  } else if (left > maxLeft) {
    left = Math.max(CARD_GAP, rect.left - CARD_WIDTH - CARD_GAP)
    if (left < SIDEBAR_RIGHT_EDGE && rect.left >= SIDEBAR_RIGHT_EDGE) {
      left = SIDEBAR_RIGHT_EDGE + CARD_GAP
    }
    if (left + CARD_WIDTH > rect.left - 8) {
      left = Math.min(maxLeft, Math.max(SIDEBAR_RIGHT_EDGE + CARD_GAP, rect.left))
      top = rect.bottom + CARD_GAP
      if (top + CARD_EST_HEIGHT > viewportH) {
        top = Math.max(CARD_GAP, rect.top - CARD_EST_HEIGHT - CARD_GAP)
      }
    }
  }

  left = Math.min(Math.max(CARD_GAP, left), maxLeft)
  top = Math.min(Math.max(CARD_GAP, top), viewportH - CARD_EST_HEIGHT - CARD_GAP)

  return {
    top: `${top}px`,
    left: `${left}px`,
    width: `${Math.min(CARD_WIDTH, viewportW - CARD_GAP * 2)}px`,
  }
})
</script>

<style scoped>
.onboarding-tour-dim {
  position: fixed;
  inset: 0;
  z-index: 10040;
  pointer-events: none;
}

.onboarding-tour-dim__pane {
  position: fixed;
  background: rgba(15, 23, 42, 0.52);
  pointer-events: auto;
}

.onboarding-tour-dim--passive .onboarding-tour-dim__pane {
  pointer-events: none;
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
  z-index: 10060;
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

.onboarding-tour__body {
  margin: 8px 18px 0 20px;
  font-size: 14px;
  line-height: 1.5;
  color: #475569;
  white-space: pre-line;
  flex: 1 1 auto;
  overflow-y: auto;
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
}

.onboarding-tour__skip {
  color: #64748b !important;
  margin-inline-start: 4px;
}

.onboarding-tour__next {
  min-width: 96px;
  justify-content: center;
}
</style>

<style>
/* Ziel-Root über den Dimmer (zusätzlich zum echten Loch) */
.onboarding-tour-elevate-root {
  z-index: 10050 !important;
}

.onboarding-tour-target-active {
  pointer-events: auto;
  filter: none !important;
  opacity: 1 !important;
}
</style>
