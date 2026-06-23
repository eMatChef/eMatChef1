<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'

const props = defineProps<{
  fromStep: JourneyStep
  nextStep: JourneyStep
  totalCount: number
  loading?: boolean
}>()

const emit = defineEmits<{
  continue: []
}>()

const { t, te } = useI18n()

const i18nBase = computed(() => `activities.materialJourney.phaseComplete.${props.fromStep}`)

const nextStepLabel = computed(() => {
  const key = props.nextStep === 'issue' ? 'issueLogistics' : props.nextStep
  const fullKey = props.nextStep === 'issue' ? 'activities.materialJourney.step.issueLogistics' : `activities.materialJourney.step.${props.nextStep}`
  return te(fullKey) ? t(fullKey) : props.nextStep
})

const title = computed(() =>
  te(`${i18nBase.value}.title`) ? t(`${i18nBase.value}.title`) : t('activities.materialJourney.phaseComplete.defaultTitle'),
)

const description = computed(() =>
  te(`${i18nBase.value}.description`)
    ? t(`${i18nBase.value}.description`, { count: props.totalCount })
    : t('activities.materialJourney.phaseComplete.defaultDescription', { count: props.totalCount }),
)

const continueLabel = computed(() =>
  te(`${i18nBase.value}.continue`)
    ? t(`${i18nBase.value}.continue`, { next: nextStepLabel.value })
    : t('activities.materialJourney.phaseComplete.defaultContinue', { next: nextStepLabel.value }),
)

const hint = computed(() =>
  te(`${i18nBase.value}.hint`) ? t(`${i18nBase.value}.hint`) : '',
)
</script>

<template>
  <section class="material-journey-pack-complete section-card">
    <EEmptyState icon="mdi-check-circle-outline" :title="title" :description="description">
      <template #actions>
        <EButton
          variant="primary"
          size="default"
          class="material-journey-pack-complete__action"
          :loading="loading"
          @click="emit('continue')"
        >
          {{ continueLabel }}
        </EButton>
        <p v-if="hint" class="material-journey-pack-complete__hint text-muted">
          {{ hint }}
        </p>
      </template>
    </EEmptyState>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
