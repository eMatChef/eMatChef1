<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import MaterialJourneyStepper from '@/components/activities/materialJourney/MaterialJourneyStepper.vue'
import MaterialJourneyToolbar from '@/components/activities/materialJourney/MaterialJourneyToolbar.vue'
import MaterialJourneyTaskList from '@/components/activities/materialJourney/MaterialJourneyTaskList.vue'
import MaterialJourneyStepFooter from '@/components/activities/materialJourney/MaterialJourneyStepFooter.vue'
import MaterialJourneyLegacyLink from '@/components/activities/materialJourney/MaterialJourneyLegacyLink.vue'
import MaterialJourneyJsBanner from '@/components/activities/materialJourney/MaterialJourneyJsBanner.vue'
import MaterialAssignCrateSheet from '@/components/activities/materialJourney/MaterialAssignCrateSheet.vue'
import MaterialCrateCheckSheet from '@/components/activities/materialJourney/MaterialCrateCheckSheet.vue'
import MaterialComboCheckSheet from '@/components/activities/materialJourney/MaterialComboCheckSheet.vue'
import MaterialReturnCrateSheet from '@/components/activities/materialJourney/MaterialReturnCrateSheet.vue'
import MaterialStoreShelveSheet from '@/components/activities/materialJourney/MaterialStoreShelveSheet.vue'
import type { ReturnCrateLineEdit } from '@/components/activities/PackReturnCrateModal.vue'
import MaterialJourneyScanBar from '@/components/activities/materialJourney/MaterialJourneyScanBar.vue'
import MaterialJourneyActiveCratePanel from '@/components/activities/materialJourney/MaterialJourneyActiveCratePanel.vue'
import MaterialScanResultCard from '@/components/activities/materialJourney/MaterialScanResultCard.vue'
import MaterialReplenishmentWishPanel from '@/components/activities/materialJourney/MaterialReplenishmentWishPanel.vue'
import MaterialReplenishmentWishList from '@/components/activities/materialJourney/MaterialReplenishmentWishList.vue'
import { useMaterialJourneyScan } from '@/composables/useMaterialJourneyScan'
import { groupMaterialJourneyTasksByShelf } from '@/components/activities/materialJourneyRegalGroups'
import MaterialJourneyTransportTours from '@/components/activities/materialJourney/MaterialJourneyTransportTours.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  isJourneyTransportBackStep,
  isJourneyTransportOutStep,
} from '@/components/activities/materialJourneySteps'
import { useMaterialJourneyData } from '@/composables/useMaterialJourneyData'
import { useMaterialJourneyTasks } from '@/composables/useMaterialJourneyTasks'
import { useMaterialJourneyPresence } from '@/composables/useMaterialJourneyPresence'
import { useReplenishmentWishes } from '@/composables/useReplenishmentWishes'
import { usePackGroupIntents } from '@/composables/usePackGroupIntents'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import {
  computeMaterialJourneyJsSummary,
  showMaterialJourneyJsBanner,
} from '@/components/activities/materialJourneyJsSummary'
import { useToast } from '@/composables/useToast'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import type { ActivityPackContainer } from '@/api/activityContainers'

const props = withDefaults(
  defineProps<{
    departmentId: string
    activityId: string
    embedded?: boolean
  }>(),
  { embedded: false },
)

const route = useRoute()
const router = useRouter()
const { t, te } = useI18n()
const toast = useToast()

const stepParam = computed(() => {
  if (props.embedded) {
    const raw = route.query.packStep
    if (Array.isArray(raw)) return raw[0] ? String(raw[0]) : undefined
    return raw ? String(raw) : undefined
  }
  const raw = route.params.step
  if (Array.isArray(raw)) return raw[0]
  return raw ? String(raw) : undefined
})

const {
  activity,
  packItems,
  packContainers,
  containerItemsByContainerId,
  loading,
  error,
  profile,
  steps,
  resolvedStep,
  needsStepRedirect,
  positionCount,
  isEarlyPackPreview,
  canManageMaterials,
  reload,
  reloadSilent,
} = useMaterialJourneyData(
  toRef(props, 'departmentId'),
  toRef(props, 'activityId'),
  stepParam,
)

