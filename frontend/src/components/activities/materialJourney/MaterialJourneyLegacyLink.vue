<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const props = defineProps<{
  departmentId: string
  activityId: string
}>()

const { t } = useI18n()

const historyHref = computed(() => ({
  name: 'ActivityDetail' as const,
  params: {
    departmentId: props.departmentId,
    activityId: props.activityId,
  },
  query: { tab: 'history' },
}))
</script>

<template>
  <div class="material-journey-legacy-link">
    <RouterLink :to="historyHref" class="material-journey-legacy-link__anchor">
      {{ t('activities.materialJourney.historyLink') }}
    </RouterLink>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
