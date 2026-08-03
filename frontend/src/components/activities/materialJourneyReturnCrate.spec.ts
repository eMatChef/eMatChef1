import { describe, expect, it } from 'vitest'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ReturnCrateLineEdit } from '@/components/activities/PackReturnCrateModal.vue'
import {
  buildMaterialJourneyReturnCrateLines,
  materialJourneyReturnCrateBatchSteps,
  materialJourneyReturnCrateContentStillOpen,
} from '@/components/activities/materialJourneyReturnCrate'
import type { PackQuantityContext } from '@/components/activities/packStageQuantityLayer'

function line(partial: Partial<ReturnCrateLineEdit> & Pick<ReturnCrateLineEdit, 'id' | 'kind'>): ReturnCrateLineEdit {
  return {
    placement: 'in_crate',
    materialItemId: 'm1',
    materialName: 'Fackeln',
    expectedQty: 5,
    ordered: 10,
    consumed: 5,
    loss: 0,
    repair: 0,
    max: 5,
    issued: 5,
    returnedAlready: 0,
    included: true,
    qty: 5,
    isExtra: false,
    isConsumable: true,
    consumptionDone: true,
    consumptionOpen: 0,
    isDone: false,
    ...partial,
  }
}

describe('materialJourneyReturnCrate', () => {
  it('includes shell together with open content lines', () => {
    const container = { id: 'c1', label: 'Rakokiste' } as ActivityPackContainer
    const shell: ActivityPackItem = {
      id: 'shell-pi',
      materialItemId: 'shell-mid',
      materialName: 'Rakokiste',
      quantityIssued: 1,
      quantityReturned: 0,
      quantityOrdered: 1,
      quantityPacked: 1,
    } as ActivityPackItem

    const packQuantityCtx = {
      stage: 'at_event_returned',
      isNonActionableContainerLine: () => false,
      containerLinePhysicalReturnRemaining: () => 5,
    } as unknown as PackQuantityContext

    const lines = buildMaterialJourneyReturnCrateLines(container, {
      packItems: [
        {
          id: 'pi1',
          materialItemId: 'mid-f',
          materialName: 'Fackeln',
          isConsumable: true,
          quantityOrdered: 10,
          quantityIssued: 5,
        } as ActivityPackItem,
      ],
      containerItemsByContainerId: {
        c1: [
          {
            id: 'ci1',
            material_item_id: 'mid-f',
            material_name: 'Fackeln',
            quantity_packed: 10,
            quantity_issued: 5,
            quantity_returned: 0,
          },
        ],
      },
      packQuantityCtx,
      shellPackItemForContainer: () => shell,
      materialFallbackLabel: 'Material',
      issues: [],
    })

    expect(lines.some((l) => l.kind === 'line' && l.max === 5)).toBe(true)
    expect(lines.some((l) => l.kind === 'shell' && l.max === 1)).toBe(true)
  })

  it('books shell only when all open content is fully selected', () => {
    const content = line({ id: 'ci1', kind: 'line', max: 5, qty: 5, included: true })
    const shell = line({
      id: 'shell',
      kind: 'shell',
      placement: 'shell',
      materialName: 'Rakokiste',
      max: 1,
      qty: 1,
      included: true,
    })

    expect(materialJourneyReturnCrateContentStillOpen([content, shell])).toBe(false)
    expect(materialJourneyReturnCrateBatchSteps([content, shell]).map((s) => s.kind)).toEqual([
      'line',
      'shell',
    ])

    const partial = line({ id: 'ci1', kind: 'line', max: 5, qty: 5, included: false })
    expect(materialJourneyReturnCrateContentStillOpen([partial, shell])).toBe(true)
    expect(materialJourneyReturnCrateBatchSteps([partial, shell]).map((s) => s.kind)).toEqual([])
  })
})
