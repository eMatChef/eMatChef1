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
import MaterialJourneyPackCompletePanel from '@/components/activities/materialJourney/MaterialJourneyPackCompletePanel.vue'
import MaterialJourneyPhaseCompletePanel from '@/components/activities/materialJourney/MaterialJourneyPhaseCompletePanel.vue'
import MaterialJourneyLegacyLink from '@/components/activities/materialJourney/MaterialJourneyLegacyLink.vue'
import MaterialAssignCrateSheet from '@/components/activities/materialJourney/MaterialAssignCrateSheet.vue'
import MaterialCrateCheckSheet from '@/components/activities/materialJourney/MaterialCrateCheckSheet.vue'
import MaterialComboCheckSheet from '@/components/activities/materialJourney/MaterialComboCheckSheet.vue'
import MaterialReturnCrateSheet from '@/components/activities/materialJourney/MaterialReturnCrateSheet.vue'
import MaterialStoreShelveSheet from '@/components/activities/materialJourney/MaterialStoreShelveSheet.vue'
import type { ReturnCrateLineEdit } from '@/components/activities/PackReturnCrateModal.vue'
import MaterialJourneyScanBar from '@/components/activities/materialJourney/MaterialJourneyScanBar.vue'
import MaterialJourneyActiveCratePanel from '@/components/activities/materialJourney/MaterialJourneyActiveCratePanel.vue'
import MaterialScanResultCard from '@/components/activities/materialJourney/MaterialScanResultCard.vue'
import MaterialScanShelfResultCard from '@/components/activities/materialJourney/MaterialScanShelfResultCard.vue'
import MaterialReplenishmentWishPanel from '@/components/activities/materialJourney/MaterialReplenishmentWishPanel.vue'
import MaterialReplenishmentWishList from '@/components/activities/materialJourney/MaterialReplenishmentWishList.vue'
import PackAddContainerModal from '@/components/activities/PackAddContainerModal.vue'
import { useMaterialJourneyScan } from '@/composables/useMaterialJourneyScan'
import { groupMaterialJourneyTasksByShelf } from '@/components/activities/materialJourneyRegalGroups'
import MaterialJourneyTransportTours from '@/components/activities/materialJourney/MaterialJourneyTransportTours.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  defaultJourneyStepForStatus,
  isJourneyTransportBackStep,
  isJourneyTransportOutStep,
} from '@/components/activities/materialJourneySteps'
import { useMaterialJourneyData } from '@/composables/useMaterialJourneyData'
import { useMaterialJourneyTasks } from '@/composables/useMaterialJourneyTasks'
import { useMaterialJourneyPackCrates } from '@/composables/useMaterialJourneyPackCrates'
import { useMaterialJourneyPresence } from '@/composables/useMaterialJourneyPresence'
import { useReplenishmentWishes } from '@/composables/useReplenishmentWishes'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import { useToast } from '@/composables/useToast'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { MaterialScanResolveResult, MaterialScanShelfLine } from '@/composables/materialScanResolve'
import { resolvePackItemShelfAction } from '@/composables/materialScanResolve'
import {
  formatPackScanProgressHint,
  formatPackScanQuantityHint,
} from '@/utils/packScanQuantityHint'
import { packItemMatchesStorageLookup } from '@/utils/packStorageLocationMatch'
import {
  getActivityTransitions,
  patchActivityPackJourneyStep,
  patchActivityStatus,
  type ActivityTransitionRow,
} from '@/api/activities'
import {
  journeyStepNeedsAdvanceConfirm,
  nextJourneyStep,
} from '@/utils/materialJourneyNavigation'

const props = withDefaults(
  defineProps<{
    departmentId: string
    activityId: string
    embedded?: boolean
    /** Kopfzeile der Aktivität — vermeidet doppelten API-Call im eingebetteten Modus */
    transitions?: ActivityTransitionRow[]
  }>(),
  { embedded: false, transitions: undefined },
)

