<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import PackMoveControls from '@/components/activities/PackMoveControls.vue'
import PackIssueQuickActions from '@/components/activities/PackIssueQuickActions.vue'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { isJourneyStoreStep } from '@/components/activities/materialJourneySteps'
import { resolveActionableContainerLine } from '@/components/activities/packShellCrateHelpers'

const props = defineProps<{
  row: MaterialJourneyTaskRow
  previewLines: MaterialJourneyAccordionLine[]
  moving: boolean
  readonly: boolean
  packTargetActive: boolean
  packTargetSelectable?: boolean
  showTransitActions?: boolean
  showMoveBack?: boolean
  moveBackQty?: number
  showMoveForward?: boolean
  showCrateMoveForward?: boolean
  moveForwardQty?: number
  transportTourAssignActive?: boolean
  transportTargetTourLabel?: string | null
  hasReassignTargets?: boolean
  reassignTargets?: { id: string; label: string }[]
  showCrateContentActions?: boolean
  deleteEmptySubmitting?: boolean
  showIssueActions?: boolean
  atEventQtyLabel?: string | null
  atEventQtyLabelForLine?: (line: MaterialJourneyAccordionLine) => string | null
  showIssueForAccordionLine?: (line: MaterialJourneyAccordionLine) => boolean
  isConsumableForMaterialId?: (materialItemId: string) => boolean
  journeyStep?: JourneyStep
  containerItemsByContainerId?: Record<string, ActivityPackContainerItem[]>
  containerLineRemainingStore?: (ci: ActivityPackContainerItem) => number
  shellStorePendingQtyForRow?: (row: MaterialJourneyTaskRow) => number
}>()

const emit = defineEmits<{
  activate: []
  selectTarget: []
  looseTake: [line: MaterialJourneyAccordionLine]
  reassignTo: [line: MaterialJourneyAccordionLine, targetContainerId: string]
  deleteEmpty: []
  moveBack: [qty: number]
  'update:moveBackQty': [qty: number]
  moveForward: [qty: number]
  'update:moveForwardQty': [qty: number]
  consumed: []
  loss: []
  repair: []
  damage: []
  lineConsumed: [line: MaterialJourneyAccordionLine]
  lineLoss: [line: MaterialJourneyAccordionLine]
  lineRepair: [line: MaterialJourneyAccordionLine]
  lineDamage: [line: MaterialJourneyAccordionLine]
  storeLine: [line: MaterialJourneyAccordionLine]
  storeShell: []
}>()

const { t } = useI18n()
const expanded = ref(false)

const isCombo = computed(() => props.row.kind === 'combo')
const isCrate = computed(() => props.row.kind === 'crate')
const isStoreStep = computed(() => isJourneyStoreStep(props.journeyStep ?? 'pack'))

function containerItemForLine(line: MaterialJourneyAccordionLine): ActivityPackContainerItem | undefined {
  const containerId = props.row.container?.id
  if (!containerId || !props.containerItemsByContainerId) return undefined
  const items = props.containerItemsByContainerId[containerId] ?? []
  const found =
    items.find((ci) => ci.id === line.id) ??
    (line.materialItemId
      ? items.find((ci) => ci.material_item_id === line.materialItemId)
      : undefined)
  if (!found) return undefined
  return resolveActionableContainerLine(containerId, found, props.containerItemsByContainerId)
}

function storePendingForLine(line: MaterialJourneyAccordionLine): number {
  const ci = containerItemForLine(line)
  if (!ci) return 0
  return props.containerLineRemainingStore?.(ci) ?? 0
}

const bookedPreviewLines = computed(() =>
  props.previewLines.filter((line) => !line.isWarehouseTemplate),
)
const warehouseTemplateLines = computed(() =>
  props.previewLines.filter((line) => line.isWarehouseTemplate),
)

const visiblePreviewLines = computed(() => {
  if (!isStoreStep.value) return bookedPreviewLines.value
  return bookedPreviewLines.value.filter((line) => storePendingForLine(line) > 0)
})

