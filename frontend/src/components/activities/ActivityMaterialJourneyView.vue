<script setup lang="ts">
import { computed, provide, ref, toRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import MaterialJourneyStepper from '@/components/activities/materialJourney/MaterialJourneyStepper.vue'
import MaterialJourneyToolbar from '@/components/activities/materialJourney/MaterialJourneyToolbar.vue'
import MaterialJourneyTaskList from '@/components/activities/materialJourney/MaterialJourneyTaskList.vue'
import MaterialJourneyStepFooter from '@/components/activities/materialJourney/MaterialJourneyStepFooter.vue'
import MaterialJourneyPhaseCompletePanel from '@/components/activities/materialJourney/MaterialJourneyPhaseCompletePanel.vue'
import MaterialJourneyStoreCompletePanel from '@/components/activities/materialJourney/MaterialJourneyStoreCompletePanel.vue'
import MaterialJourneyQuickReturnHandoffBanner from '@/components/activities/materialJourney/MaterialJourneyQuickReturnHandoffBanner.vue'
import MaterialJourneyMwHandoffBanner from '@/components/activities/materialJourney/MaterialJourneyMwHandoffBanner.vue'
import MaterialJourneySelfProvidedBanner from '@/components/activities/materialJourney/MaterialJourneySelfProvidedBanner.vue'
import MaterialJourneyReturnSummaryTable from '@/components/activities/materialJourney/MaterialJourneyReturnSummaryTable.vue'
import type { QuickReturnHandoffBannerMode } from '@/components/activities/materialJourney/MaterialJourneyQuickReturnHandoffBanner.vue'
import {
  packMwHandoffBannerVisible,
  packMwIsActivityCreator,
} from '@/components/activities/packWorkflowRules'
import { useAuthStore } from '@/stores/auth'
import MaterialJourneyLegacyLink from '@/components/activities/materialJourney/MaterialJourneyLegacyLink.vue'
import MaterialAssignCrateSheet from '@/components/activities/materialJourney/MaterialAssignCrateSheet.vue'
import MaterialCrateCheckSheet from '@/components/activities/materialJourney/MaterialCrateCheckSheet.vue'
import MaterialComboCheckSheet from '@/components/activities/materialJourney/MaterialComboCheckSheet.vue'
import MaterialReturnCrateSheet from '@/components/activities/materialJourney/MaterialReturnCrateSheet.vue'
import MaterialStoreShelveSheet from '@/components/activities/materialJourney/MaterialStoreShelveSheet.vue'
import type { ReturnCrateLineEdit } from '@/components/activities/PackReturnCrateModal.vue'
import MaterialJourneyScanBar, {
  type MaterialJourneyScanSuggestion,
} from '@/components/activities/materialJourney/MaterialJourneyScanBar.vue'
import MaterialJourneyActiveCratePanel from '@/components/activities/materialJourney/MaterialJourneyActiveCratePanel.vue'
import MaterialScanResultCard from '@/components/activities/materialJourney/MaterialScanResultCard.vue'
import MaterialScanShelfResultCard from '@/components/activities/materialJourney/MaterialScanShelfResultCard.vue'
import MaterialReplenishmentWishPanel from '@/components/activities/materialJourney/MaterialReplenishmentWishPanel.vue'
import MaterialReplenishmentWishList from '@/components/activities/materialJourney/MaterialReplenishmentWishList.vue'
import PackAddContainerModal from '@/components/activities/PackAddContainerModal.vue'
import PhysicalComboIssueComponentModal from '@/components/activities/PhysicalComboIssueComponentModal.vue'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'
import { activityTransitionActionLabel } from '@/components/activities/activityTransitionLabels'
import { usePhysicalComboIssuePicker } from '@/composables/usePhysicalComboIssuePicker'
import { useMaterialJourneyScan } from '@/composables/useMaterialJourneyScan'
import { groupMaterialJourneyTasksByShelf } from '@/components/activities/materialJourneyRegalGroups'
import MaterialJourneyTransportTours from '@/components/activities/materialJourney/MaterialJourneyTransportTours.vue'
import MaterialJourneyChooseTourModal from '@/components/activities/materialJourney/MaterialJourneyChooseTourModal.vue'
import { directionForJourneyStep } from '@/api/activityTransportTours'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  activityStatusAfterJourneyStep,
  defaultJourneyStepForStatus,
  isJourneyReturnStep,
  isJourneyStoreStep,
  isJourneyTransportBackStep,
  isJourneyTransportOutStep,
  materialJourneyAllowsShelfSearch,
  materialJourneyStepI18nKey,
} from '@/components/activities/materialJourneySteps'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import {
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackReturnPipelineStage,
  isPackReturnStage,
  isPackUnpackStage,
} from '@/components/activities/packStageQuantities'
import { useMaterialJourneyData } from '@/composables/useMaterialJourneyData'
import { useMaterialJourneyTasks } from '@/composables/useMaterialJourneyTasks'
import { useMaterialJourneyPackCrates } from '@/composables/useMaterialJourneyPackCrates'
import { useMaterialJourneyTransportTourTarget } from '@/composables/useMaterialJourneyTransportTourTarget'
import { useMaterialJourneyPresence } from '@/composables/useMaterialJourneyPresence'
import { useMaterialJourneyIssueActions } from '@/composables/useMaterialJourneyIssueActions'
import { useMaterialJourneyAtEventInventoryIssues } from '@/composables/useMaterialJourneyAtEventInventoryIssues'
import { useReplenishmentWishes } from '@/composables/useReplenishmentWishes'
import type { PackIssueWizardEmitPayload } from '@/components/activities/physicalComboIssueFlow'
import type { ConsumptionModalPreset } from '@/components/activities/ActivityConsumptionModal.vue'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import {
  isMaterialJourneyCrateKind,
  materialJourneyFilterVariantForStep,
  materialJourneyTaskHasWetQty,
} from '@/components/activities/materialJourneyTaskList'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import type { ActivityPackItem, PackMoveSource } from '@/api/activityPackItems'
import type { MaterialScanResolveResult, MaterialScanShelfLine } from '@/composables/materialScanResolve'
import { resolvePackItemShelfAction } from '@/composables/materialScanResolve'
import {
  formatPackScanProgressHint,
  formatPackScanQuantityHint,
} from '@/utils/packScanQuantityHint'
import { packItemMatchesStorageLookup } from '@/utils/packStorageLocationMatch'
import {
  getActivityItems,
  getActivityTransitions,
  patchActivityStatus,
  type ActivityIssueReportRow,
  type ActivityItemRow,
  type ActivityTransitionRow,
} from '@/api/activities'
import {
  journeyStepNeedsAdvanceConfirm,
  nextJourneyStep,
} from '@/utils/materialJourneyNavigation'
import {
  buildMaterialJourneyReturnSummaryRows,
  buildReplenishmentQtyByMaterial,
} from '@/utils/materialJourneyReturnSummary'
import { consumablePhysicalReturnMax } from '@/utils/materialJourneyConsumable'
import { isJourneyStepWorkComplete } from '@/utils/materialJourneyStepWorkStatus'
import {
  crateWarehouseTemplatePeekSummary,
  isContainerBatchEmptyForPack,
} from '@/utils/materialJourneyCrateWarehousePeek'
import { transferPackedItemBetweenContainers } from '@/composables/useMaterialJourneyCrateTransfer'
import {
  computeMaterialJourneyProgressBreakdown,
  materialJourneyProgressBreakdownHasContent,
} from '@/utils/materialJourneyProgressBreakdown'
import { buildVirtualComboSelfProvidedHints } from '@/utils/virtualComboSelfProvidedHints'

const props = withDefaults(
  defineProps<{
    departmentId: string
    activityId: string
    embedded?: boolean
    /** Kopfzeile der Aktivität — vermeidet doppelten API-Call im eingebetteten Modus */
    transitions?: ActivityTransitionRow[]
    canReportIssues?: boolean
    canReportConsumption?: boolean
    /** Nachlieferung Verbrauchsmaterial (Gruppe ab «Am Event» oder MW/DC) */
    canRequestConsumableNachbuchung?: boolean
    /** Activity-Items mit is_consumable — Fallback wenn Pack-Zeile das Flag nicht trägt */
    consumableMaterialItemIds?: string[]
    /** Erhöhen wenn z. B. Packliste / Verbrauch / Meldungen geändert wurden */
    reloadToken?: number
    vehiclesReloadToken?: number
    consumptionModalCancelledToken?: number
    consumptionModalReturnWithoutConsumptionToken?: number
    /** Ersteller der Aktivität — für MW-Gruppen-Handoff (§19.1) */
    activityCreatedByUserId?: string | null
  }>(),
  {
    embedded: false,
    transitions: undefined,
    canReportIssues: true,
    canReportConsumption: true,
    canRequestConsumableNachbuchung: false,
    consumableMaterialItemIds: () => [],
    reloadToken: 0,
    vehiclesReloadToken: 0,
    consumptionModalCancelledToken: 0,
    consumptionModalReturnWithoutConsumptionToken: 0,
    activityCreatedByUserId: null,
  },
)

const emit = defineEmits<{
  statusChanged: []
  /** Kopfzeile «Gepackt markieren» — nur wenn alles gepackt (wie Toolbar). */
  packingHeaderReady: [ready: boolean]
  /** Kopfzeile «Abschliessen» — nur wenn Einlagern-Checkliste erledigt. */
  storeHeaderReady: [ready: boolean]
  openIssueWizard: [payload: PackIssueWizardEmitPayload]
  openConsumptionModal: [payload: ConsumptionModalPreset]
  requestNachbuchung: [
    payload: {
      materialItemId: string
      materialLabel: string
      packSize?: number | null
      packUnit?: string | null
      packStage?: string
    },
  ]
}>()

const route = useRoute()
const router = useRouter()
const { t, te } = useI18n()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()
const authStore = useAuthStore()

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
  journeyStepRedirectTarget,
  activeJourneyStep,
  stepsWithOpenWork,
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

const showJourneyFullLoading = computed(() => loading.value && !activity.value)

const journeyIssues = ref<ActivityIssueReportRow[]>([])
const archiveActivityItems = ref<ActivityItemRow[]>([])
const journeyActivityItems = ref<ActivityItemRow[]>([])

const isActivityCompleted = computed(() => (activity.value?.status ?? '') === 'completed')

const virtualComboSelfProvidedHints = computed(() =>
  buildVirtualComboSelfProvidedHints(journeyActivityItems.value),
)

async function loadJourneyActivityItems(): Promise<void> {
  try {
    journeyActivityItems.value = await getActivityItems(props.activityId)
  } catch {
    journeyActivityItems.value = []
  }
}

async function loadArchiveActivityItems(): Promise<void> {
  if (!isActivityCompleted.value) {
    archiveActivityItems.value = []
    return
  }
  try {
    archiveActivityItems.value = await getActivityItems(props.activityId)
  } catch {
    archiveActivityItems.value = []
  }
}

watch(
  [isActivityCompleted, () => props.activityId, () => props.reloadToken],
  () => {
    void loadArchiveActivityItems()
  },
  { immediate: true },
)

watch(
  [() => props.activityId, () => props.reloadToken],
  () => {
    void loadJourneyActivityItems()
  },
  { immediate: true },
)

