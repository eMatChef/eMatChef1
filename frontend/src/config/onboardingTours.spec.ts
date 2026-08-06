import { describe, expect, it } from 'vitest'
import {
  filterOnboardingToursForRole,
  isTourVisibleForRole,
  getOnboardingTour,
} from '@/config/onboardingTours'

describe('onboarding tour audience filter', () => {
  it('shows only activity tours for user/l1 without camp', () => {
    const tours = filterOnboardingToursForRole('l1', { canCreateCamp: false })
    expect(tours.map((t) => t.id)).toEqual(['activity-create'])
  })

  it('includes camp tour when camp create is allowed', () => {
    const tours = filterOnboardingToursForRole('user', { canCreateCamp: true })
    expect(tours.map((t) => t.id).sort()).toEqual(['activity-camp-create', 'activity-create'])
  })

  it('shows all tours for mw', () => {
    const tours = filterOnboardingToursForRole('mw', { canCreateCamp: true })
    expect(tours.map((t) => t.id)).toContain('material-create')
    expect(tours.map((t) => t.id)).toContain('issue-return')
    expect(tours.map((t) => t.id)).toContain('activity-create')
    expect(tours.map((t) => t.id)).toContain('categories')
  })

  it('hides mw-only tours from basic members', () => {
    expect(isTourVisibleForRole(getOnboardingTour('issue-return')!, 'l2')).toBe(false)
    expect(isTourVisibleForRole(getOnboardingTour('material-create')!, 'u')).toBe(false)
  })

  it('activity tours have expected wizard targets', () => {
    const activity = getOnboardingTour('activity-create')!
    expect(activity.version).toBeGreaterThanOrEqual(3)
    expect(activity.steps.some((s) => s.target === '#activity-create-zeitraum')).toBe(true)

    const camp = getOnboardingTour('activity-camp-create')!
    expect(camp.steps.some((s) => s.target?.includes('activity-camp-js-material'))).toBe(true)
    expect(camp.requiresCampCreate).toBe(true)
  })

  it('issue-return has multiple steps for mw pack path', () => {
    const tour = getOnboardingTour('issue-return')!
    expect(tour.audience).toBe('mw')
    expect(tour.steps.length).toBeGreaterThanOrEqual(3)
  })
})
