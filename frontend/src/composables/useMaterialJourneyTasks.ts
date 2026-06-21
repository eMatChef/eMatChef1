import { computed, nextTick, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityDetail } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import {
  createActivityPackContainer,
  createActivityPackContainerItem,
  updateActivityPackContainerItem,
} from '@/api/activityContainers'
import { postMovePackItem, postMoveBackPackItem, type ActivityPackItem, type PackMoveSource } from '@/api/activityPackItems'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'
import { packItemsForMaterialJourney } from '@/components/activities/materialJourneyJsSummary'
import { isCrateShellPackItem } from '@/components/activities/packShellCrateHelpers'
import {
  buildMaterialJourneyTasks,
  filterMaterialJourneyTasksByTab,
  sortMaterialJourneyTasks,
  type MaterialJourneyFilterTab,
  type MaterialJourneyTaskRow,
} from '@/components/activities/materialJourneyTaskList'
import {
  defaultJourneyStepForStatus,
  isJourneyLooseMovesEnabledForStep,
  isJourneyReturnStep,
  isJourneyTransportOutStep,
  isJourneyStepAheadOfDefault,
  isJourneyStepBehindDefault,
  isJourneyStoreStep,
  journeyStepToPackStage,
  materialJourneyShowsMoveBack,
  materialJourneyShowsMoveForwardQty,
  materialJourneyShowsCrateMoveForwardQty,
  materialJourneyShowsShelfLocation,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'
import { shouldOpenMaterialJourneyReturnCrateModal } from '@/components/activities/materialJourneyReturnCrate'
import { useMaterialJourneyReturnCrate } from '@/composables/useMaterialJourneyReturnCrate'
import { countCratePeekLines } from '@/composables/useMaterialJourneyCrateSections'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { getBackendStage } from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { packWorkflowCanEdit } from '@/components/activities/packWorkflowRules'
import { useMaterialJourneyPackContext } from '@/composables/useMaterialJourneyPackContext'
import { useToast } from '@/composables/useToast'
import {
  acknowledgeTransportOut,
  clearTransportOutAck,
  isTransportOutAcknowledged,
} from '@/utils/materialJourneyTransportAck'

export type { MaterialJourneyFilterTab } from '@/components/activities/materialJourneyTaskList'

export function useMaterialJourneyTasks(options: {
  activity: Ref<ActivityDetail | null>
  packItems: Ref<ActivityPackItem[]>
  packContainers: Ref<ActivityPackContainer[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  cratePeekMaps: Ref<MaterialJourneyCratePeekMaps>
  journeyStep: Ref<JourneyStep>
  profile: Ref<PackWorkflowProfile>
  canManageMaterials: Ref<boolean>
  isEarlyPackPreview: Ref<boolean>
  reload: () => Promise<void>
  reloadSilent?: () => Promise<void>
  applyContainerItem: (containerId: string, item: ActivityPackContainerItem) => void
}) {
  const { t } = useI18n()
  const toast = useToast()

  const filterTab = ref<MaterialJourneyFilterTab>('open')
  const movingId = ref<string | null>(null)
  const crateSheetOpen = ref(false)
  const comboSheetOpen = ref(false)
  const activeCrate = ref<ActivityPackContainer | null>(null)
  const activeCombo = ref<ActivityPackItem | null>(null)
  const storeShelveOpen = ref(false)
  const activeStoreItem = ref<ActivityPackItem | null>(null)
  const activeStoreMaxQty = ref(0)
  const storeShelveQty = ref(1)
  const storeShelveSubmitting = ref(false)
  const storeShelveFeedback = ref(false)
  const lastFailedMove = ref<{ row: MaterialJourneyTaskRow; source: PackMoveSource } | null>(null)
  const moveBackQtyInputs = ref<Record<string, number>>({})
  const forwardQtyInputs = ref<Record<string, number>>({})
  const assignCrateSheetOpen = ref(false)
  const assignCratePackItem = ref<ActivityPackItem | null>(null)
  const assignCrateMaxQty = ref(1)
  const assignCrateQty = ref(1)
  const assignCrateSubmitting = ref(false)
  const selectedPackCrateId = ref<string | null>(null)
  const addingPackCrate = ref(false)

  const packStage = computed(() => journeyStepToPackStage(options.journeyStep.value, options.profile.value))

  const defaultJourneyStep = computed(() => {
    const activityId = options.activity.value?.id ?? ''
    const status = options.activity.value?.status ?? 'packing'
    if (status !== 'packed') {
      clearTransportOutAck(activityId)
    }
    return defaultJourneyStepForStatus(status, options.profile.value, options.canManageMaterials.value, {
      transportOutAcknowledged: isTransportOutAcknowledged(activityId),
    })
  })

  const isFutureStep = computed(() =>
    isJourneyStepAheadOfDefault(
      options.journeyStep.value,
      defaultJourneyStep.value,
      options.profile.value,
    ),
  )

  const isPastStep = computed(() =>
    isJourneyStepBehindDefault(
      options.journeyStep.value,
      defaultJourneyStep.value,
      options.profile.value,
    ),
  )

  const movesEnabledForStep = computed(() =>
    isJourneyLooseMovesEnabledForStep(options.journeyStep.value, options.profile.value),
  )

  const listEditable = computed(() => {
    if (options.isEarlyPackPreview.value) return false
    if (options.activity.value?.is_pack_list_editable === false) return false
    if (isFutureStep.value || isPastStep.value) return false
    if (!packWorkflowCanEdit(
      options.profile.value,
      options.canManageMaterials.value,
      options.activity.value?.status ?? '',
    )) {
      return false
    }
    return true
  })

  const sheetsEnabledForStep = movesEnabledForStep

  const {
    packListCtx,
    packContainerCtx,
    packIssueForwardMax,
    stageLeftItems,
    shellPackItemForContainer,
    containerIssueableUnits,
    containerActionableUnits,
    packQuantityCtx,
    packCrateLabelsForPackItem,
    qtyInPackCrateForPackItem,
    packCrateAssignQtyForItem,
    rightQtyForMoveBack,
  } = useMaterialJourneyPackContext({
    packItems: options.packItems,
    packContainers: options.packContainers,
    containerItemsByContainerId: options.containerItemsByContainerId,
    packStage,
    profile: options.profile,
  })

  const returnCrate = useMaterialJourneyReturnCrate({
    activityId: computed(() => options.activity.value?.id ?? ''),
    packItems: options.packItems,
    containerItemsByContainerId: options.containerItemsByContainerId,
    packQuantityCtx,
    shellPackItemForContainer,
    reload: options.reload,
  })

  function canMoveBackItem(pi: ActivityPackItem): boolean {
    if (!listEditable.value) return false
    if (!materialJourneyShowsMoveBack(options.journeyStep.value)) return false
    return true
  }

  const showMoveBack = computed(
    () =>
      materialJourneyShowsMoveBack(options.journeyStep.value) &&
      listEditable.value &&
      filterTab.value === 'done',
  )

  const showMoveForwardQty = computed(
    () =>
      materialJourneyShowsMoveForwardQty(options.journeyStep.value, options.profile.value) &&
      listEditable.value &&
      filterTab.value === 'open',
  )

  const showCrateMoveForwardQty = computed(
    () =>
      materialJourneyShowsCrateMoveForwardQty(options.journeyStep.value) &&
      listEditable.value &&
      filterTab.value === 'open',
  )

  const transportedUnitsTotal = computed(() => {
    if (!materialJourneyShowsMoveBack(options.journeyStep.value)) return 0
    let sum = 0
    for (const pi of packItemsForMaterialJourney(options.packItems.value)) {
      if (isCrateShellPackItem(pi, options.packContainers.value)) continue
      sum += rightQtyForMoveBack(pi)
    }
    return sum
  })

  function canMoveItem(pi: ActivityPackItem): boolean {
    if (!listEditable.value || !movesEnabledForStep.value) return false
    if (options.journeyStep.value === 'pack' && !options.canManageMaterials.value) return false
    if (options.journeyStep.value === 'store' && !options.canManageMaterials.value) return false
    return true
  }

  const canOpenSheet = computed(() => listEditable.value && sheetsEnabledForStep.value)

  const cratePeekCtx = computed(() => ({
    containerItemsByContainerId: options.containerItemsByContainerId.value,
    ...options.cratePeekMaps.value,
  }))

  const taskBuildCtx = computed(() => ({
    listCtx: packListCtx.value,
    containerCtx: packContainerCtx.value,
    stageLeftItems: stageLeftItems.value,
    packStage: packStage.value,
    packContainers: options.packContainers.value,
    maxForwardQty: packIssueForwardMax,
    containerIssueableUnits: containerActionableUnits,
    containerActionableUnits,
    canMoveItem,
    canMoveBackItem,
    rightQtyForMoveBack,
    canOpenSheet: canOpenSheet.value,
    formatCrateLineCount: (count: number) =>
      t('activities.materialJourney.row.crateLineCount', { count }),
    shellPackItemForContainer,
    cratePeekLineCount: (
      container: ActivityPackContainer,
      shellPackItem?: ActivityPackItem,
    ) =>
      countCratePeekLines(
        container,
        cratePeekCtx.value,
        shellPackItem ?? null,
        t,
        options.packItems.value,
        options.packContainers.value,
      ),
    qtyInPackCrateForItem: qtyInPackCrateForPackItem,
    packCrateLabelsForItem: packCrateLabelsForPackItem,
    formatPackCrateHint: (labels: string[]) => {
      if (labels.length === 1) {
        return t('activities.materialJourney.row.inPackCrate', { label: labels[0] })
      }
      return t('activities.materialJourney.row.inPackCrates', { labels: labels.join(', ') })
    },
    comboComponentsByMaterialId: options.cratePeekMaps.value.comboComponentsByMaterialId,
    comboMaterialIdByContainerId: options.cratePeekMaps.value.comboMaterialIdByContainerId,
    showShelfLocation: materialJourneyShowsShelfLocation(options.journeyStep.value),
  }))

  const allTasks = computed(() => {
    const rows = buildMaterialJourneyTasks(options.packItems.value, taskBuildCtx.value)
    return sortMaterialJourneyTasks(rows, options.journeyStep.value)
  })

  const visibleTasks = computed(() => filterMaterialJourneyTasksByTab(allTasks.value, filterTab.value))

  const showByShelfFilter = computed(
    () =>
      materialJourneyShowsShelfLocation(options.journeyStep.value) &&
      options.canManageMaterials.value,
  )

  watch(
    [showByShelfFilter],
    ([showShelf]) => {
      if (filterTab.value === 'byShelf' && !showShelf) {
        filterTab.value = 'open'
      }
    },
  )

  const progress = computed(() => {
    const openCount = allTasks.value.filter((row) => row.isOpen).length
    const doneCount = allTasks.value.filter((row) => row.isDone).length
    const total = openCount + doneCount
    return { open: openCount, done: doneCount, total }
  })

  watch(
    () =>
      [
        options.activity.value?.id,
        options.activity.value?.status,
        options.journeyStep.value,
        progress.value.open,
        progress.value.total,
      ] as const,
    ([activityId, status, step, open, total]) => {
      if (
        activityId &&
        status === 'packed' &&
        isJourneyTransportOutStep(step) &&
        total > 0 &&
        open === 0
      ) {
        acknowledgeTransportOut(activityId)
      }
    },
  )

  const activeCrateShellPackItem = computed(() =>
    activeCrate.value ? shellPackItemForContainer(activeCrate.value.id) ?? null : null,
  )

  const activeCrateIssueableUnits = computed(() =>
    activeCrate.value ? containerActionableUnits(activeCrate.value.id) : 0,
  )

  const activeComboMaxForwardQty = computed(() =>
    activeCombo.value ? packIssueForwardMax(activeCombo.value) : 0,
  )

  const selectedPackCrate = computed(() => {
    const id = selectedPackCrateId.value
    if (!id) return null
    return options.packContainers.value.find((c) => c.id === id) ?? null
  })

  const selectedPackCrateItems = computed(() => {
    const id = selectedPackCrateId.value
    if (!id) return []
    return options.containerItemsByContainerId.value[id] ?? []
  })

  watch(
    () => options.packContainers.value,
    (containers) => {
      const id = selectedPackCrateId.value
      if (id && !containers.some((c) => c.id === id)) {
        selectedPackCrateId.value = null
      }
    },
  )

  watch(options.journeyStep, (step) => {
    if (step !== 'pack') {
      selectedPackCrateId.value = null
    }
  })

  function applyUpdatedItem(updated: ActivityPackItem): void {
    const idx = options.packItems.value.findIndex((p) => p.id === updated.id)
    if (idx !== -1) {
      options.packItems.value[idx] = updated
    }
  }

  function showReadonlyToast(row: MaterialJourneyTaskRow): void {
    if (!listEditable.value) {
      toast.info(t('activities.materialJourney.toastViewOnly'))
    } else if (row.kind === 'combo' || row.kind === 'crate') {
      toast.info(t('activities.materialJourney.toastStepReadonly'))
    } else if (!movesEnabledForStep.value) {
      toast.info(t('activities.materialJourney.toastStepReadonly'))
    }
  }

  function openStoreShelve(pi: ActivityPackItem, maxQty: number): void {
    activeStoreItem.value = pi
    activeStoreMaxQty.value = maxQty
    storeShelveQty.value = maxQty
    storeShelveFeedback.value = false
    storeShelveOpen.value = true
  }

  function closeStoreShelve(): void {
    storeShelveOpen.value = false
    storeShelveFeedback.value = false
    activeStoreItem.value = null
  }

  function findNextOpenStoreRow(): MaterialJourneyTaskRow | undefined {
    return allTasks.value.find(
      (row) =>
        row.kind === 'loose' &&
        row.isOpen &&
        row.canMove &&
        row.packItem &&
        row.packItem.id !== activeStoreItem.value?.id,
    )
  }

  async function submitStoreShelve(): Promise<void> {
    const pi = activeStoreItem.value
    const activityId = options.activity.value?.id
    const qty = storeShelveQty.value
    if (!pi || !activityId || qty < 1) return

    storeShelveSubmitting.value = true
    try {
      const updated = await postMovePackItem(activityId, pi.id, {
        stage: getBackendStage(packStage.value),
        quantity: qty,
        source: 'tap',
      })
      applyUpdatedItem(updated)
      toast.success(t('activities.materialJourney.storeSheet.toastSuccess'))
    storeShelveFeedback.value = true
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      storeShelveSubmitting.value = false
    }
  }

  function onStoreShelveNext(): void {
    const next = findNextOpenStoreRow()
    closeStoreShelve()
    if (next?.packItem) {
      openStoreShelve(next.packItem, next.maxForwardQty)
    }
  }

  function onStoreShelveStay(): void {
    closeStoreShelve()
  }

  async function moveTaskRow(
    row: MaterialJourneyTaskRow,
    source: PackMoveSource = 'tap',
    qty?: number,
  ): Promise<void> {
    if (!row.packItem || !row.canMove || row.maxForwardQty < 1) {
      showReadonlyToast(row)
      return
    }
    const activityId = options.activity.value?.id
    if (!activityId) return

    let moveQty = qty != null ? Math.floor(qty) : forwardQtyForRow(row)
    if (!Number.isFinite(moveQty) || moveQty < 1) moveQty = row.maxForwardQty
    moveQty = Math.min(moveQty, row.maxForwardQty)
    if (moveQty < 1) return

    setForwardQtyForRow(row.id, moveQty, row.maxForwardQty)
    movingId.value = row.id
    lastFailedMove.value = null
    try {
      const updated = await postMovePackItem(activityId, row.packItem.id, {
        stage: getBackendStage(packStage.value),
        quantity: moveQty,
        source,
      })
      applyUpdatedItem(updated)
    } catch (e) {
      lastFailedMove.value = { row, source }
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      movingId.value = null
    }
  }

  function forwardQtyForRow(row: MaterialJourneyTaskRow): number {
    const stored = forwardQtyInputs.value[row.id]
    if (stored != null && stored > 0) return Math.min(stored, row.maxForwardQty)
    return row.maxForwardQty
  }

  function setForwardQtyForRow(rowId: string, qty: number, max: number): void {
    let v = Math.floor(Number(qty))
    if (!Number.isFinite(v) || v < 1) v = 1
    if (max > 0 && v > max) v = max
    forwardQtyInputs.value = { ...forwardQtyInputs.value, [rowId]: v }
  }

  function moveBackQtyForRow(row: MaterialJourneyTaskRow): number {
    const stored = moveBackQtyInputs.value[row.id]
    if (stored != null && stored > 0) return Math.min(stored, row.maxMoveBackQty)
    return row.maxMoveBackQty
  }

  function setMoveBackQtyForRow(rowId: string, qty: number, max: number): void {
    let v = Math.floor(Number(qty))
    if (!Number.isFinite(v) || v < 1) v = 1
    if (max > 0 && v > max) v = max
    moveBackQtyInputs.value = { ...moveBackQtyInputs.value, [rowId]: v }
  }

  async function moveBackTaskRow(row: MaterialJourneyTaskRow, qty?: number): Promise<void> {
    if (!row.canMoveBack || row.maxMoveBackQty < 1) return
    const activityId = options.activity.value?.id
    if (!activityId) return

    const packItem =
      row.packItem ??
      (row.container ? shellPackItemForContainer(row.container.id) : undefined)
    if (!packItem) return

    let moveQty = qty != null ? Math.floor(qty) : moveBackQtyForRow(row)
    if (!Number.isFinite(moveQty) || moveQty < 1) moveQty = row.maxMoveBackQty
    moveQty = Math.min(moveQty, row.maxMoveBackQty)
    if (moveQty < 1) return

    setMoveBackQtyForRow(row.id, moveQty, row.maxMoveBackQty)
    movingId.value = row.id
    try {
      const updated = await postMoveBackPackItem(activityId, packItem.id, {
        stage: getBackendStage(packStage.value),
        quantity: moveQty,
      })
      applyUpdatedItem(updated)
      if (options.reloadSilent) {
        await options.reloadSilent()
      } else {
        await options.reload()
      }
      toast.success(
        t('activities.materialJourney.moveBack.toastSuccess', { qty: moveQty, name: row.title }),
      )
    } catch (e) {
      toast.error(e instanceof Error ? e.message : t('activities.packList.toastMoveBackFailed'))
    } finally {
      movingId.value = null
    }
  }

  async function retryMove(): Promise<void> {
    const failed = lastFailedMove.value
    if (!failed) return
    await moveTaskRow(failed.row, failed.source)
  }

  function activateTaskRow(row: MaterialJourneyTaskRow, source: PackMoveSource = 'tap'): void {
    if (row.kind === 'crate' && row.container) {
      if (!row.canOpenSheet) {
        showReadonlyToast(row)
        return
      }
      if (isJourneyReturnStep(options.journeyStep.value)) {
        if (
          shouldOpenMaterialJourneyReturnCrateModal(row.container.id, {
            canManageMaterials: options.canManageMaterials.value,
            packItems: options.packItems.value,
            containerItemsByContainerId: options.containerItemsByContainerId.value,
            packQuantityCtx: packQuantityCtx.value,
            shellPackItemForContainer,
          })
        ) {
          returnCrate.openFor(row.container)
          return
        }
      }
      activeCrate.value = row.container
      crateSheetOpen.value = true
      return
    }
    if (row.kind === 'combo' && row.packItem) {
      if (!row.canOpenSheet) {
        showReadonlyToast(row)
        return
      }
      openComboPackItem(row.packItem)
      return
    }
    if (isJourneyStoreStep(options.journeyStep.value) && row.kind === 'loose' && row.packItem && row.canMove) {
      const useInlineForward =
        materialJourneyShowsMoveForwardQty(options.journeyStep.value, options.profile.value) &&
        filterTab.value === 'open' &&
        source !== 'scan'
      if (!useInlineForward) {
        openStoreShelve(row.packItem, row.maxForwardQty)
      }
      return
    }
    if (
      materialJourneyShowsMoveForwardQty(options.journeyStep.value, options.profile.value) &&
      filterTab.value === 'open' &&
      source !== 'scan'
    ) {
      if (row.kind === 'loose' && row.canMove) return
      if ((row.kind === 'crate' || row.kind === 'combo') && row.canOpenSheet) return
    }
    void moveTaskRow(row, source)
  }

  async function moveForwardTaskRow(row: MaterialJourneyTaskRow, qty?: number): Promise<void> {
    if (row.kind === 'crate' || row.kind === 'combo') {
      if (row.canOpenSheet) activateTaskRow(row)
      return
    }
    if (isJourneyStoreStep(options.journeyStep.value) && row.kind === 'loose' && row.packItem && row.canMove) {
      const moveQty = qty != null ? Math.floor(qty) : forwardQtyForRow(row)
      openStoreShelve(row.packItem, moveQty)
      return
    }
    await moveTaskRow(row, 'tap', qty)
  }

  async function onCrateSheetCompleted(): Promise<void> {
    activeCrate.value = null
    if (options.reloadSilent) {
      await options.reloadSilent()
    } else {
      await options.reload()
    }
  }

  async function onComboSheetCompleted(updated: ActivityPackItem): Promise<void> {
    applyUpdatedItem(updated)
    activeCombo.value = null
    if (options.reloadSilent) {
      await options.reloadSilent()
    } else {
      await options.reload()
    }
  }

  function openCrateContainer(container: ActivityPackContainer): void {
    activeCrate.value = container
    crateSheetOpen.value = true
  }

  function openComboPackItem(pi: ActivityPackItem): void {
    activeCombo.value = pi
    if (comboSheetOpen.value) {
      comboSheetOpen.value = false
    }
    void nextTick(() => {
      comboSheetOpen.value = true
    })
  }

  function activateLoosePackItem(pi: ActivityPackItem, source: PackMoveSource = 'tap'): void {
    if (isJourneyStoreStep(options.journeyStep.value)) {
      const row = allTasks.value.find((r) => r.kind === 'loose' && r.packItem?.id === pi.id)
      if (row?.canMove && row.maxForwardQty > 0) {
        openStoreShelve(pi, row.maxForwardQty)
        return
      }
    }
    const row = allTasks.value.find((r) => r.kind === 'loose' && r.packItem?.id === pi.id)
    if (row) void activateTaskRow(row, source)
  }

  function selectPackCrate(containerId: string): void {
    selectedPackCrateId.value = containerId
  }

  function togglePackCrateSelection(containerId: string): void {
    if (selectedPackCrateId.value === containerId) {
      selectedPackCrateId.value = null
      return
    }
    selectedPackCrateId.value = containerId
  }

  function clearSelectedPackCrate(): void {
    selectedPackCrateId.value = null
  }

  async function submitAddScannedPackCrate(batchId: string, label: string): Promise<ActivityPackContainer | null> {
    const activityId = options.activity.value?.id
    if (!activityId || !batchId.trim()) return null

    const raw = label.trim() || t('activities.packList.crateTargetFallback')
    addingPackCrate.value = true
    try {
      const created = await createActivityPackContainer(activityId, {
        label: raw.slice(0, 120),
        container_batch_id: batchId,
      })
      await options.reload()
      selectedPackCrateId.value = created.id
      toast.success(t('activities.packList.toastContainerAdded'))
      return created
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
      return null
    } finally {
      addingPackCrate.value = false
    }
  }

  async function assignPackItemToSelectedCrate(
    pi: ActivityPackItem,
    maxQty: number,
    source: PackMoveSource = 'scan',
  ): Promise<boolean> {
    const containerId = selectedPackCrateId.value
    const activityId = options.activity.value?.id
    if (!containerId || !activityId) return false

    const qty = Math.max(1, maxQty)
    if (qty < 1) return false

    assignCrateSubmitting.value = true
    try {
      if (options.journeyStep.value === 'pack') {
        const left = packListCtx.value.effectiveStageLeftQty(pi)
        if (left > 0) {
          const moveQty = Math.min(qty, left)
          const updated = await postMovePackItem(activityId, pi.id, {
            stage: getBackendStage(packStage.value),
            quantity: moveQty,
            source,
          })
          applyUpdatedItem(updated)
        }
      }

      const items = options.containerItemsByContainerId.value[containerId] ?? []
      const existing = items.find((row) => row.material_item_id === pi.materialItemId)
      const containerItem = existing
        ? await updateActivityPackContainerItem(activityId, containerId, existing.id, {
            quantity_packed: existing.quantity_packed + qty,
          })
        : await createActivityPackContainerItem(activityId, containerId, {
            material_item_id: pi.materialItemId,
            quantity_packed: qty,
          })

      options.applyContainerItem(containerId, containerItem)

      const label =
        options.packContainers.value.find((c) => c.id === containerId)?.label ?? ''
      toast.success(t('activities.packList.toastMaterialAddedToCrate', { label }))
      return true
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
      return false
    } finally {
      assignCrateSubmitting.value = false
    }
  }

  function openAssignCrateSheet(pi: ActivityPackItem, maxQty: number): void {
    assignCratePackItem.value = pi
    assignCrateMaxQty.value = Math.max(1, maxQty)
    assignCrateQty.value = Math.max(1, maxQty)
    assignCrateSheetOpen.value = true
  }

  async function submitAssignToCrate(containerId: string): Promise<void> {
    const pi = assignCratePackItem.value
    const activityId = options.activity.value?.id
    if (!pi || !activityId) return

    const qty = Math.min(assignCrateQty.value, assignCrateMaxQty.value)
    if (qty < 1) return

    assignCrateSubmitting.value = true
    try {
      if (options.journeyStep.value === 'pack') {
        const left = packListCtx.value.effectiveStageLeftQty(pi)
        if (left > 0) {
          const moveQty = Math.min(qty, left)
          const updated = await postMovePackItem(activityId, pi.id, {
            stage: getBackendStage(packStage.value),
            quantity: moveQty,
            source: 'tap',
          })
          applyUpdatedItem(updated)
        }
      }

      const items = options.containerItemsByContainerId.value[containerId] ?? []
      const existing = items.find((row) => row.material_item_id === pi.materialItemId)
      const containerItem = existing
        ? await updateActivityPackContainerItem(activityId, containerId, existing.id, {
            quantity_packed: existing.quantity_packed + qty,
          })
        : await createActivityPackContainerItem(activityId, containerId, {
            material_item_id: pi.materialItemId,
            quantity_packed: qty,
          })

      options.applyContainerItem(containerId, containerItem)

      const label =
        options.packContainers.value.find((c) => c.id === containerId)?.label ?? ''
      assignCrateSheetOpen.value = false
      toast.success(
        t('activities.packList.toastMaterialAddedToCrate', { label }),
      )
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      assignCrateSubmitting.value = false
    }
  }

  function taskRowForScanResult(result: {
    packItem?: ActivityPackItem
    container?: ActivityPackContainer
  }): MaterialJourneyTaskRow | undefined {
    if (result.container) {
      return allTasks.value.find((r) => r.kind === 'crate' && r.container?.id === result.container?.id)
    }
    if (result.packItem) {
      if (isPhysicalComboPackItem(result.packItem)) {
        return allTasks.value.find((r) => r.kind === 'combo' && r.packItem?.id === result.packItem?.id)
      }
      return allTasks.value.find((r) => r.kind === 'loose' && r.packItem?.id === result.packItem?.id)
    }
    return undefined
  }

  return {
    filterTab,
    movingId,
    packStage,
    listEditable,
    movesEnabledForStep,
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
    moveForwardQtyForRow: forwardQtyForRow,
    setForwardQtyForRow,
    moveForwardTaskRow,
    moveBackTaskRow,
    moveBackQtyForRow,
    setMoveBackQtyForRow,
    showMoveBack,
    showMoveForwardQty,
    showCrateMoveForwardQty,
    transportedUnitsTotal,
    allTasks,
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
    shellPackItemForContainer,
  }
}
