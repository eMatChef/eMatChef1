<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import MaterialJourneyTaskRow from '@/components/activities/materialJourney/MaterialJourneyTaskRow.vue'
import MaterialJourneyCrateTaskRow from '@/components/activities/materialJourney/MaterialJourneyCrateTaskRow.vue'
import MaterialJourneyRegalGroup from '@/components/activities/materialJourney/MaterialJourneyRegalGroup.vue'
import type { MaterialJourneyRegalGroup as RegalGroup } from '@/components/activities/materialJourneyRegalGroups'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
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
  filterVariant?: 'default' | 'quickIssue'
  /** Einlagern: immer nach Regal gruppieren */
  groupByShelf?: boolean
  journeyStep?: JourneyStep
  isEarlyPackPreview: boolean
  positionCount: number
  listEditable: boolean
  movingId: string | null
  totalOpenCount?: number
  listFilterActive?: boolean
  packCrateSelectMode?: boolean
  packTargetCrateId?: string | null
  packTargetCrateLabel?: string | null
  transportTourAssignActive?: boolean
  transportTargetTourLabel?: string | null
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
  showIssueForRow?: (row: TaskRow) => boolean
  showIssueForAccordionLine?: (row: TaskRow, line: MaterialJourneyAccordionLine) => boolean
  atEventQtyLabelForRow?: (row: TaskRow, previewLines: MaterialJourneyAccordionLine[]) => string | null
  atEventQtyLabelForLine?: (row: TaskRow, line: MaterialJourneyAccordionLine) => string | null
  isConsumableForMaterialId?: (materialItemId: string) => boolean
  containerLineRemainingStore?: (ci: ActivityPackContainerItem) => number
  shellStorePendingQtyForRow?: (row: TaskRow) => number
  showCrateContentActions?: boolean
  deleteEmptySubmittingForRow?: (row: TaskRow) => boolean
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
  selectTarget: [row: TaskRow]
  looseTake: [row: TaskRow, line: MaterialJourneyAccordionLine]
  reassignTo: [row: TaskRow, line: MaterialJourneyAccordionLine, targetContainerId: string]
  deleteEmpty: [row: TaskRow]
  moveBack: [row: TaskRow, qty: number]
  'update:moveBackQty': [row: TaskRow, qty: number]
  moveForward: [row: TaskRow, qty: number]
  'update:moveForwardQty': [row: TaskRow, qty: number]
  consumed: [row: TaskRow]
  loss: [row: TaskRow]
  repair: [row: TaskRow]
  damage: [row: TaskRow]
  lineConsumed: [row: TaskRow, line: MaterialJourneyAccordionLine]
  lineLoss: [row: TaskRow, line: MaterialJourneyAccordionLine]
  lineRepair: [row: TaskRow, line: MaterialJourneyAccordionLine]
  lineDamage: [row: TaskRow, line: MaterialJourneyAccordionLine]
  storeLine: [row: TaskRow, line: MaterialJourneyAccordionLine]
  storeShell: [row: TaskRow]
}>()

const { t } = useI18n()

const isByShelf = computed(() => props.groupByShelf === true || props.filterTab === 'byShelf')
const isStoreByShelf = computed(() => props.journeyStep === 'store' && isByShelf.value)

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

function isPackCrateAssignActive(): boolean {
  return Boolean(props.packCrateSelectMode && props.packTargetCrateId)
}

function isPackTargetActive(row: TaskRow): boolean {
  return (
    Boolean(props.packTargetCrateId) &&
    row.kind === 'crate' &&
    row.container?.id === props.packTargetCrateId
  )
}

function isPackTargetSelectable(row: TaskRow): boolean {
  return Boolean(props.packCrateSelectMode) && row.kind === 'crate'
}

function atEventLabelForRow(row: TaskRow): string | null {
  if (!props.atEventQtyLabelForRow) return null
  return props.atEventQtyLabelForRow(row, previewLinesFor(row))
}

function atEventLabelForLine(row: TaskRow, line: MaterialJourneyAccordionLine): string | null {
  return props.atEventQtyLabelForLine?.(row, line) ?? null
}