const {
  filterTab,
  movingId,
  packStage,
  listEditable,
  isFutureStep,
  isPastStep,
  showMwStepEditButton,
  mwStepEditActive,
  enableMwStepEdit,
  movesEnabledForStep,
  shellPackItemForContainer,
  visibleTasks,
  showByShelfFilter,
  useRegalGroupingOnStore,
  showFilterToolbar,
  isLogisticsAtEventInventory,
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
  packQuantityCtx,
  returnCrate,
  storeShelveOpen,
  storeShelveOpenedFromScan,
  activeStoreItem,
  activeStoreContainer,
  activeStoreMaxQty,
  storeShelveQty,
  storeShelveSubmitting,
  submitStoreShelve,
  openStoreShelveForContainerLine,
  openStoreShelveForContainerShell,
  openStoreShelveFromWet,
  containerLineRemainingStore,
  containerInnerPendingStoreUnits,
  containerShellPendingStoreQty,
  containerShellOnlyPendingUnpack,
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
  issueReports,
  reloadIssues,
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
  issues: journeyIssues,
  confirmMwEmergencyForwardMove: () => confirmMwEmergencyForwardMove(),
})

const isMwActivityCreator = computed(() =>
  packMwIsActivityCreator(
    canManageMaterials.value,
    props.activityCreatedByUserId,
    authStore.userId,
  ),
)

const isGroupHandoffProfile = computed(
  () => profile.value === 'quick' || profile.value === 'logistics',
)

/** MW ≠ Ersteller, Status packed — Gruppe holt ab (§19.1). */
const showMwIssueHandoffBanner = computed(
  () =>
    !isEarlyPackPreview.value &&
    packMwHandoffBannerVisible(
      canManageMaterials.value,
      isGroupHandoffProfile.value,
      activity.value?.status ?? '',
      isMwActivityCreator.value,
    ) &&
    (activity.value?.status ?? '') === 'packed',
)

/** MW ≠ Ersteller, Status at_event — Gruppe retourniert (§19.1). */
const showMwReturnHandoffBanner = computed(
  () =>
    !isEarlyPackPreview.value &&
    packMwHandoffBannerVisible(
      canManageMaterials.value,
      isGroupHandoffProfile.value,
      activity.value?.status ?? '',
      isMwActivityCreator.value,
    ) &&
    (activity.value?.status ?? '') === 'at_event',
)

const showMwGroupHandoffBanner = computed(
  () => showMwIssueHandoffBanner.value || showMwReturnHandoffBanner.value,
)

async function confirmMwEmergencyForwardMove(): Promise<boolean> {
  if (!showMwGroupHandoffBanner.value) return true
  if (showMwReturnHandoffBanner.value) {
    return confirmDialog({
      title: t('activities.packList.mwReturnHandoffConfirmTitle'),
      message: t('activities.packList.mwReturnHandoffConfirmMessage'),
      confirmText: t('activities.packList.mwReturnHandoffConfirmProceed'),
    })
  }
  return confirmDialog({
    title: t('activities.packList.mwHandoffConfirmTitle'),
    message: t('activities.packList.mwHandoffConfirmMessage'),
    confirmText: t('activities.packList.mwHandoffConfirmProceed'),
  })
}

const canReportIssuesRef = computed(() => props.canReportIssues !== false)
const canReportConsumptionRef = computed(() => props.canReportConsumption !== false)
const consumableMaterialItemIdsSet = computed(() => {
  const ids = new Set((props.consumableMaterialItemIds ?? []).filter(Boolean))
  for (const pi of packItems.value) {
    if (pi.isConsumable && pi.materialItemId) ids.add(pi.materialItemId)
  }
  return ids
})

const physicalComboIssuePicker = usePhysicalComboIssuePicker({
  packContainers: () => packContainers.value,
  containerItemsByContainerId: () => containerItemsByContainerId.value,
  cratePeekMaps: () => cratePeekMaps.value,
  t,
  emitIssueWizard: (payload) => emit('openIssueWizard', payload),
})
const {
  open: physicalComboIssueModalOpen,
  loading: physicalComboIssueModalLoading,
  issueType: physicalComboIssueModalIssueType,
  shellPackItem: physicalComboIssueModalPi,
  sections: physicalComboIssueModalSections,
  close: closePhysicalComboIssueModal,
  onConfirm: onPhysicalComboIssueConfirm,
} = physicalComboIssuePicker

const { showIssueForRow, showIssueForAccordionLine, showIssueForPackItem, isConsumableForMaterialId } = useMaterialJourneyIssueActions({
  activity,
  packItems,
  packContainers,
  containerItemsByContainerId,
  journeyStep: resolvedStep,
  packStage,
  profile,
  packListCtx,
  canReportIssues: canReportIssuesRef,
  canReportConsumption: canReportConsumptionRef,
  consumableMaterialItemIds: consumableMaterialItemIdsSet,
  shellPackItemForContainer,
  issuedQtyInContainersForMaterial: (mid) => packListCtx.value.issuedQtyInContainersForMaterial(mid),
  containerLineRemainingStore,
  containerShellPendingStoreQty,
})

/** Quick/External: Ausgabe und Retour — Meldungen auf Positionen mit ausgegebenem Material. */
const isQuickIssueReporting = computed(
  () =>
    profile.value !== 'logistics' &&
    (resolvedStep.value === 'issue' || resolvedStep.value === 'return'),
)

const journeyFilterVariant = computed(() =>
  materialJourneyFilterVariantForStep(profile.value, resolvedStep.value),
)

const {
  atEventQtyLabelForRow,
  atEventQtyLabelForLine,
} = useMaterialJourneyAtEventInventoryIssues({
  activityId: toRef(props, 'activityId'),
  issues: journeyIssues,
  active: computed(() => {
    if (isLogisticsAtEventInventory.value || isQuickIssueReporting.value) return true
    if (profile.value === 'logistics') return false
    if (canManageMaterials.value) {
      const step = resolvedStep.value
      const status = activity.value?.status ?? ''
      // MW: Verbrauch/Verlust für Einlager-Bilanz und Retour-Checkliste
      return (
        step === 'store' ||
        step === 'return' ||
        ['returned', 'storing', 'completed'].includes(status)
      )
    }
    const status = activity.value?.status ?? ''
    return ['at_event', 'returned', 'storing', 'completed'].includes(status)
  }),
  packItems,
  containerItemsByContainerId,
  consumableMaterialItemIds: consumableMaterialItemIdsSet,
  shellPackItemForContainer,
})

function onIssueLoss(row: MaterialJourneyTaskRow): void {
  const pi = row.packItem
  if (!pi) return
  if (isPhysicalComboPackItem(pi)) {
    void physicalComboIssuePicker.tryOpenPicker(pi, 'loss')
    return
  }
  emit('openIssueWizard', { materialItemId: pi.materialItemId, issueType: 'loss' })
}

function onIssueRepair(row: MaterialJourneyTaskRow): void {
  const pi = row.packItem
  if (!pi) return
  if (isPhysicalComboPackItem(pi)) {
    void physicalComboIssuePicker.tryOpenPicker(pi, 'repair')
    return
  }
  emit('openIssueWizard', { materialItemId: pi.materialItemId, issueType: 'repair' })
}

function onIssueDamage(row: MaterialJourneyTaskRow): void {
  const pi = row.packItem
  if (!pi) return
  emit('openIssueWizard', { materialItemId: pi.materialItemId, issueType: 'damage' })
}

function onIssueConsumed(row: MaterialJourneyTaskRow): void {
  const pi = row.packItem
  if (!pi) return
  const onReturn = isJourneyReturnStep(resolvedStep.value) && pi.isConsumable
  if (onReturn) {
    const returnQty = consumablePhysicalReturnMax(pi, packQuantityCtx.value, journeyIssues.value)
    if (returnQty > 0) {
      beginConsumableReturnForPackItem(pi, returnQty)
    }
  }
  emit('openConsumptionModal', {
    materialItemId: pi.materialItemId,
    materialName: row.title,
    packSize: pi.packSize ?? null,
    packUnit: pi.packUnit ?? null,
    returnQty: onReturn
      ? consumablePhysicalReturnMax(pi, packQuantityCtx.value, journeyIssues.value) || undefined
      : undefined,
  })
}

function onIssueLossLine(_row: MaterialJourneyTaskRow, line: MaterialJourneyAccordionLine): void {
  if (!line.materialItemId) return
  emit('openIssueWizard', { materialItemId: line.materialItemId, issueType: 'loss' })
}

function onIssueRepairLine(_row: MaterialJourneyTaskRow, line: MaterialJourneyAccordionLine): void {
  if (!line.materialItemId) return
  emit('openIssueWizard', { materialItemId: line.materialItemId, issueType: 'repair' })
}

function onIssueDamageLine(_row: MaterialJourneyTaskRow, line: MaterialJourneyAccordionLine): void {
  if (!line.materialItemId) return
  emit('openIssueWizard', { materialItemId: line.materialItemId, issueType: 'damage' })
}

function onIssueConsumedLine(row: MaterialJourneyTaskRow, line: MaterialJourneyAccordionLine): void {
  if (!line.materialItemId) return
  const pi = packItems.value.find((p) => p.materialItemId === line.materialItemId)
  if (!pi) return
  const onReturn = isJourneyReturnStep(resolvedStep.value) && pi.isConsumable
  if (onReturn) {
    const returnQty = consumablePhysicalReturnMax(pi, packQuantityCtx.value, journeyIssues.value)
    if (returnQty > 0) {
      beginConsumableReturnForPackItem(pi, returnQty)
    }
  }
  emit('openConsumptionModal', {
    materialItemId: line.materialItemId,
    materialName: line.name,
    packSize: pi.packSize ?? null,
    packUnit: pi.packUnit ?? null,
    returnQty: onReturn
      ? consumablePhysicalReturnMax(pi, packQuantityCtx.value, journeyIssues.value) || undefined
      : undefined,
  })
}

function onReturnCrateReportConsumption(materialItemId: string, materialName: string): void {
  const result = reportReturnCrateConsumption(materialItemId)
  if (!result) return
  emit('openConsumptionModal', {
    materialItemId,
    materialName,
    packSize: result.packItem.packSize ?? null,
    packUnit: result.packItem.packUnit ?? null,
    linkedContainerLabel: returnCrateContainer.value?.label ?? null,
    returnQty: result.returnQty && result.returnQty > 0 ? result.returnQty : undefined,
  })
}

function onReturnCrateReportLoss(materialItemId: string, qty: number): void {
  emit('openIssueWizard', { materialItemId, issueType: 'loss', quantity: qty })
}

function onReturnCrateReportRepair(materialItemId: string, qty: number): void {
  emit('openIssueWizard', { materialItemId, issueType: 'repair', quantity: qty })
}

function onReturnCrateReportExtra(materialItemId: string, qty: number, lineId: string): void {
  void bookReturnCrateExtra(materialItemId, qty, lineId)
}

function showConsumableNachbuchungForPackItem(pi: ActivityPackItem): boolean {
  if (!pi.isConsumable || props.canRequestConsumableNachbuchung !== true) return false
  if (isPackUnpackStage(packStage.value)) return false
  const st = activity.value?.status ?? ''
  if (st === 'completed' || st === 'cancelled') return false
  if (isPackConfirmedStage(packStage.value)) return false
  return (
    isPackForwardToEventStage(packStage.value) ||
    isPackReturnPipelineStage(packStage.value) ||
    isPackReturnStage(packStage.value)
  )
}

