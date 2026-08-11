import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import {
  postContainerItemWet,
  returnAllPackContainerItems,
  updateActivityPackContainerItem,
  type ContainerItemWetDisposition,
} from '@/api/activityContainers'
import {
  getPackItems,
  patchActivityPackItem,
  postMovePackItem,
  postPackItemWet,
  type ActivityPackItem,
  type PackItemWetDisposition,
} from '@/api/activityPackItems'
import type { ReturnCrateLineEdit, ReturnCratePartitionView } from '@/components/activities/PackReturnCrateModal.vue'
import {
  buildMaterialJourneyReturnCrateLines,
  materialJourneyReturnCrateBatchSteps,
  materialJourneyReturnCrateCanCompleteWithoutMoves,
  materialJourneyReturnCrateSubmitDisabled,
  type MaterialJourneyReturnCrateBatchStep,
} from '@/components/activities/materialJourneyReturnCrate'
import {
  computeContainerLineRemainingReturn,
  type PackQuantityContext,
} from '@/components/activities/packStageQuantityLayer'
import {
  resolveConsumableReturnQty,
} from '@/utils/materialJourneyConsumable'
import { returnCrateLineInputCap } from '@/utils/materialJourneyReturnCrateLineMeta'
import { useToast } from '@/composables/useToast'

type PendingConsumableReturn =
  | { kind: 'pack-item'; packItemId: string; qty: number }
  | { kind: 'container-line'; containerId: string; containerItemId: string; qty: number }

