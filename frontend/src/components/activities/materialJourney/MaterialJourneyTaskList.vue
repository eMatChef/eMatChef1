<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import MaterialJourneyTaskRow from '@/components/activities/materialJourney/MaterialJourneyTaskRow.vue'
import MaterialJourneyCrateTaskRow from '@/components/activities/materialJourney/MaterialJourneyCrateTaskRow.vue'
import MaterialJourneyRegalGroup from '@/components/activities/materialJourney/MaterialJourneyRegalGroup.vue'
import type { MaterialJourneyRegalGroup as RegalGroup } from '@/components/activities/materialJourneyRegalGroups'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
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
  totalOpenCount?: number
  listFilterActive?: boolean
  packCrateSelectMode?: boolean
  packTargetCrateId?: string | null
  containerItemsByContainerId?: Record<string, ActivityPackContainerItem[]>
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
}>()

const { t } = useI18n()

const isByShelf = computed(() => props.filterTab === 'byShelf')

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

const listIsEmpty = computed(() =>
  isByShelf.value ? props.regalGroups.length === 0 : props.tasks.length === 0,
)

const isFilteredEmpty = computed(
  () => listIsEmpty.value && (props.totalOpenCount ?? 0) > 0 && Boolean(props.listFilterActive),
)

const emptyTitle = computed(() => {
  if (isFilteredEmpty.value) return t('activities.materialJourney.empty.filterTitle')
  if (props.filterTab === 'done') return t('activities.materialJourney.empty.doneTitle')
  if (props.filterTab === 'byShelf') return t('activities.materialJourney.empty.byShelfTitle')
  return t('activities.materialJourney.empty.openTitle')
})

const emptyDescription = computed(() => {
  if (isFilteredEmpty.value) return t('activities.materialJourney.empty.filterDescription')
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
      v-else-if="listIsEmpty"
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
        :pack-crate-select-mode="packCrateSelectMode"
        :pack-target-crate-id="packTargetCrateId"
        :container-items-by-container-id="containerItemsByContainerId"
        @activate="emit('activate', $event)"
      />
    </div>

    <ul v-else class="material-journey-task-list__items">
      <li v-for="row in tasks" :key="row.id">
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
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
