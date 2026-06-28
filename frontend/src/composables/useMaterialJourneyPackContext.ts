import { computed, type Ref } from 'vue'
import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackStage } from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { createMaterialJourneyPackContextState } from '@/composables/materialJourneyPackContextState'

export function useMaterialJourneyPackContext(options: {
  packItems: Ref<ActivityPackItem[]>
  packContainers: Ref<ActivityPackContainer[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  packStage: Ref<PackStage>
  profile: Ref<PackWorkflowProfile>
  issues?: Ref<ActivityIssueReportRow[]>
}) {
  const state = computed(() =>
    createMaterialJourneyPackContextState({
      packItems: options.packItems.value,
      packContainers: options.packContainers.value,
      containerItemsByContainerId: options.containerItemsByContainerId.value,
      packStage: options.packStage.value,
      profile: options.profile.value,
      issues: options.issues?.value ?? [],
    }),
  )

  return {
    packListCtx: computed(() => state.value.packListCtx),
    packContainerCtx: computed(() => state.value.packContainerCtx),
    packIssueForwardMax: (pi: ActivityPackItem) => state.value.packIssueForwardMax(pi),
    effectiveStageLeftQty: (pi: ActivityPackItem) => state.value.effectiveStageLeftQty(pi),
    stageLeftQty: (pi: ActivityPackItem) => state.value.stageLeftQty(pi),
    stageRightQty: (pi: ActivityPackItem) => state.value.stageRightQty(pi),
    stageLeftItems: computed(() => state.value.stageLeftItems),
    shellPackItemForContainer: (containerId: string) => state.value.shellPackItemForContainer(containerId),
    containerIssueableUnits: (containerId: string) => state.value.containerIssueableUnits(containerId),
    containerReturnableUnits: (containerId: string) => state.value.containerReturnableUnits(containerId),
    containerStoreUnits: (containerId: string) => state.value.containerStoreUnits(containerId),
    containerActionableUnits: (containerId: string) => state.value.containerActionableUnits(containerId),
    containerContentActionableUnits: (containerId: string) =>
      state.value.containerContentActionableUnits(containerId),
    containerLineRemainingStore: (ci: ActivityPackContainerItem) =>
      state.value.containerLineRemainingStore(ci),
    containerInnerPendingStoreUnits: (containerId: string) =>
      state.value.containerInnerPendingStoreUnits(containerId),
    containerShellPendingStoreQty: (containerId: string) =>
      state.value.containerShellPendingStoreQty(containerId),
    containerShellOnlyPendingUnpack: (containerId: string) =>
      state.value.containerShellOnlyPendingUnpack(containerId),
    unpackAccountingInput: computed(() => state.value.unpackAccountingInput),
    packQuantityCtx: computed(() => state.value.packQuantityCtx),
    packCrateLabelsForPackItem: (pi: ActivityPackItem) => state.value.packCrateLabelsForPackItem(pi),
    qtyInPackCrateForPackItem: (pi: ActivityPackItem) => state.value.qtyInPackCrateForPackItem(pi),
    packCrateAssignQtyForItem: (pi: ActivityPackItem) => state.value.packCrateAssignQtyForItem(pi),
  }
}
