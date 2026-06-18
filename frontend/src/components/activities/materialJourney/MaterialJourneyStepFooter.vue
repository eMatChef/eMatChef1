<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  isJourneyReturnStep,
  isJourneyStoreStep,
  isJourneyTransportBackStep,
  isJourneyTransportOutStep,
} from '@/components/activities/materialJourneySteps'

const props = defineProps<{
  doneCount: number
  totalCount: number
  openCount: number
  journeyStep: JourneyStep
}>()

const { t } = useI18n()

const openHint = computed(() => {
  if (props.openCount <= 0) return null
  if (isJourneyReturnStep(props.journeyStep)) {
    return t('activities.materialJourney.footer.openHintReturn', { count: props.openCount })
  }
  if (isJourneyStoreStep(props.journeyStep)) {
    return t('activities.materialJourney.footer.openHintStore', { count: props.openCount })
  }
  if (isJourneyTransportOutStep(props.journeyStep)) {
    return t('activities.materialJourney.footer.openHintTransportOut', { count: props.openCount })
  }
  if (isJourneyTransportBackStep(props.journeyStep)) {
    return t('activities.materialJourney.footer.openHintTransportBack', { count: props.openCount })
  }
  return t('activities.materialJourney.footer.openHint', { count: props.openCount })
})
</script>

<template>
  <footer v-if="totalCount > 0" class="material-journey-step-footer">
    <p class="material-journey-step-footer__progress">
      {{ t('activities.materialJourney.footer.progress', { done: doneCount, total: totalCount }) }}
    </p>
    <p v-if="openHint" class="material-journey-step-footer__hint text-muted">
      {{ openHint }}
    </p>
  </footer>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
