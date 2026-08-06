import { describe, expect, it } from 'vitest'
import { activityStatusAfterJourneyStep } from '@/components/activities/materialJourneySteps'

describe('activityStatusAfterJourneyStep', () => {
  it('Logistics Transport hin: packed → zuerst transport_out (nicht at_event)', () => {
    expect(activityStatusAfterJourneyStep('transport_out', 'logistics', 'packed')).toBe(
      'transport_out',
    )
  })

  it('Logistics Transport hin: transport_out → at_event', () => {
    expect(activityStatusAfterJourneyStep('transport_out', 'logistics', 'transport_out')).toBe(
      'at_event',
    )
  })

  it('Logistics Am Anlass → transport_back', () => {
    expect(activityStatusAfterJourneyStep('issue', 'logistics', 'at_event')).toBe('transport_back')
  })

  it('Logistics Transport zurück → returned', () => {
    expect(activityStatusAfterJourneyStep('transport_back', 'logistics', 'transport_back')).toBe(
      'returned',
    )
  })

  it('Quick Ausgabe → returned', () => {
    expect(activityStatusAfterJourneyStep('issue', 'quick', 'at_event')).toBe('returned')
  })
})