const emit = defineEmits<{
  statusChanged: []
}>()

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
  cratePeekMaps,
  loading,
  error,
  profile,
  steps,
  resolvedStep,
  needsStepRedirect,
  activeJourneyStep,
  journeyStepWorkComplete,
  positionCount,
  isEarlyPackPreview,
  canManageMaterials,
  reload,
  reloadSilent,
  applyContainerItem,
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
  isPastStep,
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
  applyUpdatedItem,
  packIssueForwardMax,
  openCrateContainer,
  openComboPackItem,
  activateLoosePackItem,
  taskRowForScanResult,
  moveTaskRow,
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
  togglePackCrateSelection,
  clearSelectedPackCrate,
  submitAddScannedPackCrate,
  assignPackItemToSelectedCrate,
  packCrateAssignQtyForItem,
} = useMaterialJourneyTasks({
  activity,
  packItems,
  packContainers,
  containerItemsByContainerId,
  cratePeekMaps,
  journeyStep: resolvedStep,
  profile,
  canManageMaterials,
  isEarlyPackPreview,
  reload,
  reloadSilent,
  applyContainerItem,
})

const {
  showAddPackCrateButton,
  addModalOpen,
  stockBatchesLoading,
  addContainerBatchOptions,
  selectedStockBatchId,
  canSubmitAddContainer,
  containerMutationLoading,
  openAddPackCrateModal,
  submitAddPackCrate,
  confirmDeletePackContainer,
} = useMaterialJourneyPackCrates({
  departmentId: toRef(props, 'departmentId'),
  activityId: toRef(props, 'activityId'),
  packContainers,
  journeyStep: resolvedStep,
  packStage,
  profile,
  listEditable,
  canManageMaterials,
  reload,
  selectPackCrate,
  clearSelectedPackCrate,
  selectedPackCrateId,
  closeCrateSheet: () => {
    crateSheetOpen.value = false
  },
})

const scan = useMaterialJourneyScan({
  departmentId: toRef(props, 'departmentId'),
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
  activeShelfResult,
  bulkConfirmed: scanBulkConfirmed,
  sessionLog: scanSessionLog,
  submitQuery,
  dismissResult,
  dismissShelfSession,
  clearScanInput,
  confirmBulkBatch,
  filterTasks,
  listTextFilterActive,
  primaryActionEnabled,
  primaryActionLabel,
  showInCrateAction,
  inCrateActionLabel,
  messageForResult,
  messageForShelfResult,
  dismissLabelForResult,
  clearQuery,
} = scan

const shelfFocusedPackItemId = ref<string | null>(null)

const scanResolveCtx = computed(() => ({
  activityId: props.activityId,
  journeyStep: resolvedStep.value,
  listCtx: packListCtx.value,
  packItems: packItems.value,
  packContainers: packContainers.value,
  containerItemsByContainerId: containerItemsByContainerId.value,
  listEditable: listEditable.value,
}))

function packItemOnActiveShelf(packItemId: string): boolean {
  const shelf = activeShelfResult.value
  if (!shelf) return false
  return (shelf.shelfLines ?? []).some((line) => line.packItem.id === packItemId)
}

function scanResultBelongsToShelfSession(result: MaterialScanResolveResult): boolean {
  const shelf = activeShelfResult.value
  if (!shelf || !result.packItem) return false
  if (packItemOnActiveShelf(result.packItem.id)) return true
  if (shelf.storageLookup && packItemMatchesStorageLookup(result.packItem, shelf.storageLookup)) {
    return true
  }
  return false
}

const shelfInlineResult = computed((): MaterialScanResolveResult | null => {
  const shelf = activeShelfResult.value
  if (!shelf) return null

  const scan = scanResult.value
  if (scan?.packItem && scanResultBelongsToShelfSession(scan)) {
    return scan
  }

  if (shelfFocusedPackItemId.value) {
    const line = shelf.shelfLines?.find((l) => l.packItem.id === shelfFocusedPackItemId.value)
    if (line) {
      return resolvePackItemShelfAction(line.packItem, scanResolveCtx.value)
    }
  }

  return null
})

const showStandaloneScanResult = computed(
  () => scanResult.value != null && !(activeShelfResult.value && shelfInlineResult.value),
)

function clearShelfLineFocus(): void {
  shelfFocusedPackItemId.value = null
}

function onDismissShelfSession(): void {
  clearShelfLineFocus()
  dismissShelfSession()
}

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
    assignCrateSheetOpen.value ||
    addModalOpen.value ||
    containerMutationLoading.value,
  poll: reloadSilent,
})

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