function showIssueForAccordionLine(row: TaskRow, line: MaterialJourneyAccordionLine): boolean {
  return props.showIssueForAccordionLine?.(row, line) ?? false
}

function hasReassignTargetsFor(row: TaskRow): boolean {
  return reassignTargetsFor(row).length > 0
}

function reassignTargetsFor(row: TaskRow): { id: string; label: string }[] {
  if (row.kind !== 'crate' || !row.container) return []
  if (!props.packContainers || !props.shellPackItemForContainer) return []
  return reassignTargetPackCrates(
    props.packContainers,
    row.container.id,
    props.shellPackItemForContainer,
  ).map((container) => ({
    id: container.id,
    label: container.label,
  }))
}

const listIsEmpty = computed(() =>
  isByShelf.value ? props.regalGroups.length === 0 : props.tasks.length === 0,
)

const isFilteredEmpty = computed(
  () => listIsEmpty.value && (props.totalOpenCount ?? 0) > 0 && Boolean(props.listFilterActive),
)

const emptyTitle = computed(() => {
  if (isFilteredEmpty.value) return t('activities.materialJourney.empty.filterTitle')
  if (props.filterTab === 'done') {
    if (props.filterVariant === 'quickIssue') {
      return t('activities.materialJourney.empty.doneTitleQuickIssue')
    }
    return t('activities.materialJourney.empty.doneTitle')
  }
  if (isStoreByShelf.value) return t('activities.materialJourney.empty.storeByShelfTitle')
  if (props.filterTab === 'byShelf') return t('activities.materialJourney.empty.byShelfTitle')
  if (props.filterVariant === 'quickIssue' && props.filterTab === 'open') {
    return t('activities.materialJourney.empty.openTitleQuickIssue')
  }
  return t('activities.materialJourney.empty.openTitle')
})

