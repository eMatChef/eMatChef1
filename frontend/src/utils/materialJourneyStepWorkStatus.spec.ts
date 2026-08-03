import { describe, expect, it } from 'vitest'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { isJourneyStepWorkComplete } from '@/utils/materialJourneyStepWorkStatus'
import { journeyStepsWithOpenWork } from '@/utils/materialJourneyNavigation'
import { logisticsTransportBackFixtures } from '@/test-fixtures/journeyAbnahmeFixtures'

function fullyReturnedLogisticsItems(): ActivityPackItem[] {
  return logisticsTransportBackFixtures().packItems.map((p) => ({
    ...p,
    quantityPacked: Math.max(p.quantityPacked ?? 1, 1),
    quantityTransportTo: Math.max(p.quantityTransportTo ?? 1, 1),
    quantityIssued: Math.max(p.quantityIssued ?? 1, 1),
    quantityTransportBack: Math.max(p.quantityTransportBack ?? 1, 1),
    quantityReturned: Math.max(p.quantityTransportBack ?? 1, 1),
    quantityStored: 0,
  }))
}

describe('isJourneyStepWorkComplete — Logistics nach Retour-Ankunft', () => {
  it('Transport zurück ist fertig, wenn Mengen schon returned (kein Warn-Icon)', () => {
    const packItems = fullyReturnedLogisticsItems()
    const fx = logisticsTransportBackFixtures()

    expect(
      isJourneyStepWorkComplete(
        'transport_back',
        'logistics',
        packItems,
        fx.packContainers,
        fx.containerItemsByContainerId,
      ),
    ).toBe(true)

    expect(
      journeyStepsWithOpenWork(
        'return',
        'logistics',
        {
          packItems,
          packContainers: fx.packContainers,
          containerItemsByContainerId: fx.containerItemsByContainerId,
        },
        true,
      ),
    ).not.toContain('transport_back')
  })

  it('Transport zurück bleibt offen, solange issued > transport_back', () => {
    const fx = logisticsTransportBackFixtures()
    // Shell hat transport_back, lose Issued ohne volles transport_back prüfen via At-Event-Fixtures
    const { packItems, packContainers, containerItemsByContainerId } = fx
    // Mindestens eine Position: issued gebucht, noch kein transport_back
    const pending = packItems.map((p) =>
      p.id === 'pi-log-shell'
        ? p
        : { ...p, quantityIssued: 1, quantityTransportBack: 0, quantityReturned: 0 },
    )

    expect(
      isJourneyStepWorkComplete(
        'transport_back',
        'logistics',
        pending,
        packContainers,
        containerItemsByContainerId,
      ),
    ).toBe(false)
  })
})
