<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import MaterialJourneyTaskRow from '@/components/activities/materialJourney/MaterialJourneyTaskRow.vue'
import MaterialJourneyRegalGroup from '@/components/activities/materialJourney/MaterialJourneyRegalGroup.vue'
import type { MaterialJourneyRegalGroup as RegalGroup } from '@/components/activities/materialJourneyRegalGroups'
import type {
  MaterialJourneyFilterTab,
  MaterialJourneyTaskRow as TaskRow,
} from '@/components/activities/materialJourneyTaskList'

const props = defineProps<{
  tasks: TaskRow[]
  regalGroups: RegalGroup[]
  filterTab: MaterialJourneyFilterTab
  isEarlyPackPreview: boolean
  positionCount: number
  listEditable: boolean
  movingId: string | null
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
}>()

const { t } = useI18n()

const isByShelf = computed(() => props.filterTab === 'byShelf')

const emptyTitle = computed(() => {
  if (props.filterTab === 'done') return t('activities.materialJourney.empty.doneTitle')
  if (props.filterTab === 'byShelf') return t('activities.materialJourney.empty.byShelfTitle')
  return t('activities.materialJourney.empty.openTitle')
})

const emptyDescription = computed(() => {
  if (props.filterTab === 'done') return t('activities.materialJourney.empty.doneDescription')
  if (props.filterTab === 'byShelf') return t('activities.materialJourney.empty.byShelfDescription')
  return t('activities.materialJourney.empty.openDescription')
})
</script>

<template>
  <div class="material-journey-task-list">
    <EEmptyState
      v-if="isEarlyPackPreview"
      class="material-journey-task-list__empty"
      icon="mdi-package-variant-closed"
      :title="t('activities.materialJourney.empty.earlyPreviewTitle')"
      :description="t('activities.materialJourney.empty.earlyPreviewDescription', { count: positionCount })"
    />

    <EEmptyState
      v-else-if="isByShelf ? regalGroups.length === 0 : tasks.length === 0"
      class="material-journey-task-list__empty"
      icon="mdi-format-list-checks"
      :title="emptyTitle"
      :description="emptyDescription"
    />

    <div v-else-if="isByShelf" class="material-journey-task-list__regal-groups">
      <MaterialJourneyRegalGroup
        v-for="group in regalGroups"
        :key="group.key"
        :group="group"
        :list-editable="listEditable"
        :moving-id="movingId"
        @activate="emit('activate', $event)"
      />
    </div>

    <ul v-else class="material-journey-task-list__items">
      <li v-for="row in tasks" :key="row.id">
        <MaterialJourneyTaskRow
          :row="row"
          :moving="movingId === row.id"
          :readonly="!listEditable"
          @activate="emit('activate', row)"
        />
      </li>
    </ul>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
