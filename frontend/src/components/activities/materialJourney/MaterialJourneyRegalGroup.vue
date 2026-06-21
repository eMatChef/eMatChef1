<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import MaterialJourneyTaskRow from '@/components/activities/materialJourney/MaterialJourneyTaskRow.vue'
import MaterialJourneyCrateTaskRow from '@/components/activities/materialJourney/MaterialJourneyCrateTaskRow.vue'
import type { MaterialJourneyRegalGroup } from '@/components/activities/materialJourneyRegalGroups'
import type { MaterialJourneyTaskRow as TaskRow } from '@/components/activities/materialJourneyTaskList'
import type { ActivityPackContainerItem } from '@/api/activityContainers'

const props = defineProps<{
  group: MaterialJourneyRegalGroup
  listEditable: boolean
  movingId: string | null
  packCrateSelectMode?: boolean
  packTargetCrateId?: string | null
  containerItemsByContainerId?: Record<string, ActivityPackContainerItem[]>
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
}>()

const { t } = useI18n()

function crateContentsFor(row: TaskRow): ActivityPackContainerItem[] {
  if (row.kind !== 'crate' || !row.container) return []
  const items = props.containerItemsByContainerId?.[row.container.id] ?? []
  return items.filter((item) => (item.quantity_packed ?? 0) > 0)
}

function isPackTargetActive(row: TaskRow): boolean {
  return (
    Boolean(props.packCrateSelectMode) &&
    row.kind === 'crate' &&
    row.container?.id === props.packTargetCrateId
  )
}

function useCrateTaskRow(row: TaskRow): boolean {
  return Boolean(props.packCrateSelectMode) && row.kind === 'crate'
}
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
        <MaterialJourneyCrateTaskRow
          v-if="useCrateTaskRow(row)"
          :row="row"
          :moving="movingId === row.id"
          :readonly="!listEditable"
          :pack-target-active="isPackTargetActive(row)"
          :contents="crateContentsFor(row)"
          @activate="emit('activate', row)"
        />
        <MaterialJourneyTaskRow
          v-else
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
