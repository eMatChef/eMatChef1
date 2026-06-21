<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import MaterialJourneyTaskRow from '@/components/activities/materialJourney/MaterialJourneyTaskRow.vue'
import MaterialJourneyCrateTaskRow from '@/components/activities/materialJourney/MaterialJourneyCrateTaskRow.vue'
import type { MaterialJourneyRegalGroup } from '@/components/activities/materialJourneyRegalGroups'
import type { MaterialJourneyTaskRow as TaskRow } from '@/components/activities/materialJourneyTaskList'
import {
  materialJourneyAccordionLinesForRow,
  type MaterialJourneyAccordionLine,
} from '@/components/activities/materialJourneyAccordionLines'
import type { ActivityPackContainerItem, ActivityPackContainer } from '@/api/activityContainers'
import { reassignTargetPackCrates } from '@/composables/useMaterialJourneyCrateTransfer'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'

const props = defineProps<{
  group: MaterialJourneyRegalGroup
  listEditable: boolean
  movingId: string | null
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
  hasReassignTargetsForRow?: (row: TaskRow) => boolean
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
  if (props.hasReassignTargetsForRow) return props.hasReassignTargetsForRow(row)
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
          :move-forward-qty="moveForwardQtyForRow?.(row)"
          @activate="emit('activate', row)"
          @move-back="emit('moveBack', row, $event)"
          @update:move-back-qty="emit('update:moveBackQty', row, $event)"
          @move-forward="emit('moveForward', row, $event)"
          @update:move-forward-qty="emit('update:moveForwardQty', row, $event)"
        />
      </li>
    </ul>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
