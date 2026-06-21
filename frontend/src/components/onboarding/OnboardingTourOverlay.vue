<template>
  <Teleport to="body">
    <div v-if="isActive && activeStep" class="onboarding-tour" role="dialog" aria-modal="true">
      <div
        class="onboarding-tour__backdrop"
        :class="{ 'onboarding-tour__backdrop--passive': expectsTargetClick }"
        @click="onBackdropClick"
      />

      <div
        v-if="targetRect"
        class="onboarding-tour__spotlight"
        :style="spotlightStyle"
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

const CARD_WIDTH = 320
const CARD_GAP = 16

const spotlightStyle = computed(() => {
  const rect = targetRect.value
  if (!rect) return {}
  const pad = 6
  return {
    top: `${Math.max(8, rect.top - pad)}px`,
    left: `${Math.max(8, rect.left - pad)}px`,
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
  let top = rect.bottom + CARD_GAP
  let left = rect.left

  if (top + 200 > viewportH) {
    top = Math.max(CARD_GAP, rect.top - 200 - CARD_GAP)
  }
  if (left + CARD_WIDTH > viewportW - CARD_GAP) {
    left = viewportW - CARD_WIDTH - CARD_GAP
  }
  left = Math.max(CARD_GAP, left)

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
.onboarding-tour {
  position: fixed;
  inset: 0;
  z-index: 2400;
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
  border-radius: 10px;
  box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.45);
  pointer-events: none;
  z-index: 1;
}

.onboarding-tour__card {
  position: fixed;
  z-index: 2;
  pointer-events: auto;
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
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
  z-index: 2405 !important;
  pointer-events: auto;
}
</style>
