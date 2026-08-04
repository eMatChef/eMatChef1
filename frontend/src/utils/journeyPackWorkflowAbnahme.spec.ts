import { describe, expect, it } from 'vitest'
import {
  defaultJourneyStepForStatus,
  journeyStepToPackStage,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { packCrateCheckLegForStage } from '@/components/activities/packCrateCheckLeg'
import { buildMaterialJourneyTasks } from '@/components/activities/materialJourneyTaskList'
import { createMaterialJourneyPackContextState } from '@/composables/materialJourneyPackContextState'
import {
  logisticsAtEventFixtures,
  logisticsTransportBackFixtures,
  muusliIssuedFixtures,
  partialCrateSevenThreeFixtures,
} from '@/test-fixtures/journeyAbnahmeFixtures'
import {
  activityAllowsConsumptionBooking,
  resolveEffectiveActiveJourneyStep,
} from '@/utils/materialJourneyNavigation'

function tasksForStep(
  step: JourneyStep,
  profile: PackWorkflowProfile,
  fixtures: {
    packItems: ReturnType<typeof muusliIssuedFixtures>['packItems']
    packContainers: ReturnType<typeof muusliIssuedFixtures>['packContainers']
    containerItemsByContainerId: ReturnType<typeof muusliIssuedFixtures>['containerItemsByContainerId']
    issueReports?: ReturnType<typeof muusliIssuedFixtures>['issueReports']
  },
) {
  const packStage = journeyStepToPackStage(step, profile)
  const state = createMaterialJourneyPackContextState({
    packItems: fixtures.packItems,
    packContainers: fixtures.packContainers,
    containerItemsByContainerId: fixtures.containerItemsByContainerId,
    packStage,
    profile,
    issues: fixtures.issueReports,
  })
  return buildMaterialJourneyTasks(fixtures.packItems, {
    listCtx: state.packListCtx,
    containerCtx: state.packContainerCtx,
    stageLeftItems: state.stageLeftItems,
    packStage,
    packContainers: fixtures.packContainers,
    maxForwardQty: state.packIssueForwardMax,
    containerIssueableUnits: state.containerIssueableUnits,
    containerActionableUnits: state.containerActionableUnits,
    containerContentActionableUnits: state.containerContentActionableUnits,
    canMoveItem: () => true,
    canOpenSheet: true,
    formatCrateLineCount: (n) => String(n),
    shellPackItemForContainer: state.shellPackItemForContainer,
  })
}

describe('Phase D — Journey Pack-Workflow Abnahme', () => {
  describe('D1 müüsli: Rakokiste + Fackeln + lose', () => {
    it('Ausgabe: 3 sichtbare Zeilen erledigt (Fackeln nur in Kiste, kein Duplikat)', () => {
      const fx = muusliIssuedFixtures()
      const rows = tasksForStep('issue', 'quick', fx)
      const open = rows.filter((r) => r.isOpen)
      const done = rows.filter((r) => r.isDone)

      expect(done.length).toBe(3)
      expect(open.length).toBe(0)
      expect(rows.some((r) => r.kind === 'crate' && r.title === 'Rakokiste')).toBe(true)
      expect(rows.filter((r) => r.kind === 'loose' && r.isDone).map((r) => r.title)).toEqual(
        expect.arrayContaining(['Statikseil', 'Blache']),
      )
      expect(rows.some((r) => r.title.includes('Fackeln'))).toBe(false)
    })

    it('Retour: Kiste + 2 lose offen, keine doppelte Fackeln-Zeile', () => {
      const fx = muusliIssuedFixtures()
      const rows = tasksForStep('return', 'quick', fx)
      const open = rows.filter((r) => r.isOpen)
      const openLoose = open.filter((r) => r.kind === 'loose')

      expect(open.some((r) => r.kind === 'crate' && r.title === 'Rakokiste')).toBe(true)
      expect(openLoose.map((r) => r.title).sort()).toEqual(['Blache', 'Statikseil'])
      expect(openLoose.some((r) => r.title.includes('Fackeln'))).toBe(false)
    })

    it('Retour: nach Verbrauch max. Retour = issued − Verbrauch', () => {
      const fx = muusliIssuedFixtures()
      const crateId = fx.packContainers[0]!.id
      fx.packItems = fx.packItems.map((p) =>
        p.materialItemId === 'mat-fackeln'
          ? {
              ...p,
              quantityOrdered: 10,
              quantityPacked: 10,
              quantityIssued: 10,
            }
          : p,
      )
      fx.containerItemsByContainerId = {
        [crateId]: (fx.containerItemsByContainerId[crateId] ?? []).map((ci) =>
          ci.material_item_id === 'mat-fackeln'
            ? { ...ci, quantity_packed: 10, quantity_issued: 10 }
            : ci,
        ),
      }
      fx.issueReports = [
        {
          id: 'ir-fackeln-1',
          activity_id: 'act-abnahme',
          material_item_id: 'mat-fackeln',
          type: 'consumption',
          quantity: 5,
          description: null,
          resolved: false,
          reported_at: '2026-08-03T00:00:00Z',
        },
      ]

      const packStage = journeyStepToPackStage('return', 'quick')
      const state = createMaterialJourneyPackContextState({
        packItems: fx.packItems,
        packContainers: fx.packContainers,
        containerItemsByContainerId: fx.containerItemsByContainerId,
        packStage,
        profile: 'quick',
        issues: fx.issueReports,
      })
      const rows = tasksForStep('return', 'quick', fx)
      const crate = rows.find((r) => r.kind === 'crate' && r.title === 'Rakokiste')

      expect(state.containerReturnableUnits(crateId)).toBe(5)
      expect(crate?.maxForwardQty).toBe(5)
    })
  })

  describe('D2 Teilmenge in Kiste (7+3)', () => {
    it('zeigt lose Teilmenge (3) und 7 in Kiste auf Ausgabe', () => {
      const fx = partialCrateSevenThreeFixtures()
      const packStage = journeyStepToPackStage('issue', 'quick')
      const state = createMaterialJourneyPackContextState({
        packItems: fx.packItems,
        packContainers: fx.packContainers,
        containerItemsByContainerId: fx.containerItemsByContainerId,
        packStage,
        profile: 'quick',
      })
      const piArtikel = fx.packItems.find((p) => p.materialItemId === 'mat-artikel')!
      const rows = tasksForStep('issue', 'quick', fx)
      const loose = rows.find((r) => r.kind === 'loose' && r.title === 'Testartikel')

      expect(state.packListCtx.looseQtyForPackItem(piArtikel)).toBe(3)
      expect(state.packListCtx.qtyInContainersForItem(piArtikel)).toBe(7)
      expect(loose).toBeDefined()
      expect(loose!.isOpen).toBe(true)
    })
  })

  describe('D3 Quick Teilausgabe → at_event', () => {
    it('aktiver Schritt bleibt Ausgabe solange Status packed', () => {
      expect(
        resolveEffectiveActiveJourneyStep({ status: 'packed', type: 'activity' }, 'quick', false),
      ).toBe('issue')
    })

    it('ab at_event springt Quick auf Retour als Checkpoint', () => {
      expect(
        resolveEffectiveActiveJourneyStep({ status: 'at_event', type: 'activity' }, 'quick', false),
      ).toBe('return')
      expect(defaultJourneyStepForStatus('at_event', 'quick', false)).toBe('issue')
    })
  })

  describe('D4 Camp/Event Transport-Kette', () => {
    it('bei at_event bleibt aktiver Stepper auf Am Anlass (issue)', () => {
      expect(
        resolveEffectiveActiveJourneyStep({ status: 'at_event', type: 'camp' }, 'logistics', false),
      ).toBe('issue')
    })

    it('Kistencheck-Bein return auf Transport zurück und Retour', () => {
      expect(packCrateCheckLegForStage(journeyStepToPackStage('transport_back', 'logistics'))).toBe(
        'return',
      )
      expect(packCrateCheckLegForStage(journeyStepToPackStage('return', 'logistics'))).toBe('return')
    })

    it('Transport zurück: Kiste retournierbar sichtbar', () => {
      const fx = logisticsTransportBackFixtures()
      const rows = tasksForStep('return', 'logistics', fx)
      expect(rows.some((r) => r.kind === 'crate' && r.isOpen)).toBe(true)
    })

    it('Am Anlass: keine Retour-Kiste vor Transport zurück', () => {
      const fx = logisticsAtEventFixtures()
      const rows = tasksForStep('return', 'logistics', fx)
      const openCrates = rows.filter((r) => r.kind === 'crate' && r.isOpen)
      expect(openCrates.length).toBe(0)
    })
  })

  describe('D5 Verbrauch melden ohne MW-Auftrag vor Retour', () => {
    it('Verbrauch melden ist ab at_event erlaubt', () => {
      expect(
        activityAllowsConsumptionBooking({ status: 'at_event', type: 'activity' }, 'quick', false),
      ).toBe(true)
    })

    it('Verbrauch melden ist in packing nicht erlaubt', () => {
      expect(
        activityAllowsConsumptionBooking({ status: 'packing', type: 'activity' }, 'quick', false),
      ).toBe(false)
    })

    it('Verbrauch melden ist für Gruppe nach returned gesperrt', () => {
      expect(
        activityAllowsConsumptionBooking({ status: 'returned', type: 'activity' }, 'quick', false),
      ).toBe(false)
    })

    it('Verbrauch melden bleibt für MW nach returned erlaubt', () => {
      expect(
        activityAllowsConsumptionBooking({ status: 'returned', type: 'activity' }, 'quick', true),
      ).toBe(true)
    })
  })
})