const {
  filterTab,
  movingId,
  packStage,
  listEditable,
  isFutureStep,
  visibleTasks,
  showByShelfFilter,
  progress,
  activateTaskRow,
  crateSheetOpen,
  comboSheetOpen,
  activeCrate,
  activeCombo,
  activeCrateShellPackItem,
  activeCrateIssueableUnits,
  activeComboMaxForwardQty,
  onCrateSheetCompleted,
  onComboSheetCompleted,
  openCrateContainer,
  openComboPackItem,
  activateLoosePackItem,
  taskRowForScanResult,
  packListCtx,
  returnCrate,
  storeShelveOpen,
  activeStoreItem,
  activeStoreMaxQty,
  storeShelveQty,
  storeShelveSubmitting,
  storeShelveFeedback,
  submitStoreShelve,
  onStoreShelveNext,
  onStoreShelveStay,
  allTasks,
  lastFailedMove,
  retryMove,
  openCrateContainerWithIntentResolve,
  assignCrateSheetOpen,
  assignCratePackItem,
  assignCrateMaxQty,
  assignCrateQty,
  assignCrateSubmitting,
  openAssignCrateSheet,
  submitAssignToCrate,
  selectedPackCrateId,
  selectedPackCrate,
  selectedPackCrateItems,
  addingPackCrate,
  selectPackCrate,
  clearSelectedPackCrate,
  submitAddScannedPackCrate,
  assignPackItemToSelectedCrate,
} = useMaterialJourneyTasks({
  activity,
  packItems,
  packContainers,
  containerItemsByContainerId,
  journeyStep: resolvedStep,
  profile,
  canManageMaterials,
  isEarlyPackPreview,
  reload,
})

const scan = useMaterialJourneyScan({
  activityId: toRef(props, 'activityId'),
  journeyStep: resolvedStep,
  listCtx: packListCtx,
  packItems,
  packContainers,
  containerItemsByContainerId,
  listEditable,
  selectedPackCrateId,
})

const {
  query: scanQuery,
  resolving: scanResolving,
  activeResult: scanResult,
  bulkConfirmed: scanBulkConfirmed,
  sessionLog: scanSessionLog,
  submitQuery,
  dismissResult,
  confirmBulkBatch,
  filterTasks,
  listTextFilterActive,
  primaryActionEnabled,
  primaryActionLabel,
  showInCrateAction,
  inCrateActionLabel,
  messageForResult,
  dismissLabelForResult,
  clearQuery,
} = scan

const showActivePackCratePanel = computed(
  () => resolvedStep.value === 'pack' && selectedPackCrate.value != null,
)

const {
  open: returnCrateOpen,
  container: returnCrateContainer,
  lines: returnCrateLines,
  partition: returnCratePartition,
  submitting: returnCrateSubmitting,
  submitDisabled: returnCrateSubmitDisabled,
  submit: submitReturnCrate,
} = returnCrate

const pollFast = computed(() => listEditable.value && movingId.value === null)
const pollIntervalMs = computed(() => (pollFast.value ? 5_000 : 20_000))
const pollEnabled = computed(() => !loading.value && !!activity.value && !error.value && !isEarlyPackPreview.value)

useBackgroundPoll({
  intervalMs: pollIntervalMs,
  enabled: pollEnabled,
  isBusy: () =>
    scanResolving.value ||
    movingId.value !== null ||
    addingPackCrate.value ||
    crateSheetOpen.value ||
    comboSheetOpen.value ||
    returnCrateOpen.value ||
    storeShelveOpen.value ||
    assignCrateSheetOpen.value,
  poll: reloadSilent,
})

const packGroup = usePackGroupIntents({
  activityId: toRef(props, 'activityId'),
  packItems,
  enabled: computed(() => resolvedStep.value === 'pack' && canManageMaterials.value),
  reload,
})

watch(
  () => [resolvedStep.value, canManageMaterials.value] as const,
  () => {
    void packGroup.loadIntents()
  },
  { immediate: true },
)

const replenishment = useReplenishmentWishes({
  activityId: toRef(props, 'activityId'),
  canManageMaterials,
  onFulfilled: reload,
})