const emptyDescription = computed(() => {
  if (isFilteredEmpty.value) return t('activities.materialJourney.empty.filterDescription')
  if (props.filterTab === 'done') {
    if (props.filterVariant === 'quickIssue') {
      return t('activities.materialJourney.empty.doneDescriptionQuickIssue')
    }
    return t('activities.materialJourney.empty.doneDescription')
  }
  if (isStoreByShelf.value) return t('activities.materialJourney.empty.storeByShelfDescription')
  if (props.filterTab === 'byShelf') return t('activities.materialJourney.empty.byShelfDescription')
  if (props.filterVariant === 'quickIssue' && props.filterTab === 'open') {
    return t('activities.materialJourney.empty.openDescriptionQuickIssue')
  }
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
        :pack-target-crate-label="packTargetCrateLabel"
        :transport-tour-assign-active="transportTourAssignActive"
        :transport-target-tour-label="transportTargetTourLabel"
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
        :show-issue-for-row="showIssueForRow"
        :at-event-qty-label-for-row="atEventQtyLabelForRow"
        :at-event-qty-label-for-line="atEventQtyLabelForLine"
        :show-issue-for-accordion-line="showIssueForAccordionLine"
        :is-consumable-for-material-id="isConsumableForMaterialId"
        :journey-step="journeyStep"
        :container-line-remaining-store="containerLineRemainingStore"
        :shell-store-pending-qty-for-row="shellStorePendingQtyForRow"
        :show-crate-content-actions="showCrateContentActions"
        :delete-empty-submitting-for-row="deleteEmptySubmittingForRow"
        :has-reassign-targets-for-row="hasReassignTargetsFor"
        @activate="emit('activate', $event)"
        @select-target="emit('selectTarget', $event)"
        @loose-take="(row, line) => emit('looseTake', row, line)"
        @reassign-to="(row, line, targetId) => emit('reassignTo', row, line, targetId)"
        @delete-empty="emit('deleteEmpty', $event)"
        @move-back="(row, qty) => emit('moveBack', row, qty)"
        @update:move-back-qty="(row, qty) => emit('update:moveBackQty', row, qty)"
        @move-forward="(row, qty) => emit('moveForward', row, qty)"
        @update:move-forward-qty="(row, qty) => emit('update:moveForwardQty', row, qty)"
        @consumed="emit('consumed', $event)"
        @loss="emit('loss', $event)"
        @repair="emit('repair', $event)"
        @damage="emit('damage', $event)"
        @line-consumed="(row, line) => emit('lineConsumed', row, line)"
        @line-loss="(row, line) => emit('lineLoss', row, line)"
        @line-repair="(row, line) => emit('lineRepair', row, line)"
        @line-damage="(row, line) => emit('lineDamage', row, line)"
        @store-line="(row, line) => emit('storeLine', row, line)"
        @store-shell="(row) => emit('storeShell', row)"
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
          :transport-tour-assign-active="transportTourAssignActive"
          :transport-target-tour-label="transportTargetTourLabel"
          :has-reassign-targets="hasReassignTargetsFor(row)"
          :reassign-targets="reassignTargetsFor(row)"
          :show-crate-content-actions="showCrateContentActions"
          :delete-empty-submitting="deleteEmptySubmittingForRow?.(row) ?? false"
          :show-issue-actions="showIssueForRow?.(row) ?? false"
          :at-event-qty-label="atEventLabelForRow(row)"
          :at-event-qty-label-for-line="(line) => atEventLabelForLine(row, line)"
          :show-issue-for-accordion-line="(line) => showIssueForAccordionLine(row, line)"
          :is-consumable-for-material-id="isConsumableForMaterialId"
          :journey-step="journeyStep"
          :container-items-by-container-id="containerItemsByContainerId"
          :container-line-remaining-store="containerLineRemainingStore"
          :shell-store-pending-qty-for-row="shellStorePendingQtyForRow"
          @activate="emit('activate', row)"
          @select-target="emit('selectTarget', row)"
          @loose-take="emit('looseTake', row, $event)"
          @reassign-to="(line, targetId) => emit('reassignTo', row, line, targetId)"
          @delete-empty="emit('deleteEmpty', row)"
          @move-back="emit('moveBack', row, $event)"
          @update:move-back-qty="emit('update:moveBackQty', row, $event)"
          @move-forward="emit('moveForward', row, $event)"
          @update:move-forward-qty="emit('update:moveForwardQty', row, $event)"
          @consumed="emit('consumed', row)"
          @loss="emit('loss', row)"
          @repair="emit('repair', row)"
          @damage="emit('damage', row)"
          @line-consumed="emit('lineConsumed', row, $event)"
          @line-loss="emit('lineLoss', row, $event)"
          @line-repair="emit('lineRepair', row, $event)"
          @line-damage="emit('lineDamage', row, $event)"
          @store-line="emit('storeLine', row, $event)"
          @store-shell="emit('storeShell', row)"
        />
        <MaterialJourneyTaskRow
          v-else
          :row="row"
          :moving="movingId === row.id"
          :readonly="!listEditable"
          :show-move-back="showMoveBack"
          :move-back-qty="moveBackQtyForRow?.(row)"
          :show-move-forward="showMoveForward"
          :move-forward-qty="moveForwardQtyForRow?.(row)"
          :pack-crate-assign-active="isPackCrateAssignActive()"
          :pack-target-crate-label="packTargetCrateLabel"
          :transport-tour-assign-active="transportTourAssignActive"
          :transport-target-tour-label="transportTargetTourLabel"
          :show-issue-actions="showIssueForRow?.(row) ?? false"
          :at-event-qty-label="atEventLabelForRow(row)"
          :is-consumable-for-material-id="isConsumableForMaterialId"
          @activate="emit('activate', row)"
          @move-back="emit('moveBack', row, $event)"
          @update:move-back-qty="emit('update:moveBackQty', row, $event)"
          @move-forward="emit('moveForward', row, $event)"
          @update:move-forward-qty="emit('update:moveForwardQty', row, $event)"
          @consumed="emit('consumed', row)"
          @loss="emit('loss', row)"
          @repair="emit('repair', row)"
          @damage="emit('damage', row)"
        />
      </li>
    </ul>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
