import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
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
  materialJourneyReturnCrateSubmitDisabled,
} from '@/components/activities/materialJourneyReturnCrate'
import type { PackQuantityContext } from '@/components/activities/packStageQuantityLayer'
import { useToast } from '@/composables/useToast'

export function useMaterialJourneyReturnCrate(options: {
  activityId: Ref<string>
  packItems: Ref<ActivityPackItem[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  packQuantityCtx: Ref<PackQuantityContext>
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  reload: () => Promise<void>
}) {
  const { t } = useI18n()
  const toast = useToast()

  const open = ref(false)
  const container = ref<ActivityPackContainer | null>(null)
  const lines = ref<ReturnCrateLineEdit[]>([])
  const submitting = ref(false)

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

  watch(
    () => [open.value, container.value?.id] as const,
    ([isOpen, containerId]) => {
      if (!isOpen || !containerId || !container.value) return
      lines.value = buildMaterialJourneyReturnCrateLines(container.value, {
        packItems: options.packItems.value,
        containerItemsByContainerId: options.containerItemsByContainerId.value,
        packQuantityCtx: options.packQuantityCtx.value,
        shellPackItemForContainer: options.shellPackItemForContainer,
        materialFallbackLabel: t('common.material'),
      })
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

  async function submit(): Promise<void> {
    const c = container.value
    if (!c || submitDisabled.value) return

    const steps = materialJourneyReturnCrateBatchSteps(lines.value)
    if (steps.length === 0) {
      close()
      toast.success(t('activities.packList.toastReturnCrateCheckComplete'))
      return
    }

    submitting.value = true
    try {
      for (const step of steps) {
        if (step.kind === 'shell') {
          const shell = options.shellPackItemForContainer(c.id)
          if (shell && step.qty > 0) {
            await postMovePackItem(options.activityId.value, shell.id, {
              stage: 'returned',
              quantity: step.qty,
            })
          }
          continue
        }
        if (step.kind === 'line' && step.containerItemId) {
          const ci = (options.containerItemsByContainerId.value[c.id] ?? []).find(
            (row) => row.id === step.containerItemId,
          )
          if (ci) await executeLineReturn(c.id, ci, step.qty)
        }
      }
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

  return {
    open,
    container,
    lines,
    partition,
    submitting,
    submitDisabled,
    openFor,
    close,
    submit,
    submitReturnAll,
  }
}