const hasPreview = computed(
  () => visiblePreviewLines.value.length > 0 || warehouseTemplateLines.value.length > 0,
)
const previewLineCount = computed(
  () => visiblePreviewLines.value.length + warehouseTemplateLines.value.length,
)

const showMoveForwardControls = computed(
  () =>
    Boolean(props.showMoveForward) &&
    props.row.isOpen &&
    props.row.canOpenSheet &&
    !props.readonly &&
    !props.packTargetSelectable &&
    (isCombo.value || Boolean(props.showCrateMoveForward)),
)

const showActionButtons = computed(
  () => Boolean(props.showTransitActions) && isCrate.value && !props.readonly && props.row.isOpen,
)

function showLineActions(line: MaterialJourneyAccordionLine): boolean {
  return showActionButtons.value && line.actionable === true
}

function showReassignForLine(line: MaterialJourneyAccordionLine): boolean {
  return (
    isCrate.value &&
    !props.readonly &&
    Boolean(props.showCrateContentActions) &&
    (props.reassignTargets?.length ?? 0) > 0 &&
    line.actionable === true &&
    !line.isWarehouseTemplate &&
    (line.maxReassignQty ?? line.quantity) > 0
  )
}

function showStoreAction(line: MaterialJourneyAccordionLine): boolean {
  return isStoreStep.value && !props.readonly && props.row.isOpen && storePendingForLine(line) > 0
}

const shellStorePendingQty = computed(() => props.shellStorePendingQtyForRow?.(props.row) ?? 0)

const showShellStoreAction = computed(
  () => isStoreStep.value && !props.readonly && props.row.isOpen && shellStorePendingQty.value > 0,
)

function onReassignSelect(line: MaterialJourneyAccordionLine, event: Event): void {
  const select = event.target as HTMLSelectElement
  const targetId = select.value.trim()
  select.value = ''
  if (!targetId) return
  emit('reassignTo', line, targetId)
}

const showDeleteEmptyCrate = computed(
  () =>
    isCrate.value &&
    !props.readonly &&
    Boolean(props.showCrateContentActions) &&
    bookedPreviewLines.value.length === 0 &&
    !props.moving,
)

const showMoveBackControls = computed(
  () => Boolean(props.showMoveBack) && props.row.canMoveBack && !props.readonly,
)

const forwardMaxQty = computed(() => {
  if (isCombo.value && props.row.packItem) {
    return Math.max(1, props.row.maxForwardQty)
  }
  return Math.max(1, props.row.maxForwardQty || 1)
})

const effectiveMoveForwardQty = computed(
  () => props.moveForwardQty ?? forwardMaxQty.value,
)

const effectiveMoveBackQty = computed(
  () => props.moveBackQty ?? props.row.maxMoveBackQty,
)

const forwardIntoTour = computed(
  () => Boolean(props.transportTourAssignActive) && showMoveForwardControls.value,
)

const forwardMoveTitle = computed(() => {
  if (!forwardIntoTour.value) {
    return t('activities.materialJourney.moveForward.action')
  }
  const label =
    props.transportTargetTourLabel?.trim() ||
    t('activities.materialJourney.transportTours.tourTargetFallback')
  return t('activities.materialJourney.row.assignToTourHint', { label })
})

const statusIcon = computed(() => {
  if (props.row.isDone) return '✓'
  if (props.row.openQty > 0 && props.row.doneQty > 0) return '◐'
  return '○'
})

const statusClass = computed(() => {
  if (props.row.isDone) return 'material-journey-task-row__status--done'
  if (props.row.openQty > 0 && props.row.doneQty > 0) {
    return 'material-journey-task-row__status--partial'
  }
  return 'material-journey-task-row__status--open'
})

const kindIcon = computed(() => {
  if (isCombo.value) return 'mdi-set-merge'
  return 'mdi-package-variant'
})

const qtyLabel = computed(() => {
  if (props.atEventQtyLabel) return props.atEventQtyLabel
  if (isCrate.value && props.row.isDone) {
    return t('activities.materialJourney.row.crateDone')
  }
  if (props.showMoveBack && props.row.isDone) {
    return t('activities.materialJourney.row.transportedQty', { count: props.row.doneQty })
  }
  if (props.row.isOpen) {
    return t('activities.materialJourney.row.openQty', { count: props.row.openQty })
  }
  return t('activities.materialJourney.row.doneQty', { count: props.row.doneQty })
})

