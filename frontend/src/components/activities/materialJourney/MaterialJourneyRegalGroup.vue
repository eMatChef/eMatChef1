<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import MaterialJourneyTaskRow from '@/components/activities/materialJourney/MaterialJourneyTaskRow.vue'
import type { MaterialJourneyRegalGroup } from '@/components/activities/materialJourneyRegalGroups'
import type { MaterialJourneyTaskRow as TaskRow } from '@/components/activities/materialJourneyTaskList'

defineProps<{
  group: MaterialJourneyRegalGroup
  listEditable: boolean
  movingId: string | null
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
}>()

const { t } = useI18n()
</script>

<template>
  <section class="material-journey-regal-group">
    <header class="material-journey-regal-group__header">
      <h3 class="material-journey-regal-group__title">{{ group.label }}</h3>
      <span class="material-journey-regal-group__meta text-muted">
        {{ t('activities.materialJourney.regalGroup.summary', { count: group.openCount }) }}
      </span>
    </header>
    <ul class="material-journey-regal-group__items">
      <li v-for="row in group.rows" :key="row.id">
        <MaterialJourneyTaskRow
          :row="row"
          :moving="movingId === row.id"
          :readonly="!listEditable"
          @activate="emit('activate', row)"
        />
      </li>
    </ul>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