function showConsumableNachbuchungForMaterial(materialItemId: string): boolean {
  const pi = packItems.value.find((p) => p.materialItemId === materialItemId)
  return pi ? showConsumableNachbuchungForPackItem(pi) : false
}

function emitConsumableNachbuchungForMaterial(materialItemId: string): void {
  const pi = packItems.value.find((p) => p.materialItemId === materialItemId)
  if (!pi) return
  emit('requestNachbuchung', {
    materialItemId: pi.materialItemId,
    materialLabel: pi.materialName,
    packSize: pi.packSize ?? null,
    packUnit: pi.packUnit ?? null,
    packStage: packStage.value,
  })
}

provide(PACK_WAREHOUSE_ISSUE_INJECT_KEY, {
  showConsumableNachbuchungForMaterial,
  emitConsumableNachbuchungForMaterial,
})

/** Keine Scan-, Filter- oder Positions-Aktionen wenn Schritt abgeschlossen oder nur Ansicht. */
const stepUiLocked = computed(() => {
  if (isEarlyPackPreview.value) return true
  if (canManageMaterials.value) {
    if (isFutureStep.value) return true
    const status = activity.value?.status ?? ''
    if (status === 'completed' || status === 'cancelled') return true
    return false
  }
  return (
    isFutureStep.value ||
    isPastStep.value ||
    (resolvedStep.value === activeJourneyStep.value &&
      journeyStepWorkComplete.value(resolvedStep.value))
  )
})

const effectiveListEditable = computed(() => listEditable.value && !stepUiLocked.value)

function showIssueForAccordionLineBound(
  row: MaterialJourneyTaskRow,
  line: MaterialJourneyAccordionLine,
): boolean {
  if (stepUiLocked.value) return false
  return showIssueForAccordionLine(line, row)
}

function showIssueForRowBound(row: MaterialJourneyTaskRow): boolean {
  if (stepUiLocked.value) return false
  return showIssueForRow(row)
}

function atEventQtyLabelForLineBound(
  row: MaterialJourneyTaskRow,
  line: MaterialJourneyAccordionLine,
): string | null {
  return atEventQtyLabelForLine(line, row)
}

function showIssueForScanResult(result: MaterialScanResolveResult | null | undefined): boolean {
  if (!result?.packItem || stepUiLocked.value) return false
  return showIssueForPackItem(result.packItem)
}

function onScanIssueFromResult(result: MaterialScanResolveResult, issueType: 'loss' | 'repair' | 'damage'): void {
  const pi = result.packItem
  if (!pi) return
  if (issueType !== 'damage' && isPhysicalComboPackItem(pi)) {
    void physicalComboIssuePicker.tryOpenPicker(pi, issueType)
    return
  }
  emit('openIssueWizard', { materialItemId: pi.materialItemId, issueType })
}

function onScanIssueConsumed(result: MaterialScanResolveResult): void {
  const row = taskRowForScanResult(result)
  if (row) {
    onIssueConsumed(row)
    return
  }
  const pi = result.packItem
  if (!pi) return
  const onReturn = isJourneyReturnStep(resolvedStep.value) && pi.isConsumable
  if (onReturn) {
    const returnQty = consumablePhysicalReturnMax(pi, packQuantityCtx.value, journeyIssues.value)
    if (returnQty > 0) {
      beginConsumableReturnForPackItem(pi, returnQty)
    }
  }
  emit('openConsumptionModal', {
    materialItemId: pi.materialItemId,
    materialName: result.title,
    packSize: pi.packSize ?? null,
    packUnit: pi.packUnit ?? null,
    returnQty: onReturn
      ? consumablePhysicalReturnMax(pi, packQuantityCtx.value, journeyIssues.value) || undefined
      : undefined,
  })
}

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
const deletingEmptyPackCrateId = ref<string | null>(null)

const showCrateContentActions = computed(
  () => resolvedStep.value === 'pack' && listEditable.value && canManageMaterials.value,
)

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

const scanCrateWarehouseSummary = computed(() => {
  const result = scanResult.value
  if (!result?.container || result.type !== 'crate_shell') return null
  const shell =
    shellPackItemForContainer(result.container.id) ?? result.packItem ?? null
  return crateWarehouseTemplatePeekSummary(
    result.container,
    {
      containerItemsByContainerId: containerItemsByContainerId.value,
      ...cratePeekMaps.value,
    },
    shell,
    t,
    packItems.value,
    packContainers.value,
  )
})

const scanCrateWarehouseWarning = computed(() => {
  const summary = scanCrateWarehouseSummary.value
  if (!summary) return null
  return t('activities.materialJourney.scan.crateWarehouseWarning', {
    count: summary.lineCount,
    total: summary.totalQty,
  })
})

const scanCrateWarehouseLinePreview = computed(() => {
  const summary = scanCrateWarehouseSummary.value
  if (!summary) return null
  const preview = summary.lines
    .slice(0, 5)
    .map((line) => `${line.name} (${line.qty})`)
    .join(' · ')
  if (summary.lines.length > 5) {
    return `${preview} …`
  }
  return preview
})

const scanCrateWarehouseHint = computed(() => {
  if (!scanCrateWarehouseSummary.value) return null
  return t('activities.materialJourney.scan.crateWarehouseHint')
})

const scannedBatchAllowsPackCrate = ref<boolean | null>(null)

watch(scanResult, async (result) => {
  scannedBatchAllowsPackCrate.value = null
  if (result?.type !== 'unknown_crate' || !result.scannedBatchId) return
  scannedBatchAllowsPackCrate.value = await isContainerBatchEmptyForPack(
    props.departmentId,
    props.activityId,
    result.scannedBatchId,
  )
})

const scanBlockedForPackCrate = computed(() => {
  if (scanCrateWarehouseSummary.value) return true
  if (scanResult.value?.type === 'unknown_crate') {
    return scannedBatchAllowsPackCrate.value === false
  }
  return false
})

const scanOffersMiniInventory = computed(() => {
  const result = scanResult.value
  if (!scanBlockedForPackCrate.value || !result?.container) return false
  return result.type === 'crate_shell' || result.type === 'in_crate'
})

const scanMessageResolved = computed(() => {
  const result = scanResult.value
  if (!result) return ''
  if (scanCrateWarehouseSummary.value && result.type === 'crate_shell') {
    return t('activities.materialJourney.scan.result.crate_shell_with_content')
  }
  if (result.type === 'unknown_crate' && scannedBatchAllowsPackCrate.value === false) {
    return t('activities.materialJourney.scan.result.unknown_crate_not_empty')
  }
  return messageForResult(result)
})

const scanPrimaryLabelResolved = computed(() => {
  const result = scanResult.value
  if (!result) return ''
  if (scanOffersMiniInventory.value) {
    return t('activities.materialJourney.scan.actionMiniInventory')
  }
  return primaryActionLabel(result)
})

const scanPrimaryEnabledResolved = computed(() => {
  const result = scanResult.value
  if (!result) return false
  if (scanShowWarehouseAction.value) return false
  if (scanBlockedForPackCrate.value) {
    if (scanOffersMiniInventory.value) {
      return (canManageMaterials.value || listEditable.value) && !!result.container
    }
    return false
  }
  return primaryActionEnabled(result) && !addingPackCrate.value
})

const scanShowWarehouseAction = computed(() => {
  const result = scanResult.value
  if (!scanCrateWarehouseSummary.value || !result?.container) return false
  return result.type === 'crate_shell' || result.type === 'in_crate'
})

const scanWarehouseActionEnabled = computed(
  () => scanShowWarehouseAction.value && (canManageMaterials.value || listEditable.value),
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

const scanTargetLabel = computed(() => {
  if (showActivePackCratePanel.value) return selectedPackCrate.value?.label ?? null
  if (transportTourAssignActive.value) return selectedTourLabel.value
  if (transportTourSelectMode.value && simpleTourMode.value) {
    return t('activities.materialJourney.transportTours.simpleTour')
  }
  return null
})

const selectedPackCrateHasContents = computed(() =>
  selectedPackCrateItems.value.some((item) => (item.quantity_packed ?? 0) > 0),
)

const {
  open: returnCrateOpen,
  container: returnCrateContainer,
  sheetLabel: returnCrateSheetLabel,
  lines: returnCrateLines,
  partition: returnCratePartition,
  submitting: returnCrateSubmitting,
  submitDisabled: returnCrateSubmitDisabled,
  submit: submitReturnCrate,
  fulfillPendingConsumableReturn,
  clearPendingConsumableReturn,
  beginConsumableReturnForPackItem,
  reportReturnCrateConsumption,
  bookReturnCrateExtra,
  syncLines: syncReturnCrateLines,
  pendingConsumableReturn,
} = returnCrate

const pollFast = computed(() => listEditable.value && movingId.value === null)
const pollIntervalMs = computed(() => (pollFast.value ? 5_000 : 20_000))
const pollEnabled = computed(() => !loading.value && !!activity.value && !error.value && !isEarlyPackPreview.value)

async function reloadSilentWithIssues(): Promise<void> {
  await reloadSilent()
  await reloadIssues()
}

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
  poll: reloadSilentWithIssues,
})

const replenishment = useReplenishmentWishes({
  activityId: toRef(props, 'activityId'),
  canManageMaterials,
  onFulfilled: reload,
})

const wishPanelRef = ref<InstanceType<typeof MaterialReplenishmentWishPanel> | null>(null)

const showReplenishmentPanel = computed(
  () =>
    !isEarlyPackPreview.value &&
    !canManageMaterials.value &&
    activity.value != null &&
    resolvedStep.value === 'issue' &&
    !stepUiLocked.value,
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
  const groupByShelf = filterTab.value === 'byShelf' || useRegalGroupingOnStore.value
  if (!groupByShelf) return []
  return groupMaterialJourneyTasksByShelf(
    displayedTasks.value,
    t('activities.materialJourney.regalGroup.noShelf'),
  )
})

const mwDryStoreWorkComplete = computed(
  () =>
    journeyStepWorkComplete.value('store') ||
    (resolvedStep.value === 'store' &&
      progress.value.total > 0 &&
      progress.value.open === 0),
)

/** Trockene Einlagerung fertig — Alias für Auto-Advance / Complete-CTA. */
const mwStoreWorkComplete = computed(() => mwDryStoreWorkComplete.value)

const remainingWetTaskRows = computed(() => {
  if (!canManageMaterials.value) return []
  return allTasks.value.filter((row) =>
    materialJourneyTaskHasWetQty(row, containerItemsByContainerId.value, shellPackItemForContainer),
  )
})

/** Nach Abschluss: nasse Warteschlange bleibt bedienbar bis qty_wet = 0. */
const showPostCompleteWetQueue = computed(
  () =>
    isActivityCompleted.value &&
    canManageMaterials.value &&
    remainingWetTaskRows.value.length > 0,
)

const showMwStoreCompletionReview = computed(
  () =>
    canManageMaterials.value &&
    profile.value !== 'logistics' &&
    resolvedStep.value === 'store' &&
    activeJourneyStep.value === 'store' &&
    mwDryStoreWorkComplete.value &&
    remainingWetTaskRows.value.length === 0 &&
    ['returned', 'storing'].includes(activity.value?.status ?? ''),
)

/** Trocken fertig, Nass noch offen: Complete-CTA über der Nass-Sektion. */
const showMwStoreCompleteWithWetPending = computed(
  () =>
    canManageMaterials.value &&
    profile.value !== 'logistics' &&
    resolvedStep.value === 'store' &&
    activeJourneyStep.value === 'store' &&
    mwDryStoreWorkComplete.value &&
    remainingWetTaskRows.value.length > 0 &&
    ['returned', 'storing'].includes(activity.value?.status ?? ''),
)

