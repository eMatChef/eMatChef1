<script setup lang="ts">
import { computed, inject, unref, type MaybeRef } from 'vue'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackConfirmedPackedContainerCard from '@/components/activities/PackConfirmedPackedContainerCard.vue'
import PackEventReturnContainerCard from '@/components/activities/PackEventReturnContainerCard.vue'
import type { PackContainerCardMode } from '@/components/activities/packStepUi'
import type { PackStage } from '@/components/activities/packStageQuantities'
import { packCrateContainerUseSubsections } from '@/components/activities/packWorkflowRules'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import PackWarehouseIssueContainerCard from '@/components/activities/PackWarehouseIssueContainerCard.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackStepContainerCard' })

const props = defineProps<{
  container: ActivityPackContainer
  mode: PackContainerCardMode
  stageRightLabel?: string
  containerDomIdPrefix?: string
  showStorageLocation?: boolean
  useSubsections?: boolean
}>()

const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

const shellPackItem = computed((): ActivityPackItem | undefined => {
  const fn = ctx.shellPackItemForContainer as ((id: string) => ActivityPackItem | undefined) | undefined
  return fn?.(props.container.id)
})

/** Phys.-Kombi rechts «Gepackt»: Accordion mit Lager-Vorlage wie Gepackt→Event */
const useShellComboPackedCard = computed(
  () =>
    props.mode === 'confirmed_packed_target' &&
    shellPackItem.value?.materialType === 'physical_combo',
)

const containerUseSubsections = computed(() => {
  if (props.useSubsections != null) return props.useSubsections
  const profile =
    (unref(ctx.packWorkflowProfile as MaybeRef<PackWorkflowProfile> | undefined) as
      | PackWorkflowProfile
      | undefined) ?? 'logistics'
  const stage = unref(ctx.activePackStage as MaybeRef<PackStage>) as PackStage
  return packCrateContainerUseSubsections(stage, profile)
})
</script>

<template>
  <PackWarehouseIssueContainerCard
    v-if="useShellComboPackedCard"
    :container="container"
    variant="shell"
    :shell-pack-item="shellPackItem"
    :stage-right-label="stageRightLabel ?? ''"
    :show-storage-location="showStorageLocation"
    :use-subsections="containerUseSubsections"
    container-dom-id-prefix="pack-container-packed-"
  />
  <PackConfirmedPackedContainerCard
    v-else-if="mode === 'confirmed_packed_target'"
    :container="container"
  />
  <PackWarehouseIssueContainerCard
    v-else-if="
      mode === 'warehouse_issue' ||
      mode === 'warehouse_issue_mirror' ||
      mode === 'at_event_return_mirror'
    "
    :container="container"
    :stage-right-label="stageRightLabel ?? ''"
    :container-dom-id-prefix="containerDomIdPrefix"
    :use-subsections="containerUseSubsections"
    :show-storage-location="showStorageLocation"
  />
  <PackEventReturnContainerCard
    v-else-if="mode === 'at_event_return'"
    :container="container"
  />
</template>
