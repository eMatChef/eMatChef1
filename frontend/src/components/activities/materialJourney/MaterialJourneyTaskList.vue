<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
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
  packMultiSelect?: boolean
  isRowSelected?: (row: TaskRow) => boolean
  canGroupSelected?: boolean
  grouping?: boolean
  selectedCount?: number
  totalOpenCount?: number
  listFilterActive?: boolean
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
  'toggle-select': [row: TaskRow]
  'group-selected': []
}>()

const { t } = useI18n()

const isByShelf = computed(() => props.filterTab === 'byShelf')

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
    <div
      v-if="packMultiSelect && (selectedCount ?? 0) > 0"
      class="material-journey-pack-group-bar section-card"
    >
      <span class="text-muted">
        {{ t('activities.materialJourney.packGroup.selected', { count: selectedCount ?? 0 }) }}
      </span>
      <EButton
        variant="primary"
        size="small"
        :disabled="!canGroupSelected"
        :loading="grouping"
        @click="emit('group-selected')"
      >
        {{ t('activities.materialJourney.packGroup.groupButton') }}
      </EButton>
    </div>

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
        :pack-multi-select="packMultiSelect"
        :is-row-selected="isRowSelected"
        @activate="emit('activate', $event)"
        @toggle-select="emit('toggle-select', $event)"
      />
    </div>

    <ul v-else class="material-journey-task-list__items">
      <li v-for="row in tasks" :key="row.id">
        <MaterialJourneyTaskRow
          :row="row"
          :moving="movingId === row.id"
          :readonly="!listEditable"
          :selectable="packMultiSelect"
          :selected="isRowSelected?.(row)"
          @activate="emit('activate', row)"
          @toggle-select="emit('toggle-select', row)"
        />
      </li>
    </ul>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