const wishPanelRef = ref<InstanceType<typeof MaterialReplenishmentWishPanel> | null>(null)

const showReplenishmentPanel = computed(
  () => !isEarlyPackPreview.value && !canManageMaterials.value && activity.value != null,
)
const showReplenishmentQueue = computed(
  () => !isEarlyPackPreview.value && canManageMaterials.value && replenishment.pendingWishes.value.length > 0,
)

const displayedTasks = computed(() => filterTasks(visibleTasks.value))

const presenceShelf = computed(() => {
  if (filterTab.value === 'byShelf' && displayedTasks.value[0]?.shelfLabel) {
    return displayedTasks.value[0].shelfLabel
  }
  return null
})

const { presenceLabels } = useMaterialJourneyPresence({
  activityId: toRef(props, 'activityId'),
  journeyStep: resolvedStep,
  enabled: computed(() => !isEarlyPackPreview.value && !!activity.value),
  shelf: presenceShelf,
  containerId: computed(() => activeCrate.value?.id ?? null),
})

const packMultiSelect = computed(
  () => resolvedStep.value === 'pack' && canManageMaterials.value && listEditable.value,
)

function isPackRowSelected(row: { packItem?: { id: string }; kind: string }): boolean {
  if (row.kind !== 'loose' || !row.packItem) return false
  return packGroup.isSelected(row.packItem.id)
}

function onTogglePackSelect(row: { packItem?: { id: string }; kind: string }): void {
  if (row.kind !== 'loose' || !row.packItem) return
  packGroup.toggleSelection(row.packItem.id)
}

async function onGroupSelected(): Promise<void> {
  await packGroup.groupSelected()
}

async function onWishSubmit(payload: {
  materialItemId: string
  quantity: number
  notes: string | null
  availabilitySnapshot: Record<string, unknown> | null
}): Promise<void> {
  await replenishment.submitWish(payload)
  wishPanelRef.value?.resetForm()
  toast.success(t('activities.materialJourney.replenishmentWish.toastSubmitted'))
}

async function onWishFulfill(wishId: string): Promise<void> {
  await replenishment.fulfillWish(wishId)
  toast.success(t('activities.materialJourney.replenishmentWish.toastFulfilled'))
}

async function onWishReject(wishId: string): Promise<void> {
  await replenishment.rejectWish(wishId)
  toast.success(t('activities.materialJourney.replenishmentWish.toastRejected'))
}

async function onWishCancel(wishId: string): Promise<void> {
  await replenishment.cancelWish(wishId)
}

function openCrateWithIntentResolve(container: ActivityPackContainer): void {
  openCrateContainerWithIntentResolve(container, (c) => packGroup.resolveOldestIntentForContainer(c))
}

function onReturnCrateLinesUpdate(lines: ReturnCrateLineEdit[]): void {
  returnCrateLines.value = lines
}

const displayedRegalGroups = computed(() => {
  if (filterTab.value !== 'byShelf') return []
  return groupMaterialJourneyTasksByShelf(
    displayedTasks.value,
    t('activities.materialJourney.regalGroup.noShelf'),
  )
})

async function onScanSubmit(): Promise<void> {
  await submitQuery(scanQuery.value)
}

function onScanClear(): void {
  clearQuery()
}

async function handleScanAssignToSelectedCrate(
  result: NonNullable<typeof scanResult.value>,
  source: 'scan',
): Promise<void> {
  if (!result.packItem || !selectedPackCrateId.value) return
  const row = taskRowForScanResult(result)
  const maxQty = row?.maxForwardQty ?? 1
  await assignPackItemToSelectedCrate(result.packItem, maxQty, source)
  if (resolvedStep.value === 'pack' && selectedPackCrate.value) {
    await packGroup.resolveOldestIntentForContainer(selectedPackCrate.value)
  }
}