const storeWetTasks = computed(() => {
  if (!canManageMaterials.value) return []
  if (showPostCompleteWetQueue.value) return remainingWetTaskRows.value
  if (resolvedStep.value !== 'store' || !useRegalGroupingOnStore.value) return []
  return remainingWetTaskRows.value
})

/** Während Einlagern: oben offen, Mitte nass, unten bereits eingelagert. */
const showStoreSplitSections = computed(
  () =>
    (useRegalGroupingOnStore.value || showPostCompleteWetQueue.value) &&
    !showMwStoreCompletionReview.value &&
    (progress.value.open > 0 || storeWetTasks.value.length > 0),
)

const storeDoneTasks = computed(() =>
  showStoreSplitSections.value && !showPostCompleteWetQueue.value
    ? allTasks.value.filter((row) => row.isDone)
    : [],
)

function onActivateStoreWetTask(row: MaterialJourneyTaskRow): void {
  if (openStoreShelveFromWet(row, false)) return
  onActivateTaskRow(row, 'tap')
}

/** Retour-Liste: Regentropfen → Sheet öffnen, Nass vorausgewählt + Accordion. */
function onWetReturnFromList(row: MaterialJourneyTaskRow): void {
  if (row.kind === 'loose' && row.packItem) {
    returnCrate.openForLoose(row.packItem, Math.max(row.maxForwardQty, 0), { preselectWet: true })
    return
  }
  if (isMaterialJourneyCrateKind(row.kind) && row.container) {
    returnCrate.openFor(row.container)
    return
  }
  onActivateTaskRow(row, 'tap')
}

/** Gruppe-Retour-Abschluss / abgeschlossene Aktivität — keine Scan-Leiste/Checkliste mehr. */
const hideJourneyWorkUi = computed(
  () => showQuickGroupCompletionOnly.value || (isActivityCompleted.value && !showPostCompleteWetQueue.value),
)

/** Einlagern fertig: Scan/Hint aus; Verräum-Liste wird durch Erfolgs-Panel ersetzt. */
const hideJourneyScanOnMwStoreComplete = computed(
  () => showMwStoreCompletionReview.value || showPostCompleteWetQueue.value,
)

const showStoreShelfHint = computed(
  () =>
    !hideJourneyWorkUi.value &&
    resolvedStep.value === 'store' &&
    canManageMaterials.value &&
    !isEarlyPackPreview.value &&
    !showMwStoreCompletionReview.value &&
    !showPostCompleteWetQueue.value &&
    progress.value.total > 0,
)

const storeScanSuggestions = computed((): MaterialJourneyScanSuggestion[] => {
  if (resolvedStep.value !== 'store' || !canManageMaterials.value) return []
  const seen = new Set<string>()
  const items: MaterialJourneyScanSuggestion[] = []
  for (const row of allTasks.value) {
    if (row.kind !== 'loose' || !row.packItem || !row.canMove) continue
    const id = row.packItem.id
    if (seen.has(id)) continue
    seen.add(id)
    items.push({
      id,
      label: row.title,
      subtitle: row.subtitle,
      categoryName: row.categoryName,
    })
  }
  return items.sort((a, b) =>
    a.label.localeCompare(b.label, undefined, { sensitivity: 'base' }),
  )
})

const scanBarPlaceholderKey = computed(() => {
  if (resolvedStep.value === 'store') {
    return 'activities.materialJourney.scan.storePlaceholder'
  }
  if (materialJourneyAllowsShelfSearch(resolvedStep.value)) {
    return undefined
  }
  return 'activities.materialJourney.scan.placeholderNoShelf'
})

/** Einlagern: Position antippen → Regal-Sheet. Retour lose: Sheet mit Regentropfen (kein stiller →). */
const showLooseMoveForward = computed(
  () =>
    movesEnabledForStep.value &&
    resolvedStep.value !== 'store' &&
    !isJourneyReturnStep(resolvedStep.value) &&
    !stepUiLocked.value,
)

/** Kisten-Inhalt: «Lose mitnehmen» / «In andere Packkiste» nur beim Packen. */
const showCrateAccordionPackActions = computed(
  () => resolvedStep.value === 'pack' && movesEnabledForStep.value && !stepUiLocked.value,
)

/** Kisten-Zeile: Vorwärts-Pfeil nicht beim Einlagern. */
const showCrateMoveForwardOnRow = computed(
  () => movesEnabledForStep.value && resolvedStep.value !== 'store' && !stepUiLocked.value,
)

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

function tryAutoSelectPackCrateFromScan(result: MaterialScanResolveResult): boolean {
  if (!packCrateSelectMode.value) return false
  if (result.type !== 'crate_shell' || !result.container) return false
  if (scanCrateWarehouseSummary.value) return false
  if (!primaryActionEnabled(result)) return false
  const inList = visibleTasks.value.some(
    (row) => isMaterialJourneyCrateKind(row.kind) && row.container?.id === result.container!.id,
  )
  if (!inList) return false

  selectPackCrate(result.container.id)
  dismissResult()
  clearScanInput()
  return true
}

async function bookRowForward(
  row: MaterialJourneyTaskRow,
  source: PackMoveSource,
  qty?: number,
): Promise<boolean> {
  if (transportTourSelectMode.value) {
    movingId.value = row.id
    try {
      const moveQty = qty ?? row.maxForwardQty
      return await bookTransportRow(row, moveQty, source)
    } finally {
      movingId.value = null
    }
  }
  await moveTaskRow(row, source, qty)
  return true
}

async function tryAutoBookShelfScan(result: MaterialScanResolveResult): Promise<boolean> {
  if (resolvedStep.value === 'store') return false
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
    const booked = await bookRowForward(row, 'scan')
    if (booked) {
      dismissResult()
      clearScanInput()
      return true
    }
  }

  return false
}

function onStoreScanSuggestionSelect(suggestion: MaterialJourneyScanSuggestion): void {
  const row = allTasks.value.find(
    (r) => r.kind === 'loose' && r.packItem?.id === suggestion.id,
  )
  if (!row?.canMove) return
  clearScanInput()
  dismissResult()
  clearShelfLineFocus()
  onActivateTaskRow(row, 'tap')
}

function tryAutoOpenStoreShelveFromScan(result: MaterialScanResolveResult): boolean {
  if (resolvedStep.value !== 'store') return false
  if (!canManageMaterials.value) return false
  if (selectedPackCrateId.value) return false
  if (result.type !== 'loose_ready' && result.type !== 'bulk_wrong_batch') return false
  if (result.needsBulkConfirm && !scanBulkConfirmed.value) return false
  if (!primaryActionEnabled(result)) return false
  if (!result.packItem) return false

  const row = taskRowForScanResult(result)
  if (!row?.canMove || row.maxForwardQty < 1) return false

  onActivateTaskRow(row, 'scan')
  dismissResult()
  clearScanInput()
  return true
}

