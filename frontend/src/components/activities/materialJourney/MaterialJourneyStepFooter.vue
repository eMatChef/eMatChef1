<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { materialJourneyShowsMoveForwardQty } from '@/components/activities/materialJourneySteps'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'

const props = defineProps<{
  doneCount: number
  totalCount: number
  openCount: number
  journeyStep: JourneyStep
  profile: PackWorkflowProfile
  filterVariant?: 'default' | 'quickIssue'
}>()

const { t } = useI18n()

const progressText = computed(() =>
  props.filterVariant === 'quickIssue'
    ? t('activities.materialJourney.footer.progressQuickIssue', {
        done: props.doneCount,
        total: props.totalCount,
      })
    : t('activities.materialJourney.footer.progress', {
        done: props.doneCount,
        total: props.totalCount,
      }),
)

const openHint = computed(() => {
  if (props.openCount <= 0) return null
  if (materialJourneyShowsMoveForwardQty(props.journeyStep, props.profile)) {
    return t('activities.materialJourney.footer.openHintQty', { count: props.openCount })
  }
  return t('activities.materialJourney.footer.openHint', { count: props.openCount })
})
</script>

<template>
  <footer v-if="totalCount > 0" class="material-journey-step-footer">
    <p class="material-journey-step-footer__progress">
      {{ progressText }}
    </p>
    <p v-if="openHint" class="material-journey-step-footer__hint text-muted">
      {{ openHint }}
    </p>
  </footer>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