async function onScanPrimary(): Promise<void> {
  const result = scanResult.value
  if (!result || !primaryActionEnabled(result)) return

  if (result.type === 'unknown_crate') {
    const batchId = result.scannedBatchId ?? ''
    const label = result.scannedBatchLabel ?? result.title
    await submitAddScannedPackCrate(batchId, label)
    dismissResult()
    clearQuery()
    return
  }

  if (
    selectedPackCrateId.value &&
    result.packItem &&
    (result.type === 'loose_ready' || result.type === 'bulk_wrong_batch')
  ) {
    await handleScanAssignToSelectedCrate(result, 'scan')
    dismissResult()
    clearQuery()
    return
  }

  if (result.container && resolvedStep.value === 'pack' && result.type === 'crate_shell') {
    selectPackCrate(result.container.id)
    dismissResult()
    clearQuery()
    return
  }

  if (result.container) {
    const row = taskRowForScanResult(result)
    if (row) {
      onActivateTaskRow(row, 'scan')
    } else {
      openCrateWithIntentResolve(result.container)
    }
  } else if (result.packItem) {
    if (result.type === 'combo_check' || result.detail === 'text_combo' || result.type === 'in_virtual_crate') {
      openComboPackItem(result.packItem)
    } else {
      const row = taskRowForScanResult(result)
      if (row) {
        activateTaskRow(row, 'scan')
      } else {
        activateLoosePackItem(result.packItem, 'scan')
      }
    }
  }

  dismissResult()
  clearQuery()
}

function onScanInCrate(): void {
  const result = scanResult.value
  if (!result?.packItem || !showInCrateAction(result)) return
  const row = taskRowForScanResult(result)
  const maxQty = row?.maxForwardQty ?? 1
  openAssignCrateSheet(result.packItem, maxQty)
  dismissResult()
  clearQuery()
}

async function onAssignCrateConfirm(containerId: string): Promise<void> {
  await submitAssignToCrate(containerId)
  if (resolvedStep.value === 'pack') {
    const container = packContainers.value.find((c) => c.id === containerId)
    if (container) {
      await packGroup.resolveOldestIntentForContainer(container)
    }
  }
}

watch(resolvedStep, () => {
  filterTab.value = 'open'
  dismissResult()
})

const activityStatusLabel = computed(() => {
  const status = activity.value?.status
  if (!status) return ''
  const key = `activities.status.${activityStatusI18nKey(status)}` as const
  return te(key) ? t(key) : status
})

const activityStatusCss = computed(() =>
  activity.value ? activityStatusClass(activity.value.status ?? '') : '',
)

const showJsBanner = computed(() => showMaterialJourneyJsBanner(activity.value))

const jsSummary = computed(() => computeMaterialJourneyJsSummary(packItems.value))

const showTransportTours = computed(
  () =>
    isJourneyTransportOutStep(resolvedStep.value) ||
    isJourneyTransportBackStep(resolvedStep.value),
)

const transportAssignableTasks = computed(() =>
  allTasks.value.filter((row) => row.isOpen && (row.kind === 'crate' || row.kind === 'loose')),
)

function journeyRouteForStep(step: JourneyStep) {
  if (props.embedded) {
    return {
      name: 'ActivityDetail' as const,
      params: {
        departmentId: props.departmentId,
        activityId: props.activityId,
      },
      query: { tab: 'packs', packStep: step },
    }
  }
  return {
    name: 'ActivityPackJourney' as const,
    params: {
      departmentId: props.departmentId,
      activityId: props.activityId,
      step,
    },
  }
}

function mergeEmbeddedStepQuery(step: JourneyStep): void {
  if (!props.embedded) return
  const q = { ...route.query, tab: 'packs', packStep: step }
  void router.replace({ query: q })
}

function onActivateTaskRow(
  row: Parameters<typeof activateTaskRow>[0],
  source: Parameters<typeof activateTaskRow>[1] = 'tap',
): void {
  if (row.kind === 'crate' && row.container && resolvedStep.value === 'pack') {
    openCrateWithIntentResolve(row.container)
    return
  }
  activateTaskRow(row, source)
}

watch(
  [needsStepRedirect, resolvedStep, loading],
  ([redirect, step, isLoading]) => {
    if (isLoading || !activity.value) return
    if (!redirect) return
    if (props.embedded) {
      mergeEmbeddedStepQuery(step)
      return
    }
    void router.replace(journeyRouteForStep(step))
  },
  { immediate: true },
)

function onStepChange(step: JourneyStep): void {
  if (props.embedded) {
    mergeEmbeddedStepQuery(step)
    return
  }
  void router.push(journeyRouteForStep(step))
}