function scanQuantityMoveQty(result: MaterialScanResolveResult): number {
  if (result.type !== 'loose_ready' && result.type !== 'bulk_wrong_batch') return 0
  const row = taskRowForScanResult(result)
  return row?.maxForwardQty ?? 0
}

function scanQuantityHint(result: MaterialScanResolveResult | null): string {
  if (!result?.packItem) return ''
  const moveQty = scanQuantityMoveQty(result)
  if (moveQty <= 0) return ''
  return formatPackScanQuantityHint(result.packItem, moveQty, t)
}

function scanQuantityProgress(result: MaterialScanResolveResult | null): string {
  if (!result?.packItem) return ''
  if (scanQuantityMoveQty(result) <= 0) return ''
  const row = taskRowForScanResult(result)
  if (!row) return ''
  return formatPackScanProgressHint(row.doneQty, row.openQty + row.doneQty, t)
}

async function tryAutoBookShelfScan(result: MaterialScanResolveResult): Promise<boolean> {
  const shelf = activeShelfResult.value
  if (!shelf?.storageLookup || !result.packItem) return false
  if (!packItemMatchesStorageLookup(result.packItem, shelf.storageLookup)) return false
  if (result.type === 'bulk_wrong_batch') {
    if (!scanBulkConfirmed.value) return false
  } else if (result.type !== 'loose_ready') {
    return false
  }
  if (!primaryActionEnabled(result)) return false

  if (
    selectedPackCrateId.value &&
    (result.type === 'loose_ready' || result.type === 'bulk_wrong_batch')
  ) {
    await handleScanAssignToSelectedCrate(result, 'scan')
    dismissResult()
    clearScanInput()
    return true
  }

  const row = taskRowForScanResult(result)
  if (row?.canMove) {
    await moveTaskRow(row, 'scan')
    dismissResult()
    clearScanInput()
    return true
  }

  return false
}

async function onScanSubmit(): Promise<void> {
  const result = await submitQuery(scanQuery.value)
  if (result?.type === 'shelf_location') {
    if ((result.shelfLines?.length ?? 0) > 0) filterTab.value = 'byShelf'
    return
  }
  if (activeShelfResult.value && result) {
    if (result.packItem && scanResultBelongsToShelfSession(result)) {
      shelfFocusedPackItemId.value = result.packItem.id
    }
    const booked = await tryAutoBookShelfScan(result)
    if (booked) {
      clearShelfLineFocus()
      return
    }
  }
}

function onScanShelfLineFocus(line: MaterialScanShelfLine): void {
  shelfFocusedPackItemId.value = line.packItem.id
  dismissResult()
  clearScanInput()
}

function onShelfInlineDismissLine(): void {
  clearShelfLineFocus()
  dismissResult()
}

function onScanClear(): void {
  clearScanInput()
  dismissResult()
}

async function handleScanAssignToSelectedCrate(
  result: NonNullable<typeof scanResult.value>,
  source: 'scan',
): Promise<void> {
  if (!result.packItem || !selectedPackCrateId.value) return
  const qty = packCrateAssignQtyForItem(result.packItem)
  if (qty < 1) return
  await assignPackItemToSelectedCrate(result.packItem, qty, source)
}

