<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import MaterialJourneyTaskRow from '@/components/activities/materialJourney/MaterialJourneyTaskRow.vue'
import MaterialJourneyCrateTaskRow from '@/components/activities/materialJourney/MaterialJourneyCrateTaskRow.vue'
import type { MaterialJourneyRegalGroup } from '@/components/activities/materialJourneyRegalGroups'
import type { MaterialJourneyTaskRow as TaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'
import {
  materialJourneyAccordionLinesForRow,
  type MaterialJourneyAccordionLine,
} from '@/components/activities/materialJourneyAccordionLines'
import type { ActivityPackContainerItem, ActivityPackContainer } from '@/api/activityContainers'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { isJourneyReturnStep } from '@/components/activities/materialJourneySteps'
import { reassignTargetPackCrates } from '@/composables/useMaterialJourneyCrateTransfer'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'

const props = defineProps<{
  group: MaterialJourneyRegalGroup
  listEditable: boolean
  movingId: string | null
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
  hasReassignTargetsForRow?: (row: TaskRow) => boolean
  showIssueForRow?: (row: TaskRow) => boolean
  showIssueForAccordionLine?: (row: TaskRow, line: MaterialJourneyAccordionLine) => boolean
  atEventQtyLabelForRow?: (row: TaskRow, previewLines: MaterialJourneyAccordionLine[]) => string | null
  atEventQtyLabelForLine?: (row: TaskRow, line: MaterialJourneyAccordionLine) => string | null
  isConsumableForMaterialId?: (materialItemId: string) => boolean
  journeyStep?: JourneyStep
  containerLineRemainingStore?: (ci: ActivityPackContainerItem) => number
  shellStorePendingQtyForRow?: (row: TaskRow) => number
  showCrateContentActions?: boolean
  deleteEmptySubmittingForRow?: (row: TaskRow) => boolean
}>()

const emit = defineEmits<{
  activate: [row: TaskRow]
  wetReturn: [row: TaskRow]
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

function isExpandableRow(row: TaskRow): boolean {
  return isMaterialJourneyCrateKind(row.kind) || row.kind === 'combo'
}

function showStoreForLooseRow(row: TaskRow): boolean {
  return props.journeyStep === 'store' && row.canMove && row.isOpen && row.maxForwardQty > 0
}

function showReturnForLooseRow(row: TaskRow): boolean {
  if (!isJourneyReturnStep(props.journeyStep) || row.kind !== 'loose' || !row.packItem) return false
  if (!row.isOpen) return false
  return row.canMove || row.maxForwardQty > 0 || (row.packItem.quantityWet ?? 0) > 0
}

function showWetReturnForLooseRow(row: TaskRow): boolean {
  return showReturnForLooseRow(row)
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
    isMaterialJourneyCrateKind(row.kind) &&
    row.container?.id === props.packTargetCrateId
  )
}

function isPackTargetSelectable(row: TaskRow): boolean {
  return Boolean(props.packCrateSelectMode) && isMaterialJourneyCrateKind(row.kind)
}

function hasReassignTargetsFor(row: TaskRow): boolean {
  if (props.hasReassignTargetsForRow && !props.hasReassignTargetsForRow(row)) return false
  return reassignTargetsFor(row).length > 0
}

function reassignTargetsFor(row: TaskRow): { id: string; label: string }[] {
  if (!isMaterialJourneyCrateKind(row.kind) || !row.container) return []
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
          :show-store-action="showStoreForLooseRow(row)"
          :show-return-action="showReturnForLooseRow(row)"
          :show-wet-return-action="showWetReturnForLooseRow(row)"
          :at-event-qty-label="atEventLabelForRow(row)"
          :is-consumable-for-material-id="isConsumableForMaterialId"
          @activate="emit('activate', row)"
          @wet-return="emit('wetReturn', row)"
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
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
