<script setup lang="ts">
import { toRef, watch, type Ref } from 'vue'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackCrateShellForwardModal from '@/components/activities/PackCrateShellForwardModal.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import type { PackStage } from '@/components/activities/packStageQuantities'
import { useMaterialJourneyShellForward } from '@/composables/useMaterialJourneyShellForward'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'

const props = defineProps<{
  open: boolean
  packItem: ActivityPackItem | null
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  cratePeekMaps?: MaterialJourneyCratePeekMaps
  journeyStep: JourneyStep
  packStage: PackStage
  activityId: string
  departmentId: string
  canManageMaterials: boolean
  canSubmit: boolean
  maxForwardQty: number
  applyUpdatedItem: (item: ActivityPackItem) => void
  packMoveQtyCap?: (item: ActivityPackItem) => number
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  completed: [item: ActivityPackItem]
}>()

const {
  modalOpen,
  label,
  moveQty,
  sections,
  containerBatchId,
  looseStockByMid,
  stockLoading,
  historyReplenishByKey,
  historyPrefillHint,
  groupMode,
  checkOnly,
  submitError,
  submitting,
  emptyHint,
  initialLineReviews,
  close,
  openForPackItem,
  submit,
} = useMaterialJourneyShellForward({
  activityId: toRef(props, 'activityId'),
  departmentId: toRef(props, 'departmentId'),
  packItems: toRef(props, 'packItems'),
  packContainers: toRef(props, 'packContainers'),
  containerItemsByContainerId: toRef(props, 'containerItemsByContainerId'),
  cratePeekMaps: toRef(props, 'cratePeekMaps') as unknown as Ref<MaterialJourneyCratePeekMaps>,
  journeyStep: toRef(props, 'journeyStep'),
  packStage: toRef(props, 'packStage'),
  canManageMaterials: toRef(props, 'canManageMaterials'),
  applyUpdatedItem: (item) => props.applyUpdatedItem(item),
  packMoveQtyCap: props.packMoveQtyCap ? (item) => props.packMoveQtyCap!(item) : undefined,
})

let openGeneration = 0

watch(
  () => [props.open, props.packItem?.id, props.maxForwardQty] as const,
  async ([isOpen, packItemId, maxQty]) => {
    if (!isOpen || !packItemId || !props.packItem) {
      openGeneration += 1
      close()
      return
    }
    const gen = ++openGeneration
    await openForPackItem(props.packItem, maxQty, { kind: 'pack_move' })
    if (gen !== openGeneration) return
    if (!props.open || props.packItem?.id !== packItemId) {
      close()
    }
  },
)

function onCancel(): void {
  close()
  emit('update:open', false)
}

async function onSubmit(payload: Parameters<typeof submit>[0]): Promise<void> {
  const updated = await submit(payload)
  if (updated) {
    emit('completed', updated)
    emit('update:open', false)
  }
}
</script>

<template>
  <PackCrateShellForwardModal
    :open="open && modalOpen"
    :label="label"
    :move-qty="moveQty"
    :sections="sections"
    :department-id="departmentId"
    :container-batch-id="containerBatchId"
    :loose-stock-by-mid="looseStockByMid"
    :stock-loading="stockLoading"
    :history-replenish-by-key="historyReplenishByKey"
    :history-prefill-hint="historyPrefillHint"
    :can-report-issues="false"
    :group-mode="groupMode"
    :check-only="checkOnly"
    :submit-error="submitError"
    :submitting="submitting"
    :empty-hint="emptyHint"
    :embedded-issues-by-line-key="{}"
    :repack-issue-reviews="{}"
    :orphan-issues="[]"
    :initial-line-reviews="initialLineReviews"
    :pack-item-id="packItem?.id ?? null"
    @cancel="onCancel"
    @submit="onSubmit"
  />
</template>
