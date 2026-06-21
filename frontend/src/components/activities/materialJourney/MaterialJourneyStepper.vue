<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'

const props = defineProps<{
  steps: JourneyStep[]
  currentStep: JourneyStep
  profile: PackWorkflowProfile
}>()

const emit = defineEmits<{
  'update:currentStep': [step: JourneyStep]
}>()

const { t } = useI18n()

function stepLabel(step: JourneyStep): string {
  if (step === 'issue' && props.profile === 'logistics') {
    return t('activities.materialJourney.step.issueLogistics')
  }
  return t(`activities.materialJourney.step.${step}`)
}

const currentIndex = computed(() => Math.max(0, props.steps.indexOf(props.currentStep)))

const progressPercent = computed(() => {
  if (props.steps.length <= 1) return 100
  return Math.round((currentIndex.value / (props.steps.length - 1)) * 100)
})

function onStepClick(step: JourneyStep): void {
  if (step === props.currentStep) return
  emit('update:currentStep', step)
}
</script>

<template>
  <nav class="material-journey-stepper section-card" aria-label="Material-Journey">
    <div
      class="material-journey-stepper__track"
      role="progressbar"
      :aria-valuenow="progressPercent"
      aria-valuemin="0"
      aria-valuemax="100"
    >
      <div class="material-journey-stepper__track-fill" :style="{ width: `${progressPercent}%` }" />
    </div>

    <ol class="material-journey-stepper__list">
      <li
        v-for="(step, index) in steps"
        :key="step"
        class="material-journey-stepper__item"
        :class="{
          'material-journey-stepper__item--active': step === currentStep,
          'material-journey-stepper__item--done': index < currentIndex,
          'material-journey-stepper__item--future': index > currentIndex,
        }"
      >
        <button
          type="button"
          class="material-journey-stepper__btn"
          :aria-current="step === currentStep ? 'step' : undefined"
          @click="onStepClick(step)"
        >
          <span class="material-journey-stepper__index" aria-hidden="true">
            <v-icon
              v-if="index < currentIndex"
              icon="mdi-check"
              class="material-journey-stepper__check"
              size="16"
            />
            <span v-else class="material-journey-stepper__num">{{ index + 1 }}</span>
          </span>
          <span class="material-journey-stepper__label">{{ stepLabel(step) }}</span>
        </button>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
