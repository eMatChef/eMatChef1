<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import type { MaterialJourneyJsSummary } from '@/components/activities/materialJourneyJsSummary'

const props = defineProps<{
  departmentId: string
  activityId: string
  summary: MaterialJourneyJsSummary
}>()

const { t } = useI18n()

const jsTabHref = computed(() => ({
  name: 'ActivityDetail' as const,
  params: {
    departmentId: props.departmentId,
    activityId: props.activityId,
  },
  query: { tab: 'js' },
}))
</script>

<template>
  <div class="material-journey-js-banner section-card">
    <div class="material-journey-js-banner__row">
      <span class="activity-js-tag">{{ t('activities.common.jsBadge') }}</span>
      <span class="material-journey-js-banner__hint">
        {{ t('activities.materialJourney.jsBanner.hint') }}
      </span>
    </div>
    <div v-if="summary.items > 0" class="material-journey-js-banner__metrics">
      <span>{{ t('activities.packList.jsSummaryPositions') }} <strong>{{ summary.items }}</strong></span>
      <span>{{ t('activities.packList.jsSummaryReceived') }} <strong>{{ summary.received }}</strong></span>
      <span>{{ t('activities.packList.jsSummaryReturned') }} <strong>{{ summary.returned }}</strong></span>
    </div>
    <p v-else class="material-journey-js-banner__empty text-muted">
      {{ t('activities.materialJourney.jsBanner.noPositionsYet') }}
    </p>
    <RouterLink :to="jsTabHref" class="material-journey-js-banner__link">
      {{ t('activities.jsMaterial.tab.openTab') }}
    </RouterLink>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
