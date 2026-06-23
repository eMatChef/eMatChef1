<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'

const props = defineProps<{
  steps: JourneyStep[]
  /** Gewählter Schritt in der URL */
  currentStep: JourneyStep
  /** Bearbeitbarer Pipeline-Checkpoint (Backend) */
  activeStep: JourneyStep
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

const activeIndex = computed(() => Math.max(0, props.steps.indexOf(props.activeStep)))
const viewedIndex = computed(() => Math.max(0, props.steps.indexOf(props.currentStep)))

const progressPercent = computed(() => {
  if (props.steps.length <= 1) return 100
  return Math.round((activeIndex.value / (props.steps.length - 1)) * 100)
})

function onStepClick(step: JourneyStep): void {
  if (step === props.currentStep) return
  emit('update:currentStep', step)
}
</script>

<template>
  <nav class="material-journey-stepper" aria-label="Material-Journey">
    <div
      class="material-journey-stepper__track"
      role="progressbar"
      :aria-valuenow="progressPercent"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="t('activities.materialJourney.stepper.progress', { percent: progressPercent })"
    >
      <div class="material-journey-stepper__track-fill" :style="{ width: `${progressPercent}%` }" />
    </div>

    <ol class="material-journey-stepper__pipeline">
      <li
        v-for="(step, index) in steps"
        :key="step"
        class="material-journey-stepper__seg"
        :class="{
          'material-journey-stepper__seg--first': index === 0,
          'material-journey-stepper__seg--last': index === steps.length - 1,
          'material-journey-stepper__seg--active': step === currentStep,
          'material-journey-stepper__seg--checkpoint': step === activeStep && step !== currentStep,
          'material-journey-stepper__seg--done': index < activeIndex,
          'material-journey-stepper__seg--future': index > activeIndex,
        }"
      >
        <button
          type="button"
          class="material-journey-stepper__seg-btn"
          :aria-current="step === currentStep ? 'step' : undefined"
          :aria-label="stepLabel(step)"
          :title="stepLabel(step)"
          @click="onStepClick(step)"
        >
          <span class="material-journey-stepper__seg-icon" aria-hidden="true">
            <v-icon
              v-if="index < activeIndex"
              icon="mdi-check"
              class="material-journey-stepper__check"
              size="16"
            />
            <span v-else class="material-journey-stepper__num">{{ index + 1 }}</span>
          </span>
          <span class="material-journey-stepper__seg-label">
            <span class="material-journey-stepper__seg-label-num">{{ index + 1 }}.</span>
            {{ stepLabel(step) }}
          </span>
        </button>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-journey-stepper__seg--checkpoint .material-journey-stepper__seg-btn {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}
</style>
