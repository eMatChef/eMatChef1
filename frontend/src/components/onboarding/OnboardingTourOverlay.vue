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
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--top" :style="hole.top" @click="onBackdropClick" />
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--left" :style="hole.left" @click="onBackdropClick" />
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--right" :style="hole.right" @click="onBackdropClick" />
          <div class="onboarding-tour-dim__pane onboarding-tour-dim__pane--bottom" :style="hole.bottom" @click="onBackdropClick" />
        </template>
        <div
          v-else
          class="onboarding-tour-dim__pane onboarding-tour-dim__pane--full"
          @click="onBackdropClick"
        />
      </div>

      <div
        v-if="hole"
        class="onboarding-tour-ring"
        :style="ringStyle"
        aria-hidden="true"
      />

      <div class="onboarding-tour-card-layer">
        <div class="onboarding-tour__card" :style="cardStyle" role="dialog" aria-modal="true">
          <p v-if="activeTour" class="onboarding-tour__eyebrow">
            {{ t(activeTour.titleKey) }}
            ·
            {{ t('onboarding.tours.stepOf', { current: activeStepIndex + 1, total: activeTour.steps.length }) }}
          </p>
          <h2 class="onboarding-tour__title">{{ t(activeStep.titleKey) }}</h2>
          <p class="onboarding-tour__body">{{ t(activeStep.bodyKey) }}</p>
          <p v-if="expectsTargetClick" class="onboarding-tour__hint">
            {{ t('onboarding.tours.clickTargetHint') }}
          </p>
          <div class="onboarding-tour__actions">
            <EButton variant="text" size="small" @click="skip">
              {{ t('onboarding.tours.skip') }}
            </EButton>
            <EButton v-if="!expectsTargetClick" variant="primary" size="small" @click="next">
              {{ isLastStep ? t('onboarding.tours.finish') : t('onboarding.tours.next') }}
            </EButton>
          </div>
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

const CARD_WIDTH = 340
const CARD_GAP = 16
const CARD_EST_HEIGHT = 220
const SIDEBAR_RIGHT_EDGE = 72
const HOLE_PAD = 8

type PaneStyle = Record<string, string>

const hole = computed(() => {
  const rect = targetRect.value
  if (!rect) return null

  const viewportW = typeof window !== 'undefined' ? window.innerWidth : 800
  const viewportH = typeof window !== 'undefined' ? window.innerHeight : 600

  const top = Math.max(0, rect.top - HOLE_PAD)
  const left = Math.max(0, rect.left - HOLE_PAD)
  const width = Math.min(viewportW - left, rect.width + HOLE_PAD * 2)
  const height = Math.min(viewportH - top, rect.height + HOLE_PAD * 2)
  const right = left + width
  const bottom = top + height

  const topPane: PaneStyle = {
    top: '0',
    left: '0',
    width: '100%',
    height: `${top}px`,
  }
  const leftPane: PaneStyle = {
    top: `${top}px`,
    left: '0',
    width: `${left}px`,
    height: `${height}px`,
  }
  const rightPane: PaneStyle = {
    top: `${top}px`,
    left: `${right}px`,
    width: `${Math.max(0, viewportW - right)}px`,
    height: `${height}px`,
  }
  const bottomPane: PaneStyle = {
    top: `${bottom}px`,
    left: '0',
    width: '100%',
    height: `${Math.max(0, viewportH - bottom)}px`,
  }

  return {
    top: topPane,
    left: leftPane,
    right: rightPane,
    bottom: bottomPane,
    frame: { top, left, width, height },
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
    top = Math.min(Math.max(CARD_GAP, rect.top), viewportH - CARD_EST_HEIGHT - CARD_GAP)
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

function onBackdropClick() {
  if (expectsTargetClick.value) return
  skip()
}
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
  background: rgba(15, 23, 42, 0.55);
  pointer-events: auto;
}

.onboarding-tour-dim--passive .onboarding-tour-dim__pane {
  pointer-events: none;
}

.onboarding-tour-ring {
  position: fixed;
  z-index: 10055;
  pointer-events: none;
  border-radius: 12px;
  border: 2px solid #fff;
  box-shadow:
    0 0 0 4px rgba(34, 197, 94, 0.4),
    0 10px 28px rgba(15, 23, 42, 0.28);
}

.onboarding-tour-card-layer {
  position: fixed;
  inset: 0;
  z-index: 10060;
  pointer-events: none;
}

.onboarding-tour__card {
  position: fixed;
  pointer-events: auto;
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.22);
  max-height: min(70vh, 420px);
  overflow-y: auto;
}

.onboarding-tour__eyebrow {
  margin: 0 0 6px;
  font-size: 12px;
  color: #64748b;
}

.onboarding-tour__title {
  margin: 0 0 8px;
  font-size: 1rem;
  font-weight: 600;
  color: #0f172a;
}

.onboarding-tour__body {
  margin: 0 0 10px;
  font-size: 14px;
  line-height: 1.45;
  color: #475569;
  white-space: pre-line;
}

.onboarding-tour__hint {
  margin: 0 0 14px;
  font-size: 13px;
  line-height: 1.4;
  color: #0284c7;
  font-weight: 500;
}

.onboarding-tour__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
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