export function useMaterialJourneyReturnCrate(options: {
  activityId: Ref<string>
  packItems: Ref<ActivityPackItem[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  packQuantityCtx: Ref<PackQuantityContext>
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  issues: Ref<ActivityIssueReportRow[]>
  reload: () => Promise<void>
}) {
  const { t } = useI18n()
  const toast = useToast()

  const open = ref(false)
  const container = ref<ActivityPackContainer | null>(null)
  /** Lose Retour (ohne Kiste): Pack-Item für Regentropfen-UI. */
  const loosePackItem = ref<ActivityPackItem | null>(null)
  const lines = ref<ReturnCrateLineEdit[]>([])
  const submitting = ref(false)
  const pendingConsumableReturn = ref<PendingConsumableReturn | null>(null)
  const pendingReturnCrateBatch = ref<{
    containerId: string
    remaining: MaterialJourneyReturnCrateBatchStep[]
  } | null>(null)

  const partition = computed((): ReturnCratePartitionView => {
    const c = container.value
    if (!c) {
      return {
        shellQty: 0,
        shellIsExtra: false,
        shellMaterialName: '',
        extraLines: [],
        standardLines: [],
        hasWarehouseTemplate: false,
      }
    }
    const shellLine = lines.value.find((l) => l.kind === 'shell')
    return {
      shellQty: shellLine?.max ?? 0,
      shellIsExtra: false,
      shellMaterialName: shellLine?.materialName ?? '',
      extraLines: [],
      standardLines: (options.containerItemsByContainerId.value[c.id] ?? []).filter(
        (ci) => !lines.value.some((l) => l.containerItemId === ci.id && l.isDone),
      ),
      hasWarehouseTemplate: false,
    }
  })

  const submitDisabled = computed(() => materialJourneyReturnCrateSubmitDisabled(lines.value))

  const sheetLabel = computed(() => {
    if (container.value) return container.value.label
    return loosePackItem.value?.materialName ?? ''
  })

  function buildLooseReturnLine(pi: ActivityPackItem, maxReturnQty: number): ReturnCrateLineEdit {
    const existingWet = pi.quantityWet ?? 0
    const returnedAlready = pi.quantityReturned ?? 0
    const max = Math.max(0, maxReturnQty)
    const isDone = max < 1 && returnedAlready > 0
    const qty = isDone ? 0 : max
    const wetCap = isDone ? Math.max(0, returnedAlready - (pi.quantityStored ?? 0)) : qty
    return {
      id: `loose-${pi.id}`,
      kind: 'line',
      placement: 'loose',
      materialItemId: pi.materialItemId,
      materialName: pi.materialName,
      expectedQty: max,
      ordered: pi.quantityOrdered ?? max,
      consumed: 0,
      loss: 0,
      repair: 0,
      max,
      issued: pi.quantityIssued ?? 0,
      returnedAlready,
      included: !isDone && max > 0,
      qty,
      isExtra: false,
      isConsumable: Boolean(pi.isConsumable),
      consumptionDone: true,
      consumptionOpen: 0,
      isDone,
      wetEnabled: existingWet > 0,
      wetQty: Math.min(existingWet, wetCap > 0 ? wetCap : existingWet),
      wetHung: existingWet > 0 ? (pi.wetHung ?? false) : null,
      wetDryingStorageAddressId: pi.wetDryingStorageAddressId ?? '',
      wetDryingRackId: pi.wetDryingRackId ?? '',
      wetDryingSlotId: pi.wetDryingSlotId ?? '',
      wetDryingLocationLabel: pi.wetDryingLocationLabel ?? '',
    }
  }

  function syncLooseLine(): void {
    const pi = loosePackItem.value
    if (!pi) return
    const prev = lines.value[0]
    const live = options.packItems.value.find((p) => p.id === pi.id) ?? pi
    const rebuilt = buildLooseReturnLine(live, prev?.isDone ? 0 : (prev?.max ?? 0))
    if (!prev || rebuilt.isDone) {
      lines.value = [rebuilt]
      return
    }
    const cap = returnCrateLineInputCap(rebuilt.ordered, rebuilt.max)
    const qty = Math.min(Math.max(0, prev.qty), cap)
    const wetQty = Math.min(Math.max(0, prev.wetQty), qty > 0 ? qty : rebuilt.wetQty)
    lines.value = [
      {
        ...rebuilt,
        included: prev.included,
        qty,
        wetEnabled: prev.wetEnabled,
        wetQty,
        wetHung: wetQty > 0 ? prev.wetHung : null,
        wetDryingStorageAddressId: prev.wetDryingStorageAddressId,
        wetDryingRackId: prev.wetDryingRackId,
        wetDryingSlotId: prev.wetDryingSlotId,
        wetDryingLocationLabel: prev.wetDryingLocationLabel,
      },
    ]
  }

  function syncLines(): void {
    const c = container.value
    if (!c) {
      if (loosePackItem.value) syncLooseLine()
      return
    }
    const prevById = new Map(lines.value.map((line) => [line.id, line]))
    const rebuilt = buildMaterialJourneyReturnCrateLines(c, {
      packItems: options.packItems.value,
      containerItemsByContainerId: options.containerItemsByContainerId.value,
      packQuantityCtx: options.packQuantityCtx.value,
      shellPackItemForContainer: options.shellPackItemForContainer,
      materialFallbackLabel: t('common.material'),
      issues: options.issues.value,
    })
    lines.value = rebuilt.map((line) => {
      const prev = prevById.get(line.id)
      if (!prev || line.isDone) return line
      const cap = returnCrateLineInputCap(line.ordered, line.max)
      const qty = Math.min(Math.max(0, prev.qty), cap)
      const wetQty = Math.min(Math.max(0, prev.wetQty), qty)
      return {
        ...line,
        included: prev.included,
        qty,
        wetEnabled: prev.wetEnabled,
        wetQty,
        wetHung: wetQty > 0 ? prev.wetHung : null,
        wetDryingStorageAddressId: prev.wetDryingStorageAddressId,
        wetDryingRackId: prev.wetDryingRackId,
        wetDryingSlotId: prev.wetDryingSlotId,
        wetDryingLocationLabel: prev.wetDryingLocationLabel,
      }
    })
  }

  function wetDispositionFromLine(line: ReturnCrateLineEdit): PackItemWetDisposition | null {
    if (!line.wetEnabled || line.wetQty < 1) return null
    const hung = line.wetHung === true
    const body: PackItemWetDisposition = {
      quantity_wet: line.wetQty,
      wet_hung: line.wetHung ?? false,
    }
    if (hung) {
      body.wet_drying_storage_address_id = line.wetDryingStorageAddressId || null
      body.wet_drying_rack_id = line.wetDryingRackId || null
      body.wet_drying_slot_id = line.wetDryingSlotId || null
      body.wet_drying_location_label = line.wetDryingLocationLabel || null
    }
    return body
  }

  async function applyWetForLine(containerId: string, line: ReturnCrateLineEdit): Promise<void> {
    const body = wetDispositionFromLine(line)
    if (!body) return
    if (line.kind === 'shell') {
      const shell = options.shellPackItemForContainer(containerId)
      if (!shell) return
      await postPackItemWet(options.activityId.value, shell.id, body)
      return
    }
    if (line.containerItemId) {
      await postContainerItemWet(
        options.activityId.value,
        containerId,
        line.containerItemId,
        body as ContainerItemWetDisposition,
      )
      return
    }
    if (line.materialItemId) {
      const pi = options.packItems.value.find((p) => p.materialItemId === line.materialItemId)
      if (pi) await postPackItemWet(options.activityId.value, pi.id, body)
    }
  }

  watch(
    () => [open.value, container.value?.id, options.issues.value, options.packItems.value] as const,
    ([isOpen, containerId]) => {
      if (!isOpen || !containerId || !container.value) return
      syncLines()
    },
  )

  function openFor(c: ActivityPackContainer): void {
    loosePackItem.value = null
    container.value = c
    open.value = true
  }

  function openForLoose(
    pi: ActivityPackItem,
    maxReturnQty: number,
    opts?: { preselectWet?: boolean },
  ): void {
    container.value = null
    loosePackItem.value = pi
    let line = buildLooseReturnLine(pi, maxReturnQty)
    if (opts?.preselectWet && !line.wetEnabled) {
      const cap = Math.max(1, line.qty > 0 ? line.qty : line.max || 1)
      line = {
        ...line,
        wetEnabled: true,
        wetQty: cap,
        wetHung: false,
        wetDryingStorageAddressId: '',
        wetDryingRackId: '',
        wetDryingSlotId: '',
        wetDryingLocationLabel: '',
      }
    }
    lines.value = [line]
    open.value = true
  }

  function close(): void {
    open.value = false
    container.value = null
    loosePackItem.value = null
    lines.value = []
    pendingReturnCrateBatch.value = null
  }

  function clearPendingConsumableReturn(): void {
    pendingConsumableReturn.value = null
    pendingReturnCrateBatch.value = null
  }

  function beginConsumableReturnForPackItem(item: ActivityPackItem, returnQty: number): void {
    pendingConsumableReturn.value = { kind: 'pack-item', packItemId: item.id, qty: returnQty }
  }

  function beginConsumableReturnForContainerLine(
    containerId: string,
    containerItemId: string,
    returnQty: number,
  ): void {
    pendingConsumableReturn.value = {
      kind: 'container-line',
      containerId,
      containerItemId,
      qty: returnQty,
    }
  }

  async function executeLineReturn(containerId: string, ci: ActivityPackContainerItem, qty: number): Promise<void> {
    const pi = options.packItems.value.find((p) => p.materialItemId === ci.material_item_id)
    if (!pi) throw new Error(t('activities.packList.toastNoPackLine'))
    await postMovePackItem(options.activityId.value, pi.id, { stage: 'returned', quantity: qty })
    const cap = Math.max(ci.quantity_issued ?? 0, ci.quantity_packed ?? 0)
    await updateActivityPackContainerItem(options.activityId.value, containerId, ci.id, {
      quantity_returned: Math.min((ci.quantity_returned ?? 0) + qty, cap > 0 ? cap : qty),
    })
  }

  async function continueReturnCrateBatch(): Promise<void> {
    const job = pendingReturnCrateBatch.value
    if (!job) return

    while (job.remaining.length > 0) {
      const step = job.remaining[0]
      if (step.kind === 'shell') {
        job.remaining.shift()
        const shell = options.shellPackItemForContainer(job.containerId)
        if (shell && step.qty > 0) {
          await postMovePackItem(options.activityId.value, shell.id, {
            stage: 'returned',
            quantity: step.qty,
          })
        }
        const shellLine = lines.value.find((l) => l.kind === 'shell' || l.id === 'shell')
        if (shellLine) await applyWetForLine(job.containerId, shellLine)
        continue
      }

      if (step.kind === 'line' && step.containerItemId) {
        const ci = (options.containerItemsByContainerId.value[job.containerId] ?? []).find(
          (row) => row.id === step.containerItemId,
        )
        if (!ci) {
          job.remaining.shift()
          continue
        }
        job.remaining.shift()
        await executeLineReturn(job.containerId, ci, step.qty)
        const line = lines.value.find((l) => l.containerItemId === step.containerItemId)
        if (line) await applyWetForLine(job.containerId, line)
        continue
      }

      job.remaining.shift()
    }

    // Zeilen ohne Move (bereits retour / wet-only Update)
    for (const line of lines.value) {
      if (line.isDone && line.wetEnabled && line.wetQty > 0) {
        await applyWetForLine(job.containerId, line)
      }
    }

    pendingReturnCrateBatch.value = null
    options.packItems.value = await getPackItems(options.activityId.value)
    await options.reload()
    toast.success(t('activities.packList.toastReturnContainer'))
    close()
  }

  async function fulfillPendingConsumableReturn(): Promise<void> {
    const pending = pendingConsumableReturn.value
    if (!pending) return
    pendingConsumableReturn.value = null

    if (pending.kind === 'pack-item') {
      const item = options.packItems.value.find((p) => p.id === pending.packItemId)
      if (!item) return
      const returnQty = resolveConsumableReturnQty(
        item,
        options.packQuantityCtx.value,
        options.issues.value,
        pending.qty,
      )
      if (returnQty <= 0) {
        toast.info(t('activities.packList.toastConsumableAllUsedNothingToReturn'))
        syncLines()
        await continueReturnCrateBatch()
        return
      }
      await postMovePackItem(options.activityId.value, item.id, {
        stage: 'returned',
        quantity: returnQty,
      })
      options.packItems.value = await getPackItems(options.activityId.value)
      await options.reload()
      syncLines()
      await continueReturnCrateBatch()
      return
    }

    const ci = (options.containerItemsByContainerId.value[pending.containerId] ?? []).find(
      (row) => row.id === pending.containerItemId,
    )
    if (!ci) return
    const rem = computeContainerLineRemainingReturn(
      ci,
      options.packQuantityCtx.value,
      pending.containerId,
    )
    const returnQty = Math.min(pending.qty, rem)
    if (returnQty <= 0) {
      toast.info(t('activities.packList.toastConsumableAllUsedNothingToReturn'))
      syncLines()
      await continueReturnCrateBatch()
      return
    }
    await executeLineReturn(pending.containerId, ci, returnQty)
    const batch = pendingReturnCrateBatch.value
    if (
      batch?.remaining[0]?.kind === 'line' &&
      batch.remaining[0].containerItemId === pending.containerItemId
    ) {
      batch.remaining.shift()
    }
    options.packItems.value = await getPackItems(options.activityId.value)
    await options.reload()
    syncLines()
    await continueReturnCrateBatch()
  }

  async function submitLooseReturn(): Promise<void> {
    const pi = loosePackItem.value
    if (!pi || submitDisabled.value) return
    const line = lines.value[0]
    if (!line) return

    submitting.value = true
    try {
      const qty = line.isDone ? 0 : line.included ? line.qty : 0
      if (qty > 0) {
        await postMovePackItem(options.activityId.value, pi.id, {
          stage: 'returned',
          quantity: qty,
        })
      }
      await applyWetForLine('', line)
      options.packItems.value = await getPackItems(options.activityId.value)
      await options.reload()
      toast.success(t('activities.packList.toastReturnContainer'))
      close()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      submitting.value = false
    }
  }

  async function submit(): Promise<void> {
    if (loosePackItem.value) {
      await submitLooseReturn()
      return
    }
    const c = container.value
    if (!c || submitDisabled.value) return

    const steps = materialJourneyReturnCrateBatchSteps(lines.value)
    if (steps.length === 0) {
      if (!materialJourneyReturnCrateCanCompleteWithoutMoves(lines.value)) return
      // Wet-only Updates auf bereits retournierten Zeilen
      submitting.value = true
      try {
        for (const line of lines.value) {
          if (line.wetEnabled && line.wetQty > 0) {
            await applyWetForLine(c.id, line)
          }
        }
        options.packItems.value = await getPackItems(options.activityId.value)
        await options.reload()
        close()
        toast.success(t('activities.packList.toastReturnCrateCheckComplete'))
      } catch (e) {
        toast.error(e instanceof Error ? e.message : String(e))
      } finally {
        submitting.value = false
      }
      return
    }

    submitting.value = true
    try {
      pendingReturnCrateBatch.value = { containerId: c.id, remaining: steps }
      await continueReturnCrateBatch()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      submitting.value = false
    }
  }

  async function submitReturnAll(containerId: string): Promise<void> {
    submitting.value = true
    try {
      await returnAllPackContainerItems(options.activityId.value, containerId)
      await options.reload()
      toast.success(t('activities.packList.toastReturnContainer'))
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      submitting.value = false
    }
  }

  function reportReturnCrateConsumption(materialItemId: string): {
    packItem: ActivityPackItem
    returnQty?: number
  } | null {
    const c = container.value
    if (!c) return null
    const pi = options.packItems.value.find((p) => p.materialItemId === materialItemId)
    if (!pi) return null

    const line = lines.value.find((l) => l.materialItemId === materialItemId && l.isConsumable)
    const returnQty =
      line?.included && line.qty >= 0 ? line.qty : line?.max && line.max > 0 ? line.max : 0
    if (line?.containerItemId) {
      beginConsumableReturnForContainerLine(c.id, line.containerItemId, returnQty)
    } else {
      beginConsumableReturnForPackItem(pi, returnQty)
    }
    return { packItem: pi, returnQty }
  }

  /** Übermenge als Extra: issued (+ packed falls nötig) anheben, damit Retour die volle Menge buchen kann. */
  async function bookReturnCrateExtra(
    materialItemId: string,
    surplusQty: number,
    lineId: string,
  ): Promise<boolean> {
    const c = container.value
    if (!c || surplusQty < 1) return false
    const pi = options.packItems.value.find((p) => p.materialItemId === materialItemId)
    if (!pi) {
      toast.error(t('activities.packList.toastNoPackLine'))
      return false
    }

    submitting.value = true
    try {
      const newIssued = (pi.quantityIssued ?? 0) + surplusQty
      const newPacked = Math.max(pi.quantityPacked ?? 0, newIssued)
      await patchActivityPackItem(options.activityId.value, pi.id, {
        quantity_issued: newIssued,
        quantity_packed: newPacked,
      })

      const targetLine = lines.value.find((l) => l.id === lineId)
      if (targetLine?.containerItemId) {
        const ci = (options.containerItemsByContainerId.value[c.id] ?? []).find(
          (row) => row.id === targetLine.containerItemId,
        )
        if (ci) {
          const ciIssued = (ci.quantity_issued ?? 0) + surplusQty
          const ciPacked = Math.max(ci.quantity_packed ?? 0, ciIssued)
          await updateActivityPackContainerItem(options.activityId.value, c.id, ci.id, {
            quantity_issued: ciIssued,
            quantity_packed: ciPacked,
          })
        }
      }

      options.packItems.value = await getPackItems(options.activityId.value)
      await options.reload()
      syncLines()
      toast.success(t('activities.packList.returnCrateModalExtraBooked', { n: surplusQty }))
      return true
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
      return false
    } finally {
      submitting.value = false
    }
  }

  return {
    open,
    container,
    loosePackItem,
    sheetLabel,
    lines,
    partition,
    submitting,
    submitDisabled,
    pendingConsumableReturn,
    openFor,
    openForLoose,
    close,
    submit,
    submitReturnAll,
    syncLines,
    beginConsumableReturnForPackItem,
    beginConsumableReturnForContainerLine,
    fulfillPendingConsumableReturn,
    clearPendingConsumableReturn,
    reportReturnCrateConsumption,
    bookReturnCrateExtra,
  }
}
