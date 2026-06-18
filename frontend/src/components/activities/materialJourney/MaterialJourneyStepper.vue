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

const currentIndex = computed(() => props.steps.indexOf(props.currentStep))

function onStepClick(step: JourneyStep): void {
  if (step === props.currentStep) return
  emit('update:currentStep', step)
}
</script>

<template>
  <nav class="material-journey-stepper" aria-label="Material-Journey">
    <ol class="material-journey-stepper__list">
      <li
        v-for="(step, index) in steps"
        :key="step"
        class="material-journey-stepper__item"
        :class="{
          'material-journey-stepper__item--active': step === currentStep,
          'material-journey-stepper__item--done': index < currentIndex,
        }"
      >
        <button
          type="button"
          class="material-journey-stepper__btn"
          :aria-current="step === currentStep ? 'step' : undefined"
          @click="onStepClick(step)"
        >
          <span class="material-journey-stepper__index">{{ index + 1 }}</span>
          <span class="material-journey-stepper__label">{{ stepLabel(step) }}</span>
        </button>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