async function executeScanResultAction(result: MaterialScanResolveResult): Promise<void> {
  if (!primaryActionEnabled(result)) return

  if (result.type === 'unknown_crate') {
    const batchId = result.scannedBatchId ?? ''
    const label = result.scannedBatchLabel ?? result.title
    await submitAddScannedPackCrate(batchId, label)
    dismissResult()
    clearScanInput()
    clearShelfLineFocus()
    return
  }

  if (
    selectedPackCrateId.value &&
    result.packItem &&
    (result.type === 'loose_ready' || result.type === 'bulk_wrong_batch')
  ) {
    await handleScanAssignToSelectedCrate(result, 'scan')
    dismissResult()
    clearScanInput()
    clearShelfLineFocus()
    return
  }

  if (result.container && resolvedStep.value === 'pack' && result.type === 'crate_shell') {
    selectPackCrate(result.container.id)
    dismissResult()
    clearScanInput()
    clearShelfLineFocus()
    return
  }

  if (result.container) {
    const row = taskRowForScanResult(result)
    if (row) {
      onActivateTaskRow(row, 'scan')
    } else {
      openCrateContainer(result.container)
    }
  } else if (result.packItem) {
    if (result.type === 'combo_check' || result.detail === 'text_combo' || result.type === 'in_virtual_crate') {
      openComboPackItem(result.packItem)
      clearScanInput()
      return
    }
    const row = taskRowForScanResult(result)
    if (row) {
      activateTaskRow(row, 'scan')
    } else {
      activateLoosePackItem(result.packItem, 'scan')
    }
  }

  dismissResult()
  clearScanInput()
  clearShelfLineFocus()
}

async function onScanPrimary(): Promise<void> {
  const result = scanResult.value
  if (!result) return
  await executeScanResultAction(result)
}

async function onShelfInlinePrimary(): Promise<void> {
  const result = shelfInlineResult.value
  if (!result) return
  await executeScanResultAction(result)
}

function onScanInCrate(): void {
  const result = scanResult.value
  if (!result?.packItem || !showInCrateAction(result)) return
  openAssignCrateForScanResult(result)
}

function onShelfInlineInCrate(): void {
  const result = shelfInlineResult.value
  if (!result?.packItem || !showInCrateAction(result)) return
  openAssignCrateForScanResult(result)
}

function openAssignCrateForScanResult(result: MaterialScanResolveResult): void {
  const row = taskRowForScanResult(result)
  const maxQty = row?.maxForwardQty ?? 1
  openAssignCrateSheet(result.packItem!, maxQty)
  dismissResult()
  clearScanInput()
  clearShelfLineFocus()
}

async function onAssignCrateConfirm(containerId: string): Promise<void> {
  await submitAssignToCrate(containerId)
}

function onCrateDelete(): void {
  if (!activeCrate.value) return
  void confirmDeletePackContainer(activeCrate.value)
}

watch(resolvedStep, () => {
  filterTab.value = 'open'
  dismissResult()
  clearShelfLineFocus()
})

watch(comboSheetOpen, (isOpen, wasOpen) => {
  if (wasOpen && !isOpen && activeShelfResult.value) {
    clearShelfLineFocus()
    dismissResult()
  }
})

const activityStatusLabel = computed(() => {
  const status = activity.value?.status
  if (!status) return ''
  const key = `activities.status.${activityStatusI18nKey(status)}` as const
  return te(key) ? t(key) : status
})

const journeyStepBadgeLabel = computed(() => {
  const step = activeJourneyStep.value
  if (step === 'issue' && profile.value === 'logistics') {
    return t('activities.materialJourney.step.issueLogistics')
  }
  const key = `activities.materialJourney.step.${step}` as const
  return te(key) ? t(key) : step
})

const activityStatusCss = computed(() =>
  activity.value ? activityStatusClass(activity.value.status ?? '') : '',
)

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

const hasOpenPackAssignWork = computed(() =>
  visibleTasks.value.some(
    (row) =>
      row.isOpen &&
      ((row.kind === 'loose' && row.canMove && row.maxForwardQty > 0) ||
        (row.kind === 'combo' && row.canOpenSheet && row.maxForwardQty > 0)),
  ),
)