const isInventoryPeekMode = computed(() => Boolean(props.atEventQtyLabel))

const isMainClickable = computed(() => {
  if (Boolean(props.packTargetSelectable)) {
    return !props.readonly
  }
  if (isInventoryPeekMode.value) {
    return !props.readonly && (isCrate.value || isCombo.value)
  }
  return (
    !props.readonly &&
    props.row.canOpenSheet &&
    (isCrate.value || isCombo.value)
  )
})

const mainActivateTitle = computed(() => {
  if (Boolean(props.packTargetSelectable)) {
    return props.packTargetActive
      ? t('activities.materialJourney.row.deselectPackTargetHint')
      : t('activities.materialJourney.row.selectPackTargetHint')
  }
  if (isMainClickable.value) {
    return t('activities.materialJourney.row.openCrateSheetHint')
  }
  return undefined
})

function badgeLabel(badge: MaterialJourneyTaskRow['badges'][number]): string {
  if (badge === 'physical_combo') return t('activities.materialJourney.badge.set')
  if (badge === 'crate') return t('activities.materialJourney.badge.crate')
  if (badge === 'pack_crate') return t('activities.materialJourney.badge.packCrate')
  if (badge === 'consumable') return t('activities.materialJourney.badge.consumable')
  return t('activities.materialJourney.badge.js')
}

function toggleExpanded(): void {
  expanded.value = !expanded.value
}

function onMainActivate(): void {
  if (!isMainClickable.value) return
  if (Boolean(props.packTargetSelectable)) {
    emit('selectTarget')
    return
  }
  if (isInventoryPeekMode.value) {
    toggleExpanded()
    return
  }
  emit('activate')
}

function onForwardMove(qty: number): void {
  emit('update:moveForwardQty', qty)
  emit('moveForward', qty)
}

function showLineIssueActions(line: MaterialJourneyAccordionLine): boolean {
  return Boolean(props.showIssueForAccordionLine?.(line))
}

function lineQtyLabel(line: MaterialJourneyAccordionLine): string {
  const custom = props.atEventQtyLabelForLine?.(line)
  if (custom) return custom
  if (isStoreStep.value) {
    const pending = storePendingForLine(line)
    if (pending > 0) {
      return t('activities.materialJourney.row.storePendingQty', { count: pending })
    }
  }
  return t('activities.packList.qtyInContainerLine', { n: line.quantity })
}

const rowIsConsumable = computed(() => {
  const mid = props.row.packItem?.materialItemId
  if (!mid) return false
  if (props.row.packItem?.isConsumable === true) return true
  return props.isConsumableForMaterialId?.(mid) === true
})

function lineIsConsumable(materialItemId: string): boolean {
  return props.isConsumableForMaterialId?.(materialItemId) === true
}

function onSelectTarget(event: Event): void {
  event.stopPropagation()
  emit('selectTarget')
}
</script>

