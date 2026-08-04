import { describe, expect, it } from 'vitest'
import { buildMaterialJourneyTasks } from '@/components/activities/materialJourneyTaskList'
import { createMaterialJourneyPackContextState } from '@/composables/materialJourneyPackContextState'
import { journeyStepToPackStage } from '@/components/activities/materialJourneySteps'
import type { ActivityPackItem } from '@/api/activityPackItems'

function packItem(partial: Partial<ActivityPackItem> & { id: string }): ActivityPackItem {
  return {
    activityId: 'act-1',
    materialItemId: partial.materialItemId ?? partial.id,
    materialName: partial.materialName ?? 'Seil',
    categoryName: partial.categoryName ?? 'Seile',
    quantityOrdered: partial.quantityOrdered ?? 5,
    quantityPacked: partial.quantityPacked ?? 5,
    quantityTransportTo: partial.quantityTransportTo ?? 0,
    quantityIssued: partial.quantityIssued ?? 0,
    quantityTransportBack: partial.quantityTransportBack ?? 0,
    quantityReturned: partial.quantityReturned ?? 0,
    quantityStored: partial.quantityStored ?? 0,
    isConsumable: partial.isConsumable ?? false,
    ...partial,
  } as ActivityPackItem
}

describe('not_taken Journey-Zeilen', () => {
  it('Retour: gepackt aber nie issued → eigene not_taken-Zeile', () => {
    const packItems = [
      packItem({ id: 'pi-taken', quantityIssued: 3, quantityPacked: 3, quantityOrdered: 3 }),
      packItem({
        id: 'pi-left',
        materialName: 'Blache',
        quantityOrdered: 2,
        quantityPacked: 2,
        quantityIssued: 0,
      }),
    ]
    const packStage = journeyStepToPackStage('return', 'quick')
    const state = createMaterialJourneyPackContextState({
      packItems,
      packContainers: [],
      containerItemsByContainerId: {},
      packStage,
      profile: 'quick',
      issues: [],
    })
    const rows = buildMaterialJourneyTasks(packItems, {
      listCtx: state.packListCtx,
      containerCtx: state.packContainerCtx,
      stageLeftItems: state.stageLeftItems,
      packStage,
      packContainers: [],
      maxForwardQty: state.packIssueForwardMax,
      containerIssueableUnits: state.containerIssueableUnits,
      containerActionableUnits: state.containerActionableUnits,
      containerContentActionableUnits: state.containerContentActionableUnits,
      canMoveItem: () => true,
      canOpenSheet: true,
      formatCrateLineCount: (n) => String(n),
      shellPackItemForContainer: state.shellPackItemForContainer,
    })

    const notTaken = rows.filter((r) => r.kind === 'not_taken')
    expect(notTaken).toHaveLength(1)
    expect(notTaken[0]?.title).toBe('Blache')
    expect(notTaken[0]?.badges).toContain('not_taken')
    expect(notTaken[0]?.canMove).toBe(false)
  })
})