function goBackToActivity(): void {
  void router.push({
    name: 'ActivityDetail',
    params: {
      departmentId: props.departmentId,
      activityId: props.activityId,
    },
    query: { tab: 'packs' },
  })
}
</script>

<template>
  <div class="activity-material-journey-view" :class="{ 'activity-material-journey-view--embedded': embedded }">
    <header v-if="!embedded" class="material-journey-header">
      <EButton variant="secondary" size="small" class="material-journey-header__back" @click="goBackToActivity">
        <v-icon icon="mdi-arrow-left" start size="20" />
        {{ t('activities.detail.backToList') }}
      </EButton>
      <div v-if="activity" class="material-journey-header__title">
        <h1 class="material-journey-header__name">{{ activity.name }}</h1>
        <span class="material-journey-header__status status-label" :class="activityStatusCss">
          {{ activityStatusLabel }}
        </span>
      </div>
    </header>

    <ELoadingState
      v-if="loading"
      variant="page"
      class="material-journey-loading"
      :message="t('activities.materialJourney.loading')"
    />

    <div v-else-if="error" class="material-journey-error section-card">
      <p>{{ error }}</p>
      <EButton variant="primary" size="small" @click="reload">{{ t('common.retry') }}</EButton>
    </div>

    <template v-else-if="activity">
      <MaterialJourneyStepper
        :steps="steps"
        :current-step="resolvedStep"
        :profile="profile"
        @update:current-step="onStepChange"
      />

      <p v-if="isFutureStep" class="material-journey-readonly-banner section-card text-muted">
        {{ t('activities.materialJourney.readonlyFutureStep') }}
      </p>

      <MaterialJourneyJsBanner
        v-if="showJsBanner"
        :department-id="departmentId"
        :activity-id="activityId"
        :summary="jsSummary"
      />

      <MaterialJourneyTransportTours
        v-if="showTransportTours && !isEarlyPackPreview"
        :activity-id="activityId"
        :department-id="departmentId"
        :journey-step="resolvedStep"
        :list-editable="listEditable"
        :assignable-tasks="transportAssignableTasks"
      />

      <div v-if="!isEarlyPackPreview" class="material-journey-scan-wrap">
        <MaterialJourneyScanBar
          v-model="scanQuery"
          :loading="scanResolving || assignCrateSubmitting"
          :session-log="scanSessionLog"
          :pack-target-label="showActivePackCratePanel ? selectedPackCrate?.label ?? null : null"
          @submit="onScanSubmit"
          @clear="onScanClear"
          @deselect="clearSelectedPackCrate()"
        />

        <MaterialScanResultCard
          v-if="scanResult"
          :result="scanResult"
          :message="messageForResult(scanResult)"
          :primary-label="primaryActionLabel(scanResult)"
          :primary-enabled="primaryActionEnabled(scanResult) && !addingPackCrate"
          :dismiss-label="dismissLabelForResult(scanResult)"
          :show-bulk-confirm="Boolean(scanResult.needsBulkConfirm)"
          :bulk-confirmed="scanBulkConfirmed"
          :show-in-crate="showInCrateAction(scanResult)"
          :in-crate-label="inCrateActionLabel()"
          @primary="onScanPrimary"
          @in-crate="onScanInCrate"
          @confirm-bulk="confirmBulkBatch()"
          @dismiss="dismissResult()"
        />

        <MaterialJourneyActiveCratePanel
          v-if="showActivePackCratePanel"
          :items="selectedPackCrateItems"
        />
      </div>

      <MaterialAssignCrateSheet
        v-model:open="assignCrateSheetOpen"
        v-model:qty="assignCrateQty"
        :pack-item="assignCratePackItem"
        :containers="packContainers"
        :max-qty="assignCrateMaxQty"
        :submitting="assignCrateSubmitting"
        @confirm="onAssignCrateConfirm"
      />

      <MaterialReplenishmentWishList
        v-if="showReplenishmentQueue"
        :wishes="replenishment.pendingWishes.value"
        :submitting="replenishment.submitting.value"
        @fulfill="onWishFulfill"
        @reject="onWishReject"
      />

      <MaterialReplenishmentWishPanel
        v-if="showReplenishmentPanel"
        ref="wishPanelRef"
        :department-id="departmentId"
        :activity-id="activityId"
        :planning-start-iso="activity.planning_start ?? null"
        :planning-end-iso="activity.planning_end ?? null"
        :my-wishes="replenishment.myWishes.value"
        :submitting="replenishment.submitting.value"
        @submit="onWishSubmit"
        @cancel="onWishCancel"
      />

      <MaterialJourneyToolbar
        v-if="!isEarlyPackPreview"
        v-model:filter-tab="filterTab"
        :done-count="progress.done"
        :total-count="progress.total"
        :show-by-shelf-filter="showByShelfFilter"
        :presence-labels="presenceLabels"
      />

      <div v-if="lastFailedMove" class="material-journey-retry-banner section-card">
        <p class="text-muted">{{ t('activities.materialJourney.retryMove.hint') }}</p>
        <EButton variant="primary" size="small" :disabled="movingId !== null" @click="retryMove()">
          {{ t('activities.materialJourney.retryMove.action') }}
        </EButton>
      </div>

      <MaterialJourneyTaskList
        :tasks="displayedTasks"
        :regal-groups="displayedRegalGroups"
        :filter-tab="filterTab"
        :is-early-pack-preview="isEarlyPackPreview"
        :position-count="positionCount"
        :list-editable="listEditable"
        :moving-id="movingId"
        :pack-multi-select="packMultiSelect"
        :is-row-selected="isPackRowSelected"
        :can-group-selected="packGroup.canGroup.value"
        :grouping="packGroup.grouping.value"
        :selected-count="packGroup.selectedCount.value"
        :total-open-count="progress.open"
        :list-filter-active="listTextFilterActive"
        @activate="onActivateTaskRow"
        @toggle-select="onTogglePackSelect"
        @group-selected="onGroupSelected"
      />

      <MaterialCrateCheckSheet
        v-model:open="crateSheetOpen"
        :container="activeCrate"
        :shell-pack-item="activeCrateShellPackItem"
        :container-items-by-container-id="containerItemsByContainerId"
        :journey-step="resolvedStep"
        :pack-stage="packStage"
        :activity-id="activityId"
        :can-submit="listEditable"
        :issueable-units="activeCrateIssueableUnits"
        @completed="onCrateSheetCompleted"
      />

      <MaterialComboCheckSheet
        v-model:open="comboSheetOpen"
        :pack-item="activeCombo"
        :pack-containers="packContainers"
        :container-items-by-container-id="containerItemsByContainerId"
        :journey-step="resolvedStep"
        :pack-stage="packStage"
        :activity-id="activityId"
        :can-submit="listEditable"
        :max-forward-qty="activeComboMaxForwardQty"
        @completed="onComboSheetCompleted"
      />

      <MaterialReturnCrateSheet
        v-model:open="returnCrateOpen"
        :container-label="returnCrateContainer?.label ?? ''"
        :partition="returnCratePartition"
        :lines="returnCrateLines"
        :submitting="returnCrateSubmitting"
        :submit-disabled="returnCrateSubmitDisabled"
        @update:lines="onReturnCrateLinesUpdate"
        @submit="submitReturnCrate()"
      />

      <MaterialStoreShelveSheet
        v-model:open="storeShelveOpen"
        v-model:qty="storeShelveQty"
        :pack-item="activeStoreItem"
        :max-qty="activeStoreMaxQty"
        :department-id="departmentId"
        :submitting="storeShelveSubmitting"
        :feedback-visible="storeShelveFeedback"
        @confirm="submitStoreShelve()"
        @next="onStoreShelveNext()"
        @stay="onStoreShelveStay()"
      />

      <MaterialJourneyStepFooter
        v-if="!isEarlyPackPreview"
        :journey-step="resolvedStep"
        :done-count="progress.done"
        :total-count="progress.total"
        :open-count="progress.open"
      />

      <MaterialJourneyLegacyLink
        v-if="!embedded"
        :department-id="departmentId"
        :activity-id="activityId"
        :show-legacy-link="canManageMaterials"
      />
    </template>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
