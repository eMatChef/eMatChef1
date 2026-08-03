import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import {
  returnAllPackContainerItems,
  updateActivityPackContainerItem,
} from '@/api/activityContainers'
import { getPackItems, postMovePackItem, type ActivityPackItem } from '@/api/activityPackItems'
import type { ReturnCrateLineEdit, ReturnCratePartitionView } from '@/components/activities/PackReturnCrateModal.vue'
import {
  buildMaterialJourneyReturnCrateLines,
  materialJourneyReturnCrateBatchSteps,
  materialJourneyReturnCrateCanCompleteWithoutMoves,
  materialJourneyReturnCrateSubmitDisabled,
  type MaterialJourneyReturnCrateBatchStep,
} from '@/components/activities/materialJourneyReturnCrate'
import type { PackQuantityContext } from '@/components/activities/packStageQuantityLayer'
import {
  resolveConsumableReturnQty,
} from '@/utils/materialJourneyConsumable'
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

  function syncLines(): void {
    const c = container.value
    if (!c) return
    lines.value = buildMaterialJourneyReturnCrateLines(c, {
      packItems: options.packItems.value,
      containerItemsByContainerId: options.containerItemsByContainerId.value,
      packQuantityCtx: options.packQuantityCtx.value,
      shellPackItemForContainer: options.shellPackItemForContainer,
      materialFallbackLabel: t('common.material'),
      issues: options.issues.value,
    })
  }

  watch(
    () => [open.value, container.value?.id, options.issues.value, options.packItems.value] as const,
    ([isOpen, containerId]) => {
      if (!isOpen || !containerId || !container.value) return
      syncLines()
    },
  )

  function openFor(c: ActivityPackContainer): void {
    container.value = c
    open.value = true
  }

  function close(): void {
    open.value = false
    container.value = null
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
        const pi = options.packItems.value.find((p) => p.materialItemId === ci.material_item_id)
        if (pi?.isConsumable) {
          beginConsumableReturnForContainerLine(job.containerId, ci.id, step.qty)
          return
        }
        job.remaining.shift()
        await executeLineReturn(job.containerId, ci, step.qty)
        continue
      }

      job.remaining.shift()
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
    await executeLineReturn(pending.containerId, ci, pending.qty)
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

  async function submit(): Promise<void> {
    const c = container.value
    if (!c || submitDisabled.value) return

    const steps = materialJourneyReturnCrateBatchSteps(lines.value)
    if (steps.length === 0) {
      if (!materialJourneyReturnCrateCanCompleteWithoutMoves(lines.value)) return
      close()
      toast.success(t('activities.packList.toastReturnCrateCheckComplete'))
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
    const returnQty = line?.max && line.max > 0 ? line.max : 1
    if (line?.containerItemId) {
      beginConsumableReturnForContainerLine(c.id, line.containerItemId, returnQty)
    } else {
      beginConsumableReturnForPackItem(pi, returnQty)
    }
    return { packItem: pi, returnQty }
  }

  return {
    open,
    container,
    lines,
    partition,
    submitting,
    submitDisabled,
    pendingConsumableReturn,
    openFor,
    close,
    submit,
    submitReturnAll,
    syncLines,
    beginConsumableReturnForPackItem,
    beginConsumableReturnForContainerLine,
    fulfillPendingConsumableReturn,
    clearPendingConsumableReturn,
    reportReturnCrateConsumption,
  }
}
