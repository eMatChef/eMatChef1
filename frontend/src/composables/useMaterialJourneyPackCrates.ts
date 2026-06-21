import { computed, ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import { createActivityPackContainer, deleteActivityPackContainer } from '@/api/activityContainers'
import { getContainerBatches, type ContainerBatch } from '@/api/storageLocations'
import type { PackStage } from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { showPackContainersForProfile } from '@/components/activities/packWorkflowProfile'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

function containerBatchOptionLabel(
  batch: ContainerBatch,
  t: (key: string, ...args: unknown[]) => string,
): string {
  const base = (batch.display_label || batch.label || batch.material_name || t('activities.common.crate')).trim()
  if (batch.storage_empty === true) return `${base} ${t('activities.packList.batchEmptySuffix')}`
  if (batch.storage_empty === false) return `${base} ${t('activities.packList.batchWithContentSuffix')}`
  return base
}

export function useMaterialJourneyPackCrates(options: {
  departmentId: Ref<string>
  activityId: Ref<string>
  packContainers: Ref<ActivityPackContainer[]>
  journeyStep: Ref<JourneyStep>
  packStage: Ref<PackStage>
  profile: Ref<PackWorkflowProfile>
  listEditable: Ref<boolean>
  canManageMaterials: Ref<boolean>
  reload: () => Promise<void>
  selectPackCrate: (containerId: string) => void
  clearSelectedPackCrate: () => void
  selectedPackCrateId: Ref<string | null>
  closeCrateSheet: () => void
}) {
  const { t, locale } = useI18n()
  const toast = useToast()
  const { confirm: confirmDialog } = useConfirm()

  const addModalOpen = ref(false)
  const stockContainerBatches = ref<ContainerBatch[]>([])
  const stockBatchesLoading = ref(false)
  const selectedStockBatchId = ref('')
  const containerMutationLoading = ref(false)

  const showAddPackCrateButton = computed(
    () =>
      options.journeyStep.value === 'pack' &&
      options.listEditable.value &&
      options.canManageMaterials.value &&
      showPackContainersForProfile(options.profile.value, options.packStage.value),
  )

  const availableStockBatches = computed(() => {
    const used = new Set(
      options.packContainers.value.map((c) => c.container_batch_id).filter((id): id is string => !!id),
    )
    const rows = stockContainerBatches.value.filter((b) => !used.has(b.id))
    return [...rows].sort((a, b) => {
      const score = (x: ContainerBatch) => (x.storage_empty === true ? 0 : x.storage_empty === false ? 1 : 2)
      const d = score(a) - score(b)
      if (d !== 0) return d
      const la = (a.display_label || a.label || a.material_name || '').toString()
      const lb = (b.display_label || b.label || b.material_name || '').toString()
      return la.localeCompare(lb, locale.value)
    })
  })

  const addContainerBatchOptions = computed(() =>
    availableStockBatches.value.map((b) => ({
      id: b.id,
      label: containerBatchOptionLabel(b, t),
    })),
  )

  const canSubmitAddContainer = computed(
    () => !stockBatchesLoading.value && !!selectedStockBatchId.value.trim(),
  )

  async function loadStockContainerBatches(): Promise<void> {
    const deptId = options.departmentId.value.trim()
    if (!deptId) {
      stockContainerBatches.value = []
      return
    }
    stockBatchesLoading.value = true
    try {
      stockContainerBatches.value = await getContainerBatches(deptId, {
        activityId: options.activityId.value,
      })
    } catch {
      stockContainerBatches.value = []
      toast.error(t('activities.packList.toastStockBatchesFailed'))
    } finally {
      stockBatchesLoading.value = false
    }
  }

  async function openAddPackCrateModal(): Promise<void> {
    selectedStockBatchId.value = ''
    addModalOpen.value = true
    await loadStockContainerBatches()
    if (availableStockBatches.value.length === 1) {
      selectedStockBatchId.value = availableStockBatches.value[0].id
    }
  }

  async function submitAddPackCrate(): Promise<void> {
    if (!canSubmitAddContainer.value) return
    const activityId = options.activityId.value
    if (!activityId) return

    const batch = stockContainerBatches.value.find((b) => b.id === selectedStockBatchId.value)
    const containerBatchId = selectedStockBatchId.value
    const raw =
      batch?.display_label?.trim() ||
      [batch?.serial_number, batch?.label || batch?.material_name].filter(Boolean).join(' – ') ||
      batch?.material_name ||
      t('activities.packList.crateTargetFallback')
    const label = raw.slice(0, 120)

    containerMutationLoading.value = true
    try {
      const created = await createActivityPackContainer(activityId, {
        label,
        container_batch_id: containerBatchId,
      })
      await options.reload()
      addModalOpen.value = false
      options.selectPackCrate(created.id)
      toast.success(t('activities.packList.toastContainerAdded'))
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } }; message?: string }
      toast.error(e.response?.data?.error || e.message || t('activities.packList.toastContainerAddFailed'))
    } finally {
      containerMutationLoading.value = false
    }
  }

  async function confirmDeletePackContainer(container: ActivityPackContainer): Promise<void> {
    const ok = await confirmDialog({
      title: t('activities.packList.confirmDeleteTitle'),
      message: t('activities.packList.confirmDeleteMessage', { label: container.label }),
      confirmText: t('common.delete'),
      cancelText: t('common.cancel'),
      variant: 'danger',
    })
    if (!ok) return

    const activityId = options.activityId.value
    if (!activityId) return

    containerMutationLoading.value = true
    try {
      await deleteActivityPackContainer(activityId, container.id)
      if (options.selectedPackCrateId.value === container.id) {
        options.clearSelectedPackCrate()
      }
      options.closeCrateSheet()
      await options.reload()
      toast.success(t('activities.packList.toastContainerDeleted'))
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } }; message?: string }
      toast.error(e.response?.data?.error || e.message || t('activities.packList.toastDeleteFailed'))
    } finally {
      containerMutationLoading.value = false
    }
  }

  return {
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
  }
}
