<template>
  <Teleport to="body">
    <div v-if="isActive && activeStep" class="onboarding-tour" role="dialog" aria-modal="true">
      <!-- Ohne Spotlight: Vollflächen-Dimmen. Mit Spotlight nur Loch-Schatten (sonst bleibt Grau im Loch). -->
      <div
        v-if="!targetRect"
        class="onboarding-tour__backdrop"
        :class="{ 'onboarding-tour__backdrop--passive': expectsTargetClick }"
        @click="onBackdropClick"
      />

      <div
        v-if="targetRect"
        class="onboarding-tour__spotlight"
        :class="{ 'onboarding-tour__spotlight--passive': expectsTargetClick }"
        :style="spotlightStyle"
        @click="onBackdropClick"
      />

      <div class="onboarding-tour__card" :style="cardStyle">
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
/** Desktop-Rail / Sidebar — Karte rechts davon halten */
const SIDEBAR_RIGHT_EDGE = 72

const spotlightStyle = computed(() => {
  const rect = targetRect.value
  if (!rect) return {}
  const pad = 8
  return {
    top: `${Math.max(4, rect.top - pad)}px`,
    left: `${Math.max(4, rect.left - pad)}px`,
    width: `${rect.width + pad * 2}px`,
    height: `${rect.height + pad * 2}px`,
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

  // Ziel links (Sidebar): Karte rechts daneben, nicht über der Navigation
  if (rect.left < SIDEBAR_RIGHT_EDGE + 40) {
    left = Math.max(SIDEBAR_RIGHT_EDGE + CARD_GAP, rect.right + CARD_GAP)
    top = Math.min(Math.max(CARD_GAP, rect.top), viewportH - CARD_EST_HEIGHT - CARD_GAP)
  } else if (left > maxLeft) {
    // Rechts kein Platz → links vom Ziel
    left = Math.max(CARD_GAP, rect.left - CARD_WIDTH - CARD_GAP)
    if (left < SIDEBAR_RIGHT_EDGE && rect.left >= SIDEBAR_RIGHT_EDGE) {
      left = SIDEBAR_RIGHT_EDGE + CARD_GAP
    }
    // Immer noch eng → unter/über dem Ziel
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
/* Über Vuetify-Dialoge (Standard ~2400), sonst liegen Wizard-Felder über «Weiter». */
.onboarding-tour {
  position: fixed;
  inset: 0;
  z-index: 2600;
  pointer-events: none;
}

.onboarding-tour__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  pointer-events: auto;
}

.onboarding-tour__backdrop--passive {
  pointer-events: none;
}

.onboarding-tour__spotlight {
  position: fixed;
  border-radius: 12px;
  box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.5);
  pointer-events: auto;
  z-index: 1;
  background: transparent;
}

.onboarding-tour__spotlight--passive {
  pointer-events: none;
}

.onboarding-tour__card {
  position: fixed;
  z-index: 3;
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
.onboarding-tour-target-active {
  position: relative;
  z-index: 2605 !important;
  pointer-events: auto;
  /* Klar sichtbar im Spotlight-Loch — kein zusätzliches Abdunkeln */
  filter: none !important;
  opacity: 1 !important;
}
</style>
