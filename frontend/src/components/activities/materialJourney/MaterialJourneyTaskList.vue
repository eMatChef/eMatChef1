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
import {
  materialJourneyAccordionLinesForRow,
  type MaterialJourneyAccordionLine,
} from '@/components/activities/materialJourneyAccordionLines'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ActivityPackContainer } from '@/api/activityContainers'
import { reassignTargetPackCrates } from '@/composables/useMaterialJourneyCrateTransfer'

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
  packItems?: ActivityPackItem[]
  packContainers?: ActivityPackContainer[]
  cratePeekMaps?: MaterialJourneyCratePeekMaps
  shellPackItemForContainer?: (containerId: string) => ActivityPackItem | undefined
  showTransitActions?: boolean
  showMoveBack?: boolean
  moveBackQtyForRow?: (row: TaskRow) => number
  showMoveForward?: boolean
  showCrateMoveForward?: boolean
  moveForwardQtyForRow?: (row: TaskRow) => number
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
  selectTarget: [row: TaskRow]
  looseTake: [row: TaskRow, line: MaterialJourneyAccordionLine]
  reassign: [row: TaskRow, line: MaterialJourneyAccordionLine]
  moveBack: [row: TaskRow, qty: number]
  'update:moveBackQty': [row: TaskRow, qty: number]
  moveForward: [row: TaskRow, qty: number]
  'update:moveForwardQty': [row: TaskRow, qty: number]
}>()

const { t } = useI18n()

const isByShelf = computed(() => props.filterTab === 'byShelf')

function isExpandableRow(row: TaskRow): boolean {
  return row.kind === 'crate' || row.kind === 'combo'
}

function previewLinesFor(row: TaskRow): MaterialJourneyAccordionLine[] {
  if (!isExpandableRow(row)) return []
  if (
    !props.containerItemsByContainerId ||
    !props.packItems ||
    !props.packContainers ||
    !props.cratePeekMaps ||
    !props.shellPackItemForContainer
  ) {
    return []
  }
  return materialJourneyAccordionLinesForRow(row, {
    containerItemsByContainerId: props.containerItemsByContainerId,
    cratePeekMaps: props.cratePeekMaps,
    packItems: props.packItems,
    packContainers: props.packContainers,
    shellPackItemForContainer: props.shellPackItemForContainer,
    t,
  })
}

function isPackTargetActive(row: TaskRow): boolean {
  return (
    Boolean(props.packCrateSelectMode) &&
    row.kind === 'crate' &&
    row.container?.id === props.packTargetCrateId
  )
}

function isPackTargetSelectable(row: TaskRow): boolean {
  return Boolean(props.packCrateSelectMode) && row.kind === 'crate'
}

function hasReassignTargetsFor(row: TaskRow): boolean {
  if (row.kind !== 'crate' || !row.container) return false
  if (!props.packContainers || !props.shellPackItemForContainer) return false
  return (
    reassignTargetPackCrates(
      props.packContainers,
      row.container.id,
      props.shellPackItemForContainer,
    ).length > 0
  )
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
        :pack-items="packItems"
        :pack-containers="packContainers"
        :crate-peek-maps="cratePeekMaps"
        :shell-pack-item-for-container="shellPackItemForContainer"
        :show-transit-actions="showTransitActions"
        :show-move-back="showMoveBack"
        :move-back-qty-for-row="moveBackQtyForRow"
        :show-move-forward="showMoveForward"
        :show-crate-move-forward="showCrateMoveForward"
        :move-forward-qty-for-row="moveForwardQtyForRow"
        :has-reassign-targets-for-row="hasReassignTargetsFor"
        @activate="emit('activate', $event)"
        @select-target="emit('selectTarget', $event)"
        @loose-take="emit('looseTake', row, $event)"
        @reassign="emit('reassign', row, $event)"
        @move-back="(row, qty) => emit('moveBack', row, qty)"
        @update:move-back-qty="(row, qty) => emit('update:moveBackQty', row, qty)"
        @move-forward="(row, qty) => emit('moveForward', row, qty)"
        @update:move-forward-qty="(row, qty) => emit('update:moveForwardQty', row, qty)"
      />
    </div>

    <ul v-else class="material-journey-task-list__items">
      <li v-for="row in tasks" :key="row.id">
        <MaterialJourneyCrateTaskRow
          v-if="isExpandableRow(row)"
          :row="row"
          :moving="movingId === row.id"
          :readonly="!listEditable"
          :pack-target-active="isPackTargetActive(row)"
          :pack-target-selectable="isPackTargetSelectable(row)"
          :preview-lines="previewLinesFor(row)"
          :show-transit-actions="showTransitActions"
          :show-move-back="showMoveBack"
          :move-back-qty="moveBackQtyForRow?.(row)"
          :show-move-forward="showMoveForward"
          :show-crate-move-forward="showCrateMoveForward"
          :move-forward-qty="moveForwardQtyForRow?.(row)"
          :has-reassign-targets="hasReassignTargetsFor(row)"
          @activate="emit('activate', row)"
          @select-target="emit('selectTarget', row)"
          @loose-take="emit('looseTake', row, $event)"
          @reassign="emit('reassign', row, $event)"
          @move-back="emit('moveBack', row, $event)"
          @update:move-back-qty="emit('update:moveBackQty', row, $event)"
          @move-forward="emit('moveForward', row, $event)"
          @update:move-forward-qty="emit('update:moveForwardQty', row, $event)"
        />
        <MaterialJourneyTaskRow
          v-else
          :row="row"
          :moving="movingId === row.id"
          :readonly="!listEditable"
          :show-move-back="showMoveBack"
          :move-back-qty="moveBackQtyForRow?.(row)"
          :show-move-forward="showMoveForward"
          :show-crate-move-forward="showCrateMoveForward"
          :move-forward-qty="moveForwardQtyForRow?.(row)"
          @activate="emit('activate', row)"
          @move-back="emit('moveBack', row, $event)"
          @update:move-back-qty="emit('update:moveBackQty', row, $event)"
          @move-forward="emit('moveForward', row, $event)"
          @update:move-forward-qty="emit('update:moveForwardQty', row, $event)"
        />
      </li>
    </ul>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
