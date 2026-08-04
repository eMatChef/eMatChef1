import { describe, expect, it } from 'vitest'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  buildMaterialJourneyTasks,
  isMaterialJourneyCrateKind,
  type MaterialJourneyTaskBuildContext,
} from '@/components/activities/materialJourneyTaskList'
import { isVirtualComboTogetherContainer } from '@/components/activities/packWorkflowRules'
import type { PackWorkflowContainerContext, PackWorkflowListContext } from '@/components/activities/packWorkflowRules'

function basePackItem(overrides: Partial<ActivityPackItem> & Pick<ActivityPackItem, 'id' | 'materialItemId'>): ActivityPackItem {
  return {
    materialName: 'Teil',
    quantityOrdered: 1,
    quantityPacked: 1,
    quantityIssued: 0,
    quantityReturned: 0,
    quantityStored: 0,
    quantityTransportTo: 0,
    quantityTransportBack: 0,
    materialType: 'physical',
    isConsumable: false,
    isJsMaterial: false,
    categoryName: null,
    ...overrides,
  } as ActivityPackItem
}

describe('virtual_crate Journey (1d C1)', () => {
  it('detects logical together containers without batch', () => {
    const c = {
      id: 'pc1',
      label: 'Sarasani',
      source_activity_item_id: 'ai_parent',
      container_batch_id: null,
    } as ActivityPackContainer
    expect(isVirtualComboTogetherContainer(c)).toBe(true)
  })

  it('emits virtual_crate row and hides child pack items from loose list', () => {
    const container = {
      id: 'pc_set',
      label: 'Sarasani 39',
      source_activity_item_id: 'ai_parent',
      container_batch_id: null,
    } as ActivityPackContainer

    const child = basePackItem({
      id: 'pi_child',
      materialItemId: 'mat_child',
      materialName: 'Plane',
      quantityOrdered: 1,
      quantityPacked: 0,
    })

    const containerItems: ActivityPackContainerItem[] = [
      {
        id: 'pci1',
        material_item_id: 'mat_child',
        material_name: 'Plane',
        quantity_packed: 1,
        quantity_issued: 0,
        quantity_returned: 0,
        quantity_stored: 0,
        quantity_transport_to: 0,
        quantity_transport_back: 0,
      } as ActivityPackContainerItem,
    ]

    const listCtx = {
      stage: 'packed_at_event',
      profile: 'quick',
      showPackContainersUi: true,
      packContainers: [container],
      hasPackContainers: true,
      effectiveStageLeftQty: () => 1,
      getStageLeftQty: () => 1,
      getStageRightQty: () => 0,
      looseQtyForPackItem: () => 0,
      consumableShowsZeroOnStageLeft: () => false,
      consumableConsumptionRemaining: () => 0,
      consumablePhysicalReturnMax: () => 0,
      looseQtyStillAtEventForReturn: () => 0,
      pendingStoreLooseQtyForPackItem: () => 0,
      returnedLooseQtyForPackItem: () => 0,
      storedLooseQtyForPackItem: () => 0,
      storedShellLooseQtyForPackItem: () => 0,
      looseQtyOnRightMirror: () => 0,
      looseTransportBackOnRight: () => 0,
      notTakenQtyForReturn: () => 0,
      notTakenToEventQtyForMaterial: () => 0,
      consumableStillOnlyInCrateAtReturn: () => false,
      consumableBookedConsumptionQty: () => 0,
      isIndividuallyStorableCrateShell: () => false,
      containerReturnedAsWhole: () => false,
      qtyInContainersForItem: () => 1,
      issuedQtyInContainersForMaterial: () => 0,
      transportToQtyInContainersForMaterial: () => 0,
      transportBackQtyInContainersForMaterial: () => 0,
      isConsumablePackLine: () => false,
    } as unknown as PackWorkflowListContext

    const containerCtx = {
      ...listCtx,
      stageLeftItemIds: new Set<string>(),
      getLeftQtyForMerge: () => 0,
      shellPackItemForContainer: () => undefined,
      containerHasPackedContent: () => true,
      containerHasIssuedAtEvent: () => false,
      containerLineRemainingAtForwardStage: (ci: ActivityPackContainerItem) =>
        Math.max(0, (ci.quantity_packed ?? 0) - (ci.quantity_issued ?? 0)),
      containerReturnableUnits: () => 0,
      containerStoreUnits: () => 0,
      containerTransportBackReturnableUnits: () => 0,
      containerItemsForContainer: () => containerItems,
    } as unknown as PackWorkflowContainerContext

    const ctx: MaterialJourneyTaskBuildContext = {
      listCtx,
      containerCtx,
      stageLeftItems: [child],
      packStage: 'packed_at_event',
      packContainers: [container],
      maxForwardQty: () => 0,
      containerIssueableUnits: () => 1,
      containerActionableUnits: () => 1,
      containerContentActionableUnits: () => 1,
      canMoveItem: () => false,
      canOpenSheet: true,
      formatCrateLineCount: (n) => `${n} Teile`,
      shellPackItemForContainer: () => undefined,
    }

    const rows = buildMaterialJourneyTasks([child], ctx)
    const setRow = rows.find((r) => r.container?.id === 'pc_set')
    expect(setRow?.kind).toBe('virtual_crate')
    expect(isMaterialJourneyCrateKind(setRow!.kind)).toBe(true)
    expect(setRow?.badges).toContain('virtual_crate')
    expect(rows.some((r) => r.kind === 'loose' && r.packItem?.id === 'pi_child')).toBe(false)
  })
})