async function onScanSubmit(): Promise<void> {
  const result = await submitQuery(scanQuery.value)
  if (
    result?.type === 'shelf_location' &&
    materialJourneyAllowsShelfSearch(resolvedStep.value)
  ) {
    if ((result.shelfLines?.length ?? 0) > 0) filterTab.value = 'byShelf'
    return
  }
  if (result && tryAutoSelectPackCrateFromScan(result)) {
    clearShelfLineFocus()
    return
  }
  if (result && (await tryAutoAssignToSelectedPackCrate(result))) {
    clearShelfLineFocus()
    return
  }
  if (activeShelfResult.value && result) {
    if (result.packItem && scanResultBelongsToShelfSession(result)) {
      shelfFocusedPackItemId.value = result.packItem.id
    }
    if (tryAutoOpenStoreShelveFromScan(result)) {
      clearShelfLineFocus()
      return
    }
    const booked = await tryAutoBookShelfScan(result)
    if (booked) {
      clearShelfLineFocus()
      return
    }
  }
  if (result && tryAutoOpenStoreShelveFromScan(result)) {
    clearShelfLineFocus()
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

async function tryAutoAssignToSelectedPackCrate(
  result: MaterialScanResolveResult,
): Promise<boolean> {
  if (resolvedStep.value !== 'pack') return false
  if (!selectedPackCrateId.value || !result.packItem) return false
  if (result.type !== 'loose_ready' && result.type !== 'bulk_wrong_batch') return false
  if (result.needsBulkConfirm && !scanBulkConfirmed.value) return false
  if (!primaryActionEnabled(result)) return false
  await handleScanAssignToSelectedCrate(result, 'scan')
  dismissResult()
  clearScanInput()
  return true
}

async function openScanCrateCheck(result: MaterialScanResolveResult): Promise<void> {
  if (!result.container) return
  if (canManageMaterials.value || listEditable.value) {
    openCrateContainer(result.container)
  } else {
    const row = taskRowForScanResult(result)
    if (row) {
      onActivateTaskRow(row, 'scan')
    } else {
      openCrateContainer(result.container)
    }
  }
  dismissResult()
  clearScanInput()
  clearShelfLineFocus()
}

async function onScanWarehouseAction(): Promise<void> {
  const result = scanResult.value
  if (!result) return
  await openScanCrateCheck(result)
}

async function executeScanResultAction(result: MaterialScanResolveResult): Promise<void> {
  if (scanBlockedForPackCrate.value && result.container) {
    await openScanCrateCheck(result)
    return
  }

  if (!primaryActionEnabled(result)) return

  if (result.type === 'unknown_crate') {
    if (scannedBatchAllowsPackCrate.value === false) return
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

  if (
    result.container &&
    resolvedStep.value === 'pack' &&
    result.type === 'crate_shell' &&
    !scanBlockedForPackCrate.value
  ) {
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
    if (result.type === 'combo_check' || result.detail === 'text_combo') {
      openComboPackItem(result.packItem)
      clearScanInput()
      return
    }
    const row = taskRowForScanResult(result)
    if (row) {
      onActivateTaskRow(row, 'scan')
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

function onCrateStoreLine(
  containerId: string,
  ci: import('@/api/activityContainers').ActivityPackContainerItem,
  pi: import('@/api/activityPackItems').ActivityPackItem,
  qty: number,
): void {
  openStoreShelveForContainerLine(containerId, ci, pi, qty)
}

function onCrateStoreShell(
  containerId: string,
  pi: import('@/api/activityPackItems').ActivityPackItem,
  qty: number,
): void {
  openStoreShelveForContainerShell(containerId, pi, qty)
}

function onAccordionStoreLine(
  row: MaterialJourneyTaskRow,
  line: MaterialJourneyAccordionLine,
): void {
  const containerId = row.container?.id
  if (!containerId || !line.materialItemId) return
  const ci = containerItemsByContainerId.value[containerId]?.find(
    (item) => item.id === line.id || item.material_item_id === line.materialItemId,
  )
  if (!ci) return
  const pi = packItems.value.find((p) => p.materialItemId === line.materialItemId)
  if (!pi) return
  const max = containerLineRemainingStore(ci)
  if (max < 1) return
  openStoreShelveForContainerLine(containerId, ci, pi, max)
}

function shellStorePendingQtyForRow(row: MaterialJourneyTaskRow): number {
  if (!isMaterialJourneyCrateKind(row.kind) || !row.container) return 0
  if (!containerShellOnlyPendingUnpack(row.container.id)) return 0
  return containerShellPendingStoreQty(row.container.id)
}

function onCrateShellStore(row: MaterialJourneyTaskRow): void {
  const containerId = row.container?.id
  if (!containerId) return
  const shell = shellPackItemForContainer(containerId)
  const qty = containerShellPendingStoreQty(containerId)
  if (!shell || qty < 1) return
  openStoreShelveForContainerShell(containerId, shell, qty)
}

function deleteEmptySubmittingForRow(row: MaterialJourneyTaskRow): boolean {
  return (
    containerMutationLoading.value &&
    deletingEmptyPackCrateId.value === row.container?.id
  )
}

async function onDeleteEmptyPackCrate(row: MaterialJourneyTaskRow): Promise<void> {
  if (!row.container) return
  deletingEmptyPackCrateId.value = row.container.id
  try {
    await confirmDeletePackContainer(row.container)
  } finally {
    deletingEmptyPackCrateId.value = null
  }
}

async function onReassignCrateLine(
  row: MaterialJourneyTaskRow,
  line: MaterialJourneyAccordionLine,
  targetContainerId: string,
): Promise<void> {
  const activityId = props.activityId
  const sourceContainerId = row.container?.id
  if (!activityId || !sourceContainerId || !targetContainerId.trim()) return

  const qty = line.maxReassignQty ?? line.quantity
  if (qty < 1) return

  movingId.value = row.id
  try {
    await transferPackedItemBetweenContainers(
      activityId,
      sourceContainerId,
      targetContainerId,
      line.id,
      containerItemsByContainerId.value,
      qty,
    )
    await reloadSilent()
    toast.success(
      t('activities.materialJourney.reassignCrate.toastSuccessItemQty', {
        name: line.name,
        count: qty,
      }),
    )
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : String(err)
    toast.error(message || t('activities.materialJourney.reassignCrate.toastFailed'))
  } finally {
    movingId.value = null
  }
}

watch(resolvedStep, () => {
  dismissResult()
  clearShelfLineFocus()
})

watch(comboSheetOpen, (isOpen, wasOpen) => {
  if (wasOpen && !isOpen && activeShelfResult.value) {
    clearShelfLineFocus()
    dismissResult()
  }
})

const journeyStepBadgeLabel = computed(() => {
  const step = activeJourneyStep.value
  const key = materialJourneyStepI18nKey(step, profile.value, {
    activityStatus: activity.value?.status ?? '',
    issueDoneCount: progress.value.done,
  })
  return te(key) ? t(key) : step
})

const showQuickBringBackButton = computed(
  () =>
    profile.value !== 'logistics' &&
    journeyFilterVariant.value === 'quickIssue' &&
    resolvedStep.value === 'issue' &&
    (progress.value.done > 0 ||
      activity.value?.status === 'at_event' ||
      activity.value?.status === 'returned'),
)

const quickBringBackDisabled = computed(() => {
  const status = activity.value?.status ?? ''
  if (status === 'at_event' || status === 'returned') return false
  return progress.value.open > 0
})

const quickBringBackDisabledHint = computed(() => {
  if (progress.value.done <= 0) {
    return t('activities.materialJourney.quickPhase.returnDisabledNoIssue')
  }
  return t('activities.materialJourney.quickPhase.returnDisabledHint')
})

function onQuickBringBack(): void {
  if (quickBringBackDisabled.value) return
  onStepChange('return')
}

const activityStatusCss = computed(() =>
  activity.value ? activityStatusClass(activity.value.status ?? '') : '',
)

const showTransportTours = computed(
  () => directionForJourneyStep(resolvedStep.value) != null,
)

const showTransportWeightReview = computed(
  () => resolvedStep.value === 'return' && canManageMaterials.value,
)

const transportAssignableTasks = computed(() =>
  allTasks.value.filter((row) => row.isOpen && (isMaterialJourneyCrateKind(row.kind) || row.kind === 'loose')),
)

const transportToursRef = ref<InstanceType<typeof MaterialJourneyTransportTours> | null>(null)

const {
  selectedTourId,
  simpleTourMode,
  tours: planTransportTours,
  activityVehicles: planActivityVehicles,
  chooseTourModalOpen,
  assignTourSubmitting,
  transportTourSelectMode,
  transportTourAssignActive,
  selectedTourLabel,
  loadToursAndVehicles,
  selectSimpleTour,
  selectVehicleTarget,
  toggleTourSelection,
  cancelChooseTourModal,
  bookTransportRow,
} = useMaterialJourneyTransportTourTarget({
  activityId: toRef(props, 'activityId'),
  journeyStep: resolvedStep,
  listEditable,
  canManageMaterials,
  assignableTasks: transportAssignableTasks,
  packStage,
  shellPackItemForContainer,
  applyUpdatedItem,
  reload,
})

async function onTransportPipelineChanged(): Promise<void> {
  await reloadSilentWithIssues()
  await loadToursAndVehicles()
  await transportToursRef.value?.loadAll?.()
}

function onChooseTourModalOpenChange(open: boolean): void {
  chooseTourModalOpen.value = open
  if (!open) cancelChooseTourModal()
}

watch(
  () => props.vehiclesReloadToken ?? 0,
  () => {
    void loadToursAndVehicles()
    void transportToursRef.value?.loadAll?.()
  },
)

async function afterConsumptionModalChange(): Promise<void> {
  await reloadSilentWithIssues()
  if (pendingConsumableReturn.value) {
    await fulfillPendingConsumableReturn()
  } else if (returnCrateOpen.value) {
    syncReturnCrateLines()
  }
}

watch(
  () => props.reloadToken ?? 0,
  async (token, prev) => {
    if (token !== prev && token > 0) {
      await afterConsumptionModalChange()
    }
  },
)

watch(
  () => props.consumptionModalReturnWithoutConsumptionToken ?? 0,
  async (token, prev) => {
    if (token !== prev && token > 0 && pendingConsumableReturn.value) {
      await afterConsumptionModalChange()
    }
  },
)

watch(
  () => props.consumptionModalCancelledToken ?? 0,
  (token, prev) => {
    if (token !== prev && token > 0 && pendingConsumableReturn.value) {
      clearPendingConsumableReturn()
    }
  },
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

function onSelectPackTarget(row: MaterialJourneyTaskRow): void {
  if (!isMaterialJourneyCrateKind(row.kind) || !row.container) return
  if (packCrateSelectMode.value) {
    togglePackCrateSelection(row.container.id)
    return
  }
  if (selectedPackCrateId.value === row.container.id) {
    clearSelectedPackCrate()
  }
}

function onActivateTaskRow(
  row: Parameters<typeof activateTaskRow>[0],
  source: Parameters<typeof activateTaskRow>[1] = 'tap',
): void {
  if (isMaterialJourneyCrateKind(row.kind) && row.container && packCrateSelectMode.value) {
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
  if (transportTourSelectMode.value && row.kind === 'loose' && row.canMove) {
    void bookRowForward(row, source)
    return
  }
  activateTaskRow(row, source)
}

/** Grüner Pfeil → Kistencheck nur in Schritten, wo die Packliste das auch tut (nicht Transport). */
function shouldOpenCheckSheetOnMoveForward(row: MaterialJourneyTaskRow): boolean {
  if (!row.canOpenSheet) return false
  if (transportTourSelectMode.value) return false

  const step = resolvedStep.value

  if (row.kind === 'combo') {
    return (
      step === 'pack' ||
      step === 'issue' ||
      isJourneyReturnStep(step) ||
      isJourneyStoreStep(step)
    )
  }

  if (isMaterialJourneyCrateKind(row.kind)) {
    if (step === 'pack') return false
    if (isJourneyTransportOutStep(step) || isJourneyTransportBackStep(step)) return false
    return step === 'issue' || isJourneyReturnStep(step) || isJourneyStoreStep(step)
  }

  return false
}

function onMoveForwardTask(row: MaterialJourneyTaskRow, qty: number): void {
  if (isMaterialJourneyCrateKind(row.kind) || row.kind === 'combo') {
    activateTaskRow(row, 'tap')
    return
  }
  if (
    resolvedStep.value === 'store' &&
    row.kind === 'loose' &&
    row.packItem &&
    row.canMove
  ) {
    activateTaskRow(row, 'tap')
    return
  }
  if (isJourneyReturnStep(resolvedStep.value) && row.kind === 'loose' && row.packItem) {
    activateTaskRow(row, 'tap')
    return
  }
  if (
    row.kind === 'loose' &&
    row.packItem &&
    packCrateSelectMode.value &&
    selectedPackCrateId.value
  ) {
    void assignPackItemToSelectedCrate(row.packItem, qty, 'tap')
    return
  }
  // Phys.-Kombi / Packkiste: Kistencheck nur in passenden Schritten (Transport → Tour buchen)
  if (shouldOpenCheckSheetOnMoveForward(row)) {
    activateTaskRow(row, 'tap')
    return
  }
  void bookRowForward(row, 'tap', qty)
}

watch(
  [needsStepRedirect, journeyStepRedirectTarget, loading],
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
  return nextJourneyStep(step, profile.value, canManageMaterials.value)
})

/** Tatsächliches Status-Ziel (Logistics: packed → zuerst transport_out). */
const phaseAdvanceStatusTarget = computed((): string | null => {
  if (phaseAdvanceTarget.value == null) return null
  return activityStatusAfterJourneyStep(
    resolvedStep.value,
    profile.value,
    activity.value?.status,
  )
})

/** Wenn zuerst nur Status «Transport hin» gesetzt wird, Label nicht «Am Anlass». */
const phaseAdvanceContinueLabel = computed((): string | null => {
  if (phaseAdvanceStatusTarget.value !== 'transport_out') return null
  return t('activities.detail.transitionActions.transport_out')
})

const showPhaseCompletePanel = computed(
  () =>
    !isEarlyPackPreview.value &&
    phaseAdvanceTarget.value != null &&
    resolvedStep.value !== 'issue',
)

const showIssueReturnMaterialButton = computed(
  () =>
    isLogisticsAtEventInventory.value &&
    resolvedStep.value === activeJourneyStep.value &&
    phaseAdvanceTarget.value === 'transport_back',
)

const showStepCompletePanel = computed(() => showPhaseCompletePanel.value)

const advancingJourneyPhase = ref(false)

const localTransitions = ref<ActivityTransitionRow[]>([])
const markingPacked = ref(false)

const effectiveTransitions = computed(() => props.transitions ?? localTransitions.value)

const atEventTransition = computed(
  () => effectiveTransitions.value.find((row) => row.status === 'at_event') ?? null,
)

const partialTakenToEventInProgress = ref(false)

const showPartialTakenToEventButton = computed(
  () =>
    profile.value !== 'logistics' &&
    resolvedStep.value === 'issue' &&
    listEditable.value &&
    activity.value?.status === 'packed' &&
    progress.value.done > 0 &&
    progress.value.open > 0,
)

const partialTakenToEventTitle = computed(() =>
  canManageMaterials.value
    ? t('activities.materialJourney.partialTaken.titleMw')
    : t('activities.materialJourney.partialTaken.title'),
)

const progressBreakdownLabel = computed((): string | null => {
  if (profile.value === 'logistics' || isEarlyPackPreview.value) return null
  const step = resolvedStep.value
  if (step !== 'issue' && step !== 'return' && step !== 'transport_back') return null
  const mode =
    step === 'issue' && journeyFilterVariant.value === 'quickIssue' && filterTab.value === 'done'
      ? 'done'
      : 'open'
  const breakdown = computeMaterialJourneyProgressBreakdown(allTasks.value, mode)
  if (!materialJourneyProgressBreakdownHasContent(breakdown)) return null
  const parts: string[] = []
  if (breakdown.crates > 0) {
    parts.push(t('activities.materialJourney.progressBreakdown.crates', { count: breakdown.crates }))
  }
  if (breakdown.consumables > 0) {
    parts.push(
      t('activities.materialJourney.progressBreakdown.consumables', { count: breakdown.consumables }),
    )
  }
  if (breakdown.loose > 0) {
    parts.push(t('activities.materialJourney.progressBreakdown.loose', { count: breakdown.loose }))
  }
  return parts.length > 0 ? parts.join(' · ') : null
})

async function onPartialTakenToEvent(): Promise<void> {
  if (partialTakenToEventInProgress.value || !showPartialTakenToEventButton.value) return
  const transition = atEventTransition.value
  if (transition && !transition.allowed) {
    toast.error(transition.reason || t('activities.detail.toastStatusChangeFailed'))
    return
  }
  const ok = await confirmDialog({
    title: partialTakenToEventTitle.value,
    message: canManageMaterials.value
      ? t('activities.materialJourney.partialTaken.confirmMw')
      : t('activities.materialJourney.partialTaken.confirm'),
    confirmText: t('activities.materialJourney.partialTaken.action'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
  if (!ok) return

  partialTakenToEventInProgress.value = true
  try {
    await patchActivityStatus(props.activityId, { status: 'at_event' })
    await reloadSilent()
    emit('statusChanged')
    toast.success(t('activities.materialJourney.partialTaken.toastSuccess'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastStatusChangeFailed'))
  } finally {
    partialTakenToEventInProgress.value = false
  }
}

const quickIssueAllIssued = computed(
  () =>
    isQuickIssueReporting.value &&
    progress.value.total > 0 &&
    progress.value.open === 0,
)

const hideIssueScanBar = computed(
  () =>
    showPackCompletePanel.value ||
    stepUiLocked.value ||
    (isQuickIssueReporting.value &&
      resolvedStep.value === 'issue' &&
      (quickIssueAllIssued.value || filterTab.value === 'done')),
)

const quickReturnHandoffInProgress = ref(false)

/** Retour-Checkliste fertig — gleiche Sicht wie 5/5 in der Toolbar (inkl. Verbrauch/Issues). */
const quickReturnWorkReady = computed(() => {
  if (
    isJourneyStepWorkComplete(
      'return',
      profile.value,
      packItems.value,
      packContainers.value,
      containerItemsByContainerId.value,
      journeyIssues.value,
    )
  ) {
    return true
  }
  return progress.value.total > 0 && progress.value.open === 0
})

const quickReturnHandoffBannerMode = computed((): QuickReturnHandoffBannerMode | null => {
  if (isEarlyPackPreview.value) return null
  const status = activity.value?.status ?? ''
  if (!quickReturnWorkReady.value) return null

  if (canManageMaterials.value) {
    // MW: Retour fertig → explizit «Einlagern starten» (Quick + Logistics)
    if (status === 'returned') return 'storeForMw'
    if (profile.value !== 'logistics' && status === 'at_event' && resolvedStep.value === 'return') {
      return 'storeForMw'
    }
    // Logistics: Mengen schon retour (z. B. Bulk), Status noch transport_back
    if (
      profile.value === 'logistics' &&
      status === 'transport_back' &&
      (resolvedStep.value === 'transport_back' || resolvedStep.value === 'return')
    ) {
      return 'storeForMw'
    }
    return null
  }

  // Gruppe: Handoff nur Quick/External — Logistics ohne Gruppen-Retour-Banner
  if (profile.value === 'logistics') return null
  if (status === 'at_event') return 'handoff'
  if (['returned', 'storing', 'completed'].includes(status)) return 'handoffDone'
  return null
})

/** Gruppe: Retour erledigt — nur Abschluss-Banner, keine Checkliste/Stepper mehr. */
const showQuickGroupCompletionOnly = computed(
  () =>
    !canManageMaterials.value &&
    profile.value !== 'logistics' &&
    (quickReturnHandoffBannerMode.value === 'handoff' ||
      quickReturnHandoffBannerMode.value === 'handoffDone'),
)

/** Abgeschlossen: Material als Übersichtstabelle (statt operativer Packliste). */
const showCompletedMaterialArchive = computed(
  () => isActivityCompleted.value && !showPostCompleteWetQueue.value,
)

const materialSummaryMode = computed(() =>
  showCompletedMaterialArchive.value ? 'archive' : 'return',
)

const replenishmentByMaterial = computed(() =>
  buildReplenishmentQtyByMaterial(archiveActivityItems.value),
)

const returnSummaryRows = computed(() =>
  buildMaterialJourneyReturnSummaryRows(
    packItems.value,
    journeyIssues.value,
    consumableMaterialItemIdsSet.value,
    packContainers.value,
    containerItemsByContainerId.value,
    {
      mode: materialSummaryMode.value,
      replenishmentByMaterial: showCompletedMaterialArchive.value
        ? replenishmentByMaterial.value
        : undefined,
    },
  ),
)

const showMaterialSummaryTable = computed(
  () =>
    returnSummaryRows.value.length > 0 &&
    (showQuickGroupCompletionOnly.value || showCompletedMaterialArchive.value),
)

const returnedTransition = computed(
  () => effectiveTransitions.value.find((row) => row.status === 'returned') ?? null,
)

const storingTransition = computed(
  () => effectiveTransitions.value.find((row) => row.status === 'storing') ?? null,
)

const quickReturnHandoffDisabled = computed(() => {
  if (quickReturnHandoffInProgress.value) return true
  const tr = returnedTransition.value
  if (tr && !tr.allowed) return true
  return false
})

const quickStartStoringDisabled = computed(() => {
  if (quickReturnHandoffInProgress.value) return true
  const status = activity.value?.status ?? ''
  if (status === 'at_event' || status === 'transport_back') {
    const tr = returnedTransition.value
    if (tr && !tr.allowed) return true
    return false
  }
  const tr = storingTransition.value
  if (tr && !tr.allowed) return true
  return false
})

const quickReturnHandoffBannerDisabled = computed(() => {
  const mode = quickReturnHandoffBannerMode.value
  if (mode === 'handoff') return quickReturnHandoffDisabled.value
  if (mode === 'storeForMw') return quickStartStoringDisabled.value
  return false
})

async function onQuickReturnHandoff(): Promise<void> {
  if (quickReturnHandoffInProgress.value || quickReturnHandoffDisabled.value) return
  if (activity.value?.status !== 'at_event') return
  quickReturnHandoffInProgress.value = true
  try {
    await patchActivityStatus(props.activityId, { status: 'returned' })
    await reloadSilent()
    emit('statusChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.materialJourney.quickReturnHandoff.error'))
  } finally {
    quickReturnHandoffInProgress.value = false
  }
}

async function onQuickStartStoring(): Promise<void> {
  if (quickReturnHandoffInProgress.value || quickStartStoringDisabled.value) return
  const status = activity.value?.status ?? ''
  if (status !== 'returned' && status !== 'at_event' && status !== 'transport_back') return
  quickReturnHandoffInProgress.value = true
  try {
    if (status === 'at_event' || status === 'transport_back') {
      await patchActivityStatus(props.activityId, { status: 'returned' })
      await reloadSilent()
      emit('statusChanged')
    }
    if ((activity.value?.status ?? '') === 'returned') {
      await patchActivityStatus(props.activityId, { status: 'storing' })
      await reloadSilent()
      onStepChange('store')
      emit('statusChanged')
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastStatusChangeFailed'))
  } finally {
    quickReturnHandoffInProgress.value = false
  }
}

const quickStoringAutoAdvancing = ref(false)

/** Nach letztem Einlagern: Status «Einlagern» setzen, damit Materialabschluss erscheint. */
async function maybeAutoAdvanceToStoring(): Promise<void> {
  if (profile.value === 'logistics' || !canManageMaterials.value) return
  if (!activity.value || activity.value.status !== 'returned') return
  if (resolvedStep.value !== 'store' || activeJourneyStep.value !== 'store') return
  if (!journeyStepWorkComplete.value('store') && !mwStoreWorkComplete.value) return
  if (quickStoringAutoAdvancing.value || quickReturnHandoffInProgress.value) return
  quickStoringAutoAdvancing.value = true
  try {
    await onQuickStartStoring()
  } finally {
    quickStoringAutoAdvancing.value = false
  }
}

function onQuickReturnHandoffBannerAction(): void {
  const mode = quickReturnHandoffBannerMode.value
  if (mode === 'handoff') void onQuickReturnHandoff()
  else if (mode === 'storeForMw') void onQuickStartStoring()
}

const quickIssueAutoAdvancing = ref(false)

async function maybeAutoAdvanceQuickIssueToAtEvent(): Promise<void> {
  if (profile.value === 'logistics') return
  if (resolvedStep.value !== 'issue') return
  if (!activity.value || activity.value.status !== 'packed') return
  if (activeJourneyStep.value !== 'issue') return
  if (!journeyStepWorkComplete.value('issue')) return
  if (quickIssueAutoAdvancing.value) return

  quickIssueAutoAdvancing.value = true
  try {
    await patchActivityStatus(props.activityId, { status: 'at_event' })
    await reloadSilent()
    emit('statusChanged')
  } catch {
    /* Kopfzeile zeigt ggf. manuellen Übergang */
  } finally {
    quickIssueAutoAdvancing.value = false
  }
}

watch(
  [
    () => packItems.value.length,
    () => activity.value?.status,
    activeJourneyStep,
    () => journeyStepWorkComplete.value('issue'),
    () => progress.value.open,
  ],
  () => {
    void maybeAutoAdvanceQuickIssueToAtEvent()
  },
  { immediate: true },
)

const packedTransition = computed(
  () => effectiveTransitions.value.find((row) => row.status === 'packed') ?? null,
)

const markPackedButtonLabel = computed(() => {
  const transition = packedTransition.value
  if (!transition) return t('activities.transitionActions.packed')
  return activityTransitionActionLabel(
    transition.status,
    activity.value?.status ?? null,
    t,
    te,
    transition.label,
  )
})

const markPackedActionDisabled = computed(
  () => !packedTransition.value?.allowed || markingPacked.value,
)

const markPackedHint = computed(() => {
  if (packedTransition.value?.allowed) {
    return t('activities.materialJourney.packComplete.markPackedHint')
  }
  if (packedTransition.value?.reason) return packedTransition.value.reason
  return t('activities.materialJourney.packComplete.noPermission')
})

watch(
  showPackCompletePanel,
  async (show) => {
    emit('packingHeaderReady', show)
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
  showMwStoreCompletionReview,
  async (show) => {
    emit('storeHeaderReady', show)
    if (show) {
      filterTab.value = 'done'
      emit('statusChanged')
      if (props.transitions != null || !props.activityId) return
      try {
        const payload = await getActivityTransitions(props.activityId)
        localTransitions.value = payload.transitions ?? []
      } catch {
        localTransitions.value = []
      }
    }
  },
  { immediate: true },
)

watch(
  [resolvedStep, () => activity.value?.status, showPartialTakenToEventButton],
  async ([step, , showPartial]) => {
    if (props.transitions != null || !props.activityId || isEarlyPackPreview.value) return
    if (step !== 'issue' && step !== 'return') return
    if (step === 'issue' && !showPartial && !showPackCompletePanel.value) return
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
  () => [
    mwStoreWorkComplete.value,
    activity.value?.status,
    packItems.value.length,
  ] as const,
  () => {
    void maybeAutoAdvanceToStoring()
  },
)

watch(
  () => props.transitions,
  (rows) => {
    if (rows != null) localTransitions.value = rows
  },
)

async function onAdvanceJourneyPhase(): Promise<void> {
  const currentStep = resolvedStep.value
  const nextStatus = activityStatusAfterJourneyStep(
    currentStep,
    profile.value,
    activity.value?.status,
  )
  if (!nextStatus || advancingJourneyPhase.value) return

  if (
    showMwGroupHandoffBanner.value &&
    (nextStatus === 'at_event' || nextStatus === 'returned' || nextStatus === 'transport_out')
  ) {
    const ok = await confirmMwEmergencyForwardMove()
    if (!ok) return
  }

  advancingJourneyPhase.value = true
  try {
    await patchActivityStatus(props.activityId, { status: nextStatus })
    await reloadSilent()
    const nextStep = defaultJourneyStepForStatus(
      activity.value?.status ?? nextStatus,
      profile.value,
      canManageMaterials.value,
    )
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

const completingStoreActivity = ref(false)

const completedTransition = computed(
  () => effectiveTransitions.value.find((row) => row.status === 'completed') ?? null,
)

async function onCompleteStoreActivity(): Promise<void> {
  if (completingStoreActivity.value) return
  const transition = completedTransition.value
  if (transition && !transition.allowed) {
    toast.error(transition.reason || t('activities.detail.toastStatusChangeFailed'))
    return
  }
  completingStoreActivity.value = true
  try {
    if ((activity.value?.status ?? '') === 'returned') {
      await patchActivityStatus(props.activityId, { status: 'storing' })
    }
    await patchActivityStatus(props.activityId, { status: 'completed' })
    await reloadSilent()
    const statusKey = activityStatusI18nKey('completed')
    const statusLabel = te(`activities.status.${statusKey}`)
      ? t(`activities.status.${statusKey}`)
      : 'completed'
    toast.success(t('activities.detail.toastStatusChanged', { status: statusLabel }))
    emit('statusChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastStatusChangeFailed'))
  } finally {
    completingStoreActivity.value = false
  }
}

defineExpose({
  showPackCompletePanel,
  markPacked: onMarkPacked,
})
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
      </div>
    </header>

    <ELoadingState
      v-if="showJourneyFullLoading"
      :variant="embedded ? 'inline' : 'page'"
      class="material-journey-loading"
      :message="t('activities.materialJourney.loading')"
    />

    <div v-else-if="error" class="material-journey-error section-card">
      <p>{{ error }}</p>
      <EButton variant="primary" size="small" @click="reload">{{ t('common.retry') }}</EButton>
    </div>

    <template v-else-if="activity">
      <MaterialJourneyStepper
        v-if="!showQuickGroupCompletionOnly && !showMwStoreCompletionReview && !showCompletedMaterialArchive"
        :steps="steps"
        :current-step="resolvedStep"
        :active-step="activeJourneyStep"
        :steps-with-open-work="stepsWithOpenWork"
        :profile="profile"
        :activity-status="activity.status ?? ''"
        :issue-done-count="progress.done"
        @update:current-step="onStepChange"
      />

      <p
        v-if="embedded && activity && !showMwStoreCompletionReview && !showCompletedMaterialArchive"
        class="material-journey-embedded-status"
      >
        <span class="status-label" :class="activityStatusCss">{{ journeyStepBadgeLabel }}</span>
      </p>

      <MaterialJourneyMwHandoffBanner
        v-if="showMwGroupHandoffBanner && !showCompletedMaterialArchive && !quickReturnHandoffBannerMode"
        :variant="showMwReturnHandoffBanner ? 'return' : 'issue'"
      />

      <MaterialJourneyQuickReturnHandoffBanner
        v-if="quickReturnHandoffBannerMode && !showCompletedMaterialArchive"
        :mode="quickReturnHandoffBannerMode"
        :loading="quickReturnHandoffInProgress"
        :disabled="quickReturnHandoffBannerDisabled"
        @action="onQuickReturnHandoffBannerAction()"
      />

      <MaterialJourneyReturnSummaryTable
        v-if="showMaterialSummaryTable"
        :rows="returnSummaryRows"
        :mode="materialSummaryMode"
      />

      <div
        v-if="showMwStepEditButton && !hideJourneyWorkUi"
        class="material-journey-mw-edit-banner section-card"
        role="note"
      >
        <p class="material-journey-mw-edit-banner__text text-muted">
          {{ t('activities.materialJourney.mwStepEdit.hint') }}
        </p>
        <EButton variant="primary" size="small" @click="enableMwStepEdit()">
          {{ t('activities.materialJourney.mwStepEdit.action') }}
        </EButton>
      </div>
      <p
        v-else-if="mwStepEditActive && !hideJourneyWorkUi"
        class="material-journey-mw-edit-active section-card text-muted"
        role="status"
      >
        {{ t('activities.materialJourney.mwStepEdit.active') }}
      </p>
      <p v-else-if="isFutureStep && !hideJourneyWorkUi" class="material-journey-readonly-banner section-card text-muted">
        {{ t('activities.materialJourney.readonlyFutureStep') }}
      </p>
      <p
        v-else-if="isPastStep && !canManageMaterials && !showQuickGroupCompletionOnly && !hideJourneyWorkUi"
        class="material-journey-readonly-banner section-card text-muted"
      >
        {{ t('activities.materialJourney.readonlyPastStep') }}
      </p>

      <MaterialJourneyTransportTours
        v-if="(showTransportTours || showTransportWeightReview) && !isEarlyPackPreview && !showPhaseCompletePanel"
        ref="transportToursRef"
        :activity-id="activityId"
        :department-id="departmentId"
        :journey-step="resolvedStep"
        :list-editable="effectiveListEditable"
        :can-manage-materials="canManageMaterials"
        :assignable-tasks="transportAssignableTasks"
        :pack-items="packItems"
        :pack-containers="packContainers"
        :container-items-by-container-id="containerItemsByContainerId"
        :transport-tour-select-mode="transportTourSelectMode"
        :selected-tour-id="selectedTourId"
        :simple-tour-mode="simpleTourMode"
        :plan-tours="transportTourSelectMode ? planTransportTours : null"
        :plan-vehicles="transportTourSelectMode ? planActivityVehicles : null"
        @pipeline-changed="onTransportPipelineChanged"
        @tour-items-changed="loadToursAndVehicles"
        @select-vehicle="selectVehicleTarget($event)"
        @select-tour="toggleTourSelection($event)"
        @select-simple-tour="selectSimpleTour()"
      />

      <MaterialJourneyChooseTourModal
        :open="chooseTourModalOpen"
        :vehicles="planActivityVehicles"
        :loading="assignTourSubmitting"
        @update:open="onChooseTourModalOpenChange"
        @select-vehicle="selectVehicleTarget($event, true)"
        @select-simple="selectSimpleTour(true)"
      />

      <MaterialJourneyPhaseCompletePanel
        v-if="showPhaseCompletePanel && phaseAdvanceTarget"
        :from-step="resolvedStep"
        :next-step="phaseAdvanceTarget"
        :total-count="progress.total"
        :loading="advancingJourneyPhase"
        :continue-label="phaseAdvanceContinueLabel ?? undefined"
        @continue="onAdvanceJourneyPhase()"
      />

      <MaterialJourneyStoreCompletePanel
        v-else-if="showMwStoreCompletionReview"
        :total-count="progress.total"
        :loading="completingStoreActivity"
        :disabled="Boolean(completedTransition && !completedTransition.allowed)"
        @continue="onCompleteStoreActivity()"
      />

      <MaterialJourneyStoreCompletePanel
        v-else-if="showMwStoreCompleteWithWetPending"
        :total-count="progress.total"
        :wet-pending-count="storeWetTasks.length"
        :loading="completingStoreActivity"
        :disabled="Boolean(completedTransition && !completedTransition.allowed)"
        @continue="onCompleteStoreActivity()"
      />

      <div
        v-else-if="!hideJourneyWorkUi && !hideJourneyScanOnMwStoreComplete && !isEarlyPackPreview && !isLogisticsAtEventInventory && !hideIssueScanBar"
        class="material-journey-scan-wrap"
      >
        <MaterialJourneyScanBar
          v-model="scanQuery"
          :loading="scanResolving || assignCrateSubmitting"
          :session-log="scanSessionLog"
          :pack-target-label="scanTargetLabel"
          :suggestions="storeScanSuggestions"
          :placeholder-key="scanBarPlaceholderKey"
          @submit="onScanSubmit"
          @clear="onScanClear"
          @deselect="clearSelectedPackCrate()"
          @select-suggestion="onStoreScanSuggestionSelect"
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
          :inline-show-issue-actions="showIssueForScanResult(shelfInlineResult)"
          :inline-issue-is-consumable="
            shelfInlineResult?.packItem
              ? isConsumableForMaterialId(shelfInlineResult.packItem.materialItemId)
              : false
          "
          @focus-line="onScanShelfLineFocus"
          @inline-primary="onShelfInlinePrimary"
          @inline-in-crate="onShelfInlineInCrate"
          @inline-confirm-bulk="confirmBulkBatch()"
          @inline-dismiss-line="onShelfInlineDismissLine"
          @inline-consumed="shelfInlineResult && onScanIssueConsumed(shelfInlineResult)"
          @inline-loss="shelfInlineResult && onScanIssueFromResult(shelfInlineResult, 'loss')"
          @inline-repair="shelfInlineResult && onScanIssueFromResult(shelfInlineResult, 'repair')"
          @inline-damage="shelfInlineResult && onScanIssueFromResult(shelfInlineResult, 'damage')"
          @dismiss="onDismissShelfSession()"
        />

        <MaterialScanResultCard
          v-if="showStandaloneScanResult && scanResult"
          :result="scanResult"
          :message="scanMessageResolved"
          :quantity-hint="scanQuantityHint(scanResult)"
          :quantity-progress="scanQuantityProgress(scanResult)"
          :primary-label="scanPrimaryLabelResolved"
          :primary-enabled="scanPrimaryEnabledResolved"
          :dismiss-label="dismissLabelForResult(scanResult)"
          :show-bulk-confirm="Boolean(scanResult.needsBulkConfirm)"
          :bulk-confirmed="scanBulkConfirmed"
          :show-in-crate="showInCrateAction(scanResult)"
          :in-crate-label="inCrateActionLabel()"
          :show-issue-actions="showIssueForScanResult(scanResult)"
          :issue-is-consumable="
            scanResult.packItem ? isConsumableForMaterialId(scanResult.packItem.materialItemId) : false
          "
          :warehouse-warning="scanCrateWarehouseWarning"
          :warehouse-line-preview="scanCrateWarehouseLinePreview"
          :warehouse-hint="scanCrateWarehouseHint"
          :show-warehouse-action="scanShowWarehouseAction"
          :warehouse-action-label="t('activities.materialJourney.scan.actionMiniInventory')"
          :warehouse-action-enabled="scanWarehouseActionEnabled"
          @primary="onScanPrimary"
          @warehouse-action="onScanWarehouseAction"
          @in-crate="onScanInCrate"
          @confirm-bulk="confirmBulkBatch()"
          @consumed="onScanIssueConsumed(scanResult)"
          @loss="onScanIssueFromResult(scanResult, 'loss')"
          @repair="onScanIssueFromResult(scanResult, 'repair')"
          @damage="onScanIssueFromResult(scanResult, 'damage')"
          @dismiss="dismissResult()"
        />

        <MaterialJourneyActiveCratePanel
          v-if="showActivePackCratePanel && selectedPackCrateHasContents"
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
        v-if="showReplenishmentPanel && !hideJourneyWorkUi"
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
        v-if="!hideJourneyWorkUi && !isEarlyPackPreview && !showPhaseCompletePanel && !showMwStoreCompletionReview && showFilterToolbar"
        v-model:filter-tab="filterTab"
        :filter-variant="journeyFilterVariant"
        :hide-filter-tabs="useRegalGroupingOnStore"
        :done-count="progress.done"
        :total-count="progress.total"
        :show-by-shelf-filter="showByShelfFilter"
        :presence-labels="presenceLabels"
        :show-add-pack-crate="showAddPackCrateButton"
        :add-pack-crate-loading="containerMutationLoading"
        :show-mark-packed="showPackCompletePanel"
        :mark-packed-label="markPackedButtonLabel"
        :mark-packed-disabled="markPackedActionDisabled"
        :mark-packed-loading="markingPacked"
        :mark-packed-hint="markPackedHint"
        :pack-complete-description="
          t('activities.materialJourney.packComplete.description', { count: progress.total })
        "
        :show-bring-back="showQuickBringBackButton"
        :bring-back-disabled="quickBringBackDisabled"
        :bring-back-disabled-hint="quickBringBackDisabledHint"
        :show-partial-taken="showPartialTakenToEventButton"
        :partial-taken-disabled="partialTakenToEventInProgress"
        :partial-taken-loading="partialTakenToEventInProgress"
        :partial-taken-title="partialTakenToEventTitle"
        :progress-breakdown="progressBreakdownLabel"
        @add-pack-crate="openAddPackCrateModal()"
        @mark-packed="onMarkPacked()"
        @bring-back="onQuickBringBack()"
        @partial-taken="onPartialTakenToEvent()"
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

      <div
        v-if="isLogisticsAtEventInventory && !showStepCompletePanel"
        class="material-journey-inventory-header section-card"
      >
        <div class="material-journey-inventory-header__top">
          <div>
            <h2 class="material-journey-inventory-header__title">
              {{ t('activities.materialJourney.inventoryAtEvent.title') }}
            </h2>
            <p v-if="displayedTasks.length === 0" class="text-muted material-journey-inventory-header__empty">
              {{ t('activities.materialJourney.inventoryAtEvent.empty') }}
            </p>
            <p v-else class="text-muted material-journey-inventory-header__hint">
              {{ t('activities.materialJourney.inventoryAtEvent.hint') }}
            </p>
          </div>
          <EButton
            v-if="showIssueReturnMaterialButton"
            variant="primary"
            size="small"
            class="material-journey-inventory-header__action"
            :loading="advancingJourneyPhase"
            @click="onAdvanceJourneyPhase()"
          >
            {{ t('activities.materialJourney.inventoryAtEvent.returnMaterial') }}
          </EButton>
        </div>
      </div>

      <section
        v-if="showStoreShelfHint"
        class="material-journey-store-hint section-card"
        role="note"
      >
        <p class="material-journey-store-hint__title">
          {{ t('activities.materialJourney.storeHint.title') }}
        </p>
        <p class="material-journey-store-hint__body text-muted">
          {{ t('activities.materialJourney.storeHint.description') }}
        </p>
      </section>

      <MaterialJourneySelfProvidedBanner
        v-if="virtualComboSelfProvidedHints.length > 0"
        :hints="virtualComboSelfProvidedHints"
        :activity-id="activityId"
        :show-issue-ack="resolvedStep === 'issue'"
      />

      <p
        v-if="showPostCompleteWetQueue"
        class="material-journey-readonly-banner section-card text-muted"
      >
        {{ t('activities.materialJourney.storeComplete.wetQueueAfterComplete') }}
      </p>

      <MaterialJourneyTaskList
        v-if="!showPhaseCompletePanel && !hideJourneyWorkUi && !showMwStoreCompletionReview"
        :tasks="showPostCompleteWetQueue || showMwStoreCompleteWithWetPending ? [] : displayedTasks"
        :regal-groups="
          showPostCompleteWetQueue || showMwStoreCompleteWithWetPending ? [] : displayedRegalGroups
        "
        :group-by-shelf="useRegalGroupingOnStore && !showPostCompleteWetQueue"
        :journey-step="resolvedStep"
        :filter-tab="filterTab"
        :filter-variant="journeyFilterVariant"
        :is-early-pack-preview="isEarlyPackPreview"
        :position-count="positionCount"
        :list-editable="effectiveListEditable"
        :moving-id="movingId"
        :total-open-count="progress.open"
        :list-filter-active="listTextFilterActive"
        :pack-crate-select-mode="packCrateSelectMode"
        :pack-target-crate-id="selectedPackCrateId"
        :pack-target-crate-label="selectedPackCrate?.label ?? null"
        :transport-tour-assign-active="transportTourAssignActive"
        :transport-target-tour-label="selectedTourLabel"
        :container-items-by-container-id="containerItemsByContainerId"
        :pack-items="packItems"
        :pack-containers="packContainers"
        :crate-peek-maps="cratePeekMaps"
        :shell-pack-item-for-container="shellPackItemForContainer"
        :show-transit-actions="showCrateAccordionPackActions"
        :show-move-forward="showLooseMoveForward"
        :show-crate-move-forward="showCrateMoveForwardOnRow"
        :show-issue-for-row="showIssueForRowBound"
        :show-issue-for-accordion-line="showIssueForAccordionLineBound"
        :container-line-remaining-store="containerLineRemainingStore"
        :shell-store-pending-qty-for-row="shellStorePendingQtyForRow"
        :at-event-qty-label-for-row="atEventQtyLabelForRow"
        :at-event-qty-label-for-line="atEventQtyLabelForLineBound"
        :is-consumable-for-material-id="isConsumableForMaterialId"
        :show-crate-content-actions="showCrateContentActions"
        :delete-empty-submitting-for-row="deleteEmptySubmittingForRow"
        :show-store-split-sections="showStoreSplitSections"
        :store-wet-tasks="storeWetTasks"
        :store-done-tasks="storeDoneTasks"
        @activate="onActivateTaskRow"
        @activate-wet="onActivateStoreWetTask"
        @wet-return="onWetReturnFromList"
        @select-target="onSelectPackTarget"
        @move-forward="onMoveForwardTask"
        @consumed="onIssueConsumed"
        @loss="onIssueLoss"
        @repair="onIssueRepair"
        @damage="onIssueDamage"
        @line-consumed="onIssueConsumedLine"
        @line-loss="onIssueLossLine"
        @line-repair="onIssueRepairLine"
        @line-damage="onIssueDamageLine"
        @store-line="onAccordionStoreLine"
        @store-shell="onCrateShellStore"
        @reassign-to="onReassignCrateLine"
        @delete-empty="onDeleteEmptyPackCrate"
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
        :profile="profile"
        :activity-id="activityId"
        :department-id="departmentId"
        :can-manage-materials="canManageMaterials"
        :can-submit="effectiveListEditable"
        :can-delete="effectiveListEditable"
        :deleting="containerMutationLoading"
        :issueable-units="activeCrateIssueableUnits"
        :apply-updated-item="applyUpdatedItem"
        :pack-move-qty-cap="packIssueForwardMax"
        :container-line-remaining-store="containerLineRemainingStore"
        :container-inner-pending-store-units="containerInnerPendingStoreUnits"
        :container-shell-pending-store-qty="containerShellPendingStoreQty"
        :container-shell-only-pending-unpack="containerShellOnlyPendingUnpack"
        @completed="onCrateSheetCompleted"
        @delete="onCrateDelete"
        @store-line="onCrateStoreLine"
        @store-shell="onCrateStoreShell"
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
        :can-submit="effectiveListEditable"
        :max-forward-qty="activeComboMaxForwardQty"
        :apply-updated-item="applyUpdatedItem"
        :pack-move-qty-cap="packIssueForwardMax"
        @completed="onComboSheetCompleted"
      />

      <PhysicalComboIssueComponentModal
        :open="physicalComboIssueModalOpen"
        :loading="physicalComboIssueModalLoading"
        :issue-type="physicalComboIssueModalIssueType"
        :shell-pack-item="physicalComboIssueModalPi"
        :sections="physicalComboIssueModalSections"
        @cancel="closePhysicalComboIssueModal"
        @confirm="onPhysicalComboIssueConfirm"
      />

      <MaterialReturnCrateSheet
        v-model:open="returnCrateOpen"
        :container-label="returnCrateSheetLabel"
        :partition="returnCratePartition"
        :lines="returnCrateLines"
        :submitting="returnCrateSubmitting"
        :submit-disabled="returnCrateSubmitDisabled"
        :can-report-consumption="canReportConsumptionRef"
        :can-report-issues="canReportIssuesRef"
        :department-id="departmentId"
        @update:lines="onReturnCrateLinesUpdate"
        @report-consumption="onReturnCrateReportConsumption"
        @report-loss="onReturnCrateReportLoss"
        @report-repair="onReturnCrateReportRepair"
        @report-extra="onReturnCrateReportExtra"
        @submit="submitReturnCrate()"
      />

      <MaterialStoreShelveSheet
        v-model:open="storeShelveOpen"
        v-model:qty="storeShelveQty"
        :pack-item="activeStoreItem"
        :max-qty="activeStoreMaxQty"
        :department-id="departmentId"
        :submitting="storeShelveSubmitting"
        :opened-from-scan="storeShelveOpenedFromScan"
        :store-display-name="activeStoreContainer?.label ?? null"
        :store-rack-name="activeStoreContainer?.container_storage_rack_name ?? null"
        :store-slot-name="activeStoreContainer?.container_storage_slot_name ?? null"
        @confirm="submitStoreShelve()"
      />

      <MaterialJourneyStepFooter
        v-if="!isEarlyPackPreview && !showStepCompletePanel && !showMwStoreCompletionReview && !isLogisticsAtEventInventory && !hideJourneyWorkUi"
        :journey-step="resolvedStep"
        :profile="profile"
        :filter-variant="journeyFilterVariant"
        :done-count="progress.done"
        :total-count="progress.total"
        :open-count="progress.open"
        :progress-breakdown="progressBreakdownLabel"
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

.material-journey-inventory-header__title {
  margin: 0 0 4px;
  font-size: 1rem;
}

.material-journey-embedded-status {
  margin: 0;
}

.material-journey-inventory-header__top {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.material-journey-inventory-header__hint,
.material-journey-inventory-header__empty {
  margin: 0;
  font-size: 13px;
}

.material-journey-inventory-header__action {
  flex-shrink: 0;
}

.material-journey-store-hint {
  margin-bottom: 12px;
  border: 1px solid rgba(var(--v-theme-primary), 0.35);
  background: rgba(var(--v-theme-primary), 0.05);
}

.material-journey-store-hint__title {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
}

.material-journey-store-hint__body {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
}
</style>
