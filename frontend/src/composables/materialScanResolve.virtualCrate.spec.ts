import { describe, expect, it } from 'vitest'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PublicLookupBatchResponse } from '@/api/public/publicLookup'
import { resolveMaterialBatchScan } from '@/composables/materialScanResolve'
import type { PackWorkflowListContext } from '@/components/activities/packWorkflowRules'

describe('materialScanResolve in_virtual_crate (1d C2)', () => {
  it('opens virtual together container for child material (not phys combo)', () => {
    const container = {
      id: 'pc_set',
      label: 'Sarasani 39',
      source_activity_item_id: 'ai_parent',
      container_batch_id: null,
    } as ActivityPackContainer

    const child = {
      id: 'pi_child',
      materialItemId: 'mat_child',
      materialName: 'Plane',
      quantityOrdered: 1,
      quantityPacked: 1,
      quantityIssued: 0,
      materialType: 'physical',
      isConsumable: false,
      isJsMaterial: false,
    } as ActivityPackItem

    const items: ActivityPackContainerItem[] = [
      {
        id: 'pci1',
        material_item_id: 'mat_child',
        material_name: 'Plane',
        quantity_packed: 1,
      } as ActivityPackContainerItem,
    ]

    const listCtx = {
      profile: 'quick',
      showPackContainersUi: true,
      getStageLeftQty: () => 1,
      getStageRightQty: () => 0,
      effectiveStageLeftQty: () => 1,
      looseQtyForPackItem: () => 0,
    } as unknown as PackWorkflowListContext

    const lookup = {
      material: { id: 'mat_child', name: 'Plane', is_container: false },
      batch: { id: 'b1', is_container: false, status: 'available' },
    } as PublicLookupBatchResponse

    const result = resolveMaterialBatchScan(lookup, {
      activityId: 'act1',
      journeyStep: 'issue',
      listCtx,
      packItems: [child],
      packContainers: [container],
      containerItemsByContainerId: { pc_set: items },
      listEditable: true,
    })

    expect(result.type).toBe('in_virtual_crate')
    expect(result.container?.id).toBe('pc_set')
    expect(result.packItem).toBeUndefined()
  })
})