<template>
  <div
    class="material-journey-crate-row section-card"
    :class="{
      'material-journey-crate-row--target': packTargetActive,
      'material-journey-crate-row--expanded': expanded,
      'material-journey-crate-row--combo': isCombo,
      'material-journey-crate-row--readonly': readonly,
      'material-journey-crate-row--moving': moving,
      'material-journey-crate-row--inline-actions': showMoveForwardControls || showMoveBackControls,
      'material-journey-crate-row--pack-target-selectable': packTargetSelectable,
    }"
  >
    <div class="material-journey-crate-row__header">
      <button
        v-if="packTargetSelectable"
        type="button"
        class="material-journey-crate-row__status-btn material-journey-task-row__status"
        :class="statusClass"
        :title="t('activities.materialJourney.row.selectPackTargetHint')"
        :aria-pressed="packTargetActive"
        @click="onSelectTarget"
      >
        {{ statusIcon }}
      </button>
      <span
        v-else
        class="material-journey-crate-row__status-static material-journey-task-row__status"
        :class="statusClass"
        aria-hidden="true"
      >
        {{ statusIcon }}
      </span>

      <component
        :is="isMainClickable ? 'button' : 'div'"
        type="button"
        class="material-journey-crate-row__main"
        :disabled="moving"
        :title="mainActivateTitle"
        @click="onMainActivate"
      >
        <span class="material-journey-task-row__kind-icon" aria-hidden="true">
          <v-icon :icon="kindIcon" size="20" />
        </span>
        <span class="material-journey-task-row__body">
          <span class="material-journey-task-row__title">{{ row.title }}</span>
          <span v-if="row.subtitle" class="material-journey-task-row__subtitle text-muted">
            {{ row.subtitle }}
          </span>
          <span v-if="row.badges.length" class="material-journey-task-row__badges">
            <span
              v-for="badge in row.badges"
              :key="badge"
              class="material-journey-task-row__badge"
              :class="{ 'material-journey-task-row__badge--pack-crate': badge === 'pack_crate' }"
            >
              {{ badgeLabel(badge) }}
            </span>
          </span>
          <span v-if="row.packCrateHint" class="material-journey-task-row__crate-flag">
            {{ row.packCrateHint }}
          </span>
          <span
            v-if="packTargetSelectable"
            class="material-journey-crate-row__target-badge"
            :class="{ 'material-journey-crate-row__target-badge--active': packTargetActive }"
            :aria-hidden="!packTargetActive"
          >
            {{ t('activities.packList.crateTargetBadge') }}
          </span>
        </span>
      </component>

      <div class="material-journey-task-row__trailing material-journey-crate-row__trailing" @click.stop>
        <span class="material-journey-task-row__qty">{{ qtyLabel }}</span>
        <PackIssueQuickActions
          v-if="showIssueActions && row.packItem"
          :is-consumable="rowIsConsumable"
          :material-item-id="row.packItem.materialItemId"
          :material-name="row.title"
          :show-consumption="rowIsConsumable"
          @consumed="emit('consumed')"
          @loss="emit('loss')"
          @repair="emit('repair')"
          @damage="emit('damage')"
        />
        <EButton
          v-if="showShellStoreAction"
          variant="primary"
          size="small"
          @click.stop="emit('storeShell')"
        >
          {{ t('activities.packList.storeLineTitle', { count: shellStorePendingQty }) }}
        </EButton>
        <PackMoveControls
          v-if="showMoveForwardControls"
          direction="forward"
          :into-crate="forwardIntoTour"
          :qty="effectiveMoveForwardQty"
          :max="forwardMaxQty"
          :disabled="moving"
          :forward-title="forwardMoveTitle"
          @update:qty="emit('update:moveForwardQty', $event)"
          @move="onForwardMove"
        />
        <PackMoveControls
          v-if="showMoveBackControls"
          direction="back"
          :qty="effectiveMoveBackQty"
          :max="row.maxMoveBackQty"
          :disabled="moving"
          :back-title="t('activities.materialJourney.moveBack.action')"
          @update:qty="emit('update:moveBackQty', $event)"
          @move="emit('moveBack', $event)"
        />
      </div>
    </div>

    <button
      type="button"
      class="material-journey-crate-row__content-toggle"
      :aria-expanded="expanded"
      @click="toggleExpanded"
    >
      <span
        class="material-journey-crate-row__chevron"
        :class="{ 'material-journey-crate-row__chevron--open': expanded }"
        aria-hidden="true"
      >▶</span>
      <span class="material-journey-crate-row__content-toggle-label">
        {{ t('activities.materialJourney.row.accordionToggle') }}
      </span>
      <span v-if="hasPreview" class="material-journey-crate-row__content-toggle-meta text-muted">
        {{ t('activities.materialJourney.row.accordionCount', { count: previewLineCount }) }}
      </span>
    </button>

    <div v-show="expanded" class="material-journey-crate-row__contents">
      <ul v-if="visiblePreviewLines.length > 0" class="material-journey-crate-row__content-list">
        <li
          v-for="line in visiblePreviewLines"
          :key="line.id"
          class="material-journey-crate-row__content-line"
        >
          <div class="material-journey-crate-row__content-main">
            <span class="material-journey-crate-row__content-name">{{ line.name }}</span>
          </div>
          <div
            v-if="
              showLineIssueActions(line) ||
              showLineActions(line) ||
              showStoreAction(line) ||
              showReassignForLine(line)
            "
            class="material-journey-crate-row__content-trailing"
            @click.stop
          >
            <span class="material-journey-crate-row__content-qty text-muted">
              {{ lineQtyLabel(line) }}
            </span>
            <PackIssueQuickActions
              v-if="showLineIssueActions(line) && line.materialItemId"
              :is-consumable="lineIsConsumable(line.materialItemId)"
              :material-item-id="line.materialItemId"
              :material-name="line.name"
              :show-consumption="lineIsConsumable(line.materialItemId)"
              @consumed="emit('lineConsumed', line)"
              @loss="emit('lineLoss', line)"
              @repair="emit('lineRepair', line)"
              @damage="emit('lineDamage', line)"
            />
            <EButton
              v-if="showStoreAction(line)"
              variant="primary"
              size="small"
              @click.stop="emit('storeLine', line)"
            >
              {{ t('activities.packList.storeLineTitle', { count: storePendingForLine(line) }) }}
            </EButton>
            <div v-if="showLineActions(line)" class="material-journey-crate-row__content-actions">
              <EButton variant="secondary" size="small" @click.stop="emit('looseTake', line)">
                {{ t('activities.materialJourney.row.looseTake') }}
              </EButton>
            </div>
            <label
              v-if="showReassignForLine(line)"
              class="material-journey-crate-row__reassign-field"
            >
              <span class="material-journey-crate-row__reassign-label text-muted">
                {{ t('activities.materialJourney.row.reassignCrate') }}
              </span>
              <select
                class="material-journey-crate-row__reassign-select"
                :disabled="moving"
                :aria-label="t('activities.materialJourney.reassignCrate.listAria')"
                @change="onReassignSelect(line, $event)"
              >
                <option value="" selected disabled>
                  {{ t('activities.materialJourney.row.reassignCratePlaceholder') }}
                </option>
                <option
                  v-for="target in reassignTargets"
                  :key="target.id"
                  :value="target.id"
                >
                  {{ target.label }}
                </option>
              </select>
            </label>
          </div>
          <span
            v-else
            class="material-journey-crate-row__content-qty text-muted"
          >
            {{ lineQtyLabel(line) }}
          </span>
        </li>
      </ul>
      <div v-if="warehouseTemplateLines.length > 0" class="material-journey-crate-row__warehouse-block">
        <p class="material-journey-crate-row__warehouse-title text-muted">
          {{ t('activities.materialJourney.row.warehouseTemplateSection') }}
        </p>
        <ul class="material-journey-crate-row__content-list">
          <li
            v-for="line in warehouseTemplateLines"
            :key="line.id"
            class="material-journey-crate-row__content-line material-journey-crate-row__content-line--warehouse"
          >
            <div class="material-journey-crate-row__content-main">
              <span class="material-journey-crate-row__content-name">{{ line.name }}</span>
              <span class="material-journey-crate-row__warehouse-badge">
                {{ t('activities.materialJourney.row.warehouseTemplateBadge') }}
              </span>
            </div>
            <span class="material-journey-crate-row__content-qty text-muted">
              {{ t('activities.packList.qtyInContainerLine', { n: line.quantity }) }}
            </span>
          </li>
        </ul>
      </div>
      <p v-if="!hasPreview" class="material-journey-crate-row__content-empty text-muted">
        {{
          isCrate
            ? t('activities.materialJourney.row.accordionEmptyPackCrate')
            : t('activities.materialJourney.row.accordionEmpty')
        }}
      </p>
      <div
        v-if="showDeleteEmptyCrate"
        class="material-journey-crate-row__delete-empty"
        @click.stop
      >
        <EButton
          variant="secondary"
          size="small"
          :loading="deleteEmptySubmitting"
          :disabled="deleteEmptySubmitting"
          @click="emit('deleteEmpty')"
        >
          {{ t('activities.materialJourney.row.deleteEmptyPackCrate') }}
        </EButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