/** Grüne Zielkiste: nur solange noch lose Sets/Artikel offen sind. Danach → Kistencheck per Tap. */
const packCrateSelectMode = computed(
  () =>
    resolvedStep.value === 'pack' &&
    listEditable.value &&
    canManageMaterials.value &&
    hasOpenPackAssignWork.value,
)

watch(hasOpenPackAssignWork, (hasWork) => {
  if (!hasWork) {
    clearSelectedPackCrate()
  }
})

function onActivateTaskRow(
  row: Parameters<typeof activateTaskRow>[0],
  source: Parameters<typeof activateTaskRow>[1] = 'tap',
): void {
  if (row.kind === 'crate' && row.container && packCrateSelectMode.value) {
    togglePackCrateSelection(row.container.id)
    return
  }
  if (
    row.kind === 'loose' &&
    row.packItem &&
    packCrateSelectMode.value &&
    selectedPackCrateId.value
  ) {
    const qty = packCrateAssignQtyForItem(row.packItem)
    if (qty > 0) {
      void assignPackItemToSelectedCrate(row.packItem, qty, source)
      return
    }
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

const showPackCompletePanel = computed(
  () =>
    !isEarlyPackPreview.value &&
    resolvedStep.value === 'pack' &&
    activity.value?.status === 'packing' &&
    progress.value.total > 0 &&
    progress.value.open === 0,
)

const phaseAdvanceTarget = computed((): JourneyStep | null => {
  const step = resolvedStep.value
  if (!journeyStepNeedsAdvanceConfirm(step, profile.value)) return null
  if (step !== activeJourneyStep.value) return null
  if (!journeyStepWorkComplete.value(step)) return null
  return nextJourneyStep(step, profile.value)
})

const showPhaseCompletePanel = computed(
  () => !isEarlyPackPreview.value && phaseAdvanceTarget.value != null,
)

const showStepCompletePanel = computed(
  () => showPackCompletePanel.value || showPhaseCompletePanel.value,
)

const advancingJourneyPhase = ref(false)

const localTransitions = ref<ActivityTransitionRow[]>([])
const markingPacked = ref(false)

const effectiveTransitions = computed(() => props.transitions ?? localTransitions.value)

const packedTransition = computed(
  () => effectiveTransitions.value.find((row) => row.status === 'packed') ?? null,
)

watch(
  showPackCompletePanel,
  async (show) => {
    if (!show || props.transitions != null || !props.activityId) return
    try {
      const payload = await getActivityTransitions(props.activityId)
      localTransitions.value = payload.transitions ?? []
    } catch {
      localTransitions.value = []
    }
  },
  { immediate: true },
)

watch(
  () => props.transitions,
  (rows) => {
    if (rows != null) localTransitions.value = rows
  },
)

async function onAdvanceJourneyPhase(): Promise<void> {
  const nextStep = phaseAdvanceTarget.value
  if (!nextStep || advancingJourneyPhase.value) return
  advancingJourneyPhase.value = true
  try {
    const updated = await patchActivityPackJourneyStep(props.activityId, nextStep)
    activity.value = updated
    onStepChange(nextStep)
    emit('statusChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.materialJourney.phaseComplete.error'))
  } finally {
    advancingJourneyPhase.value = false
  }
}

async function onMarkPacked(): Promise<void> {
  const transition = packedTransition.value
  if (!transition?.allowed || markingPacked.value) return
  markingPacked.value = true
  try {
    await patchActivityStatus(props.activityId, { status: 'packed' })
    await reloadSilent()
    const status = activity.value?.status ?? 'packed'
    const nextStep = defaultJourneyStepForStatus(status, profile.value, canManageMaterials.value)
    onStepChange(nextStep)
    const statusKey = activityStatusI18nKey('packed')
    const statusLabel = te(`activities.status.${statusKey}`)
      ? t(`activities.status.${statusKey}`)
      : 'packed'
    toast.success(t('activities.detail.toastStatusChanged', { status: statusLabel }))
    emit('statusChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastStatusChangeFailed'))
  } finally {
    markingPacked.value = false
  }
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
          {{ journeyStepBadgeLabel }}
        </span>
        <span v-if="activityStatusLabel" class="material-journey-header__status-sub text-muted">
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
        :active-step="activeJourneyStep"
        :profile="profile"
        @update:current-step="onStepChange"
      />

      <p v-if="isFutureStep" class="material-journey-readonly-banner section-card text-muted">
        {{ t('activities.materialJourney.readonlyFutureStep') }}
      </p>
      <p v-else-if="isPastStep" class="material-journey-readonly-banner section-card text-muted">
        {{ t('activities.materialJourney.readonlyPastStep') }}
      </p>

      <MaterialJourneyTransportTours
        v-if="showTransportTours && !isEarlyPackPreview && !showPhaseCompletePanel"
        :activity-id="activityId"
        :department-id="departmentId"
        :journey-step="resolvedStep"
        :list-editable="listEditable"
        :assignable-tasks="transportAssignableTasks"
      />

      <MaterialJourneyPackCompletePanel
        v-if="showPackCompletePanel"
        :total-count="progress.total"
        :transition="packedTransition"
        :loading="markingPacked"
        :current-status="activity.status"
        @mark-packed="onMarkPacked()"
      />

      <MaterialJourneyPhaseCompletePanel
        v-else-if="showPhaseCompletePanel && phaseAdvanceTarget"
        :from-step="resolvedStep"
        :next-step="phaseAdvanceTarget"
        :total-count="progress.total"
        :loading="advancingJourneyPhase"
        @continue="onAdvanceJourneyPhase()"
      />

      <div v-else-if="!isEarlyPackPreview" class="material-journey-scan-wrap">
        <MaterialJourneyScanBar
          v-model="scanQuery"
          :loading="scanResolving || assignCrateSubmitting"
          :session-log="scanSessionLog"
          :pack-target-label="showActivePackCratePanel ? selectedPackCrate?.label ?? null : null"
          @submit="onScanSubmit"
          @clear="onScanClear"
          @deselect="clearSelectedPackCrate()"
        />

        <MaterialScanShelfResultCard
          v-if="activeShelfResult"
          :result="activeShelfResult"
          :message="messageForShelfResult(activeShelfResult)"
          :focused-pack-item-id="shelfFocusedPackItemId"
          :inline-result="shelfInlineResult"
          :inline-message="shelfInlineResult ? messageForResult(shelfInlineResult) : ''"
          :inline-quantity-hint="scanQuantityHint(shelfInlineResult)"
          :inline-quantity-progress="scanQuantityProgress(shelfInlineResult)"
          :inline-primary-label="shelfInlineResult ? primaryActionLabel(shelfInlineResult) : ''"
          :inline-primary-enabled="
            shelfInlineResult != null &&
            primaryActionEnabled(shelfInlineResult) &&
            !addingPackCrate
          "
          :inline-show-bulk-confirm="Boolean(shelfInlineResult?.needsBulkConfirm)"
          :inline-bulk-confirmed="scanBulkConfirmed"
          :inline-show-in-crate="shelfInlineResult ? showInCrateAction(shelfInlineResult) : false"
          :inline-in-crate-label="inCrateActionLabel()"
          @focus-line="onScanShelfLineFocus"
          @inline-primary="onShelfInlinePrimary"
          @inline-in-crate="onShelfInlineInCrate"
          @inline-confirm-bulk="confirmBulkBatch()"
          @inline-dismiss-line="onShelfInlineDismissLine"
          @dismiss="onDismissShelfSession()"
        />

        <MaterialScanResultCard
          v-if="showStandaloneScanResult && scanResult"
          :result="scanResult"
          :message="messageForResult(scanResult)"
          :quantity-hint="scanQuantityHint(scanResult)"
          :quantity-progress="scanQuantityProgress(scanResult)"
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
        v-if="!isEarlyPackPreview && !showStepCompletePanel"
        v-model:filter-tab="filterTab"
        :done-count="progress.done"
        :total-count="progress.total"
        :show-by-shelf-filter="showByShelfFilter"
        :presence-labels="presenceLabels"
        :show-add-pack-crate="showAddPackCrateButton"
        :add-pack-crate-loading="containerMutationLoading"
        @add-pack-crate="openAddPackCrateModal()"
      />

      <PackAddContainerModal
        :open="addModalOpen"
        :loading="stockBatchesLoading"
        :batches="addContainerBatchOptions"
        :selected-batch-id="selectedStockBatchId"
        :can-submit="canSubmitAddContainer"
        :submitting="containerMutationLoading"
        @update:selected-batch-id="selectedStockBatchId = $event"
        @cancel="addModalOpen = false"
        @submit="submitAddPackCrate()"
      />

      <div v-if="lastFailedMove" class="material-journey-retry-banner section-card">
        <p class="text-muted">{{ t('activities.materialJourney.retryMove.hint') }}</p>
        <EButton variant="primary" size="small" :disabled="movingId !== null" @click="retryMove()">
          {{ t('activities.materialJourney.retryMove.action') }}
        </EButton>
      </div>

      <MaterialJourneyTaskList
        v-if="!showStepCompletePanel"
        :tasks="displayedTasks"
        :regal-groups="displayedRegalGroups"
        :filter-tab="filterTab"
        :is-early-pack-preview="isEarlyPackPreview"
        :position-count="positionCount"
        :list-editable="listEditable"
        :moving-id="movingId"
        :total-open-count="progress.open"
        :list-filter-active="listTextFilterActive"
        :pack-crate-select-mode="packCrateSelectMode"
        :pack-target-crate-id="selectedPackCrateId"
        :container-items-by-container-id="containerItemsByContainerId"
        @activate="onActivateTaskRow"
      />

      <MaterialCrateCheckSheet
        v-model:open="crateSheetOpen"
        :container="activeCrate"
        :shell-pack-item="activeCrateShellPackItem"
        :pack-items="packItems"
        :pack-containers="packContainers"
        :container-items-by-container-id="containerItemsByContainerId"
        :crate-peek-maps="cratePeekMaps"
        :journey-step="resolvedStep"
        :pack-stage="packStage"
        :activity-id="activityId"
        :department-id="departmentId"
        :can-manage-materials="canManageMaterials"
        :can-submit="listEditable"
        :can-delete="listEditable"
        :deleting="containerMutationLoading"
        :issueable-units="activeCrateIssueableUnits"
        :apply-updated-item="applyUpdatedItem"
        :pack-move-qty-cap="packIssueForwardMax"
        @completed="onCrateSheetCompleted"
        @delete="onCrateDelete"
      />

      <MaterialComboCheckSheet
        v-model:open="comboSheetOpen"
        :pack-item="activeCombo"
        :pack-items="packItems"
        :pack-containers="packContainers"
        :container-items-by-container-id="containerItemsByContainerId"
        :crate-peek-maps="cratePeekMaps"
        :journey-step="resolvedStep"
        :pack-stage="packStage"
        :activity-id="activityId"
        :department-id="departmentId"
        :can-manage-materials="canManageMaterials"
        :can-submit="listEditable"
        :max-forward-qty="activeComboMaxForwardQty"
        :apply-updated-item="applyUpdatedItem"
        :pack-move-qty-cap="packIssueForwardMax"
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
        v-if="!isEarlyPackPreview && !showStepCompletePanel"
        :journey-step="resolvedStep"
        :done-count="progress.done"
        :total-count="progress.total"
        :open-count="progress.open"
      />

      <MaterialJourneyLegacyLink
        v-if="!embedded"
        :department-id="departmentId"
        :activity-id="activityId"
      />
    </template>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
