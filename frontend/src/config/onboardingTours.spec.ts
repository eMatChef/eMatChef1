import { describe, expect, it } from 'vitest'
import {
  filterOnboardingToursForRole,
  isTourVisibleForRole,
  getOnboardingTour,
  getMissingTourPrerequisites,
  isActivityReadyForIssueTour,
  isAcceptPackTourListCandidate,
  acceptPackTourTypeRank,
  ONBOARDING_TOUR_CATEGORY_ORDER,
} from '@/config/onboardingTours'

describe('onboarding tour audience filter', () => {
  it('shows start + activity for l1 without camp; approve only with camp', () => {
    const tours = filterOnboardingToursForRole('l1', { canCreateCamp: false })
    expect(tours.map((t) => t.id)).toEqual(['profile-overview', 'activity-create'])
    expect(
      isTourVisibleForRole(getOnboardingTour('activity-approve')!, 'l1', { canCreateCamp: true })
    ).toBe(true)
  })

  it('includes camp + approve when camp create is allowed for group leader user', () => {
    const tours = filterOnboardingToursForRole('user', {
      canCreateCamp: true,
      isGroupLeader: true,
    })
    expect(tours.map((t) => t.id).sort()).toEqual([
      'activity-approve',
      'activity-camp-create',
      'activity-create',
      'profile-overview',
    ])
    expect(getOnboardingTour('activity-approve')!.requiresCompletedTours).toContain(
      'activity-camp-create'
    )
  })

  it('hides approve from plain user without group leader', () => {
    expect(
      isTourVisibleForRole(getOnboardingTour('activity-approve')!, 'u', {
        isGroupLeader: false,
        canCreateCamp: true,
      })
    ).toBe(false)
    expect(
      isTourVisibleForRole(getOnboardingTour('activity-approve')!, 'u', {
        isGroupLeader: true,
        canCreateCamp: true,
      })
    ).toBe(true)
  })

  it('shows mw pack chain and leader approve; not dc-close for mw', () => {
    const tours = filterOnboardingToursForRole('mw', { canCreateCamp: true })
    const ids = tours.map((t) => t.id)
    expect(ids).toContain('profile-overview')
    expect(ids).toContain('material-create')
    expect(ids).toContain('activity-approve')
    expect(ids).toContain('issue-return')
    expect(ids).toContain('issue-handoff')
    expect(ids).toContain('activity-store')
    expect(ids).toContain('workshop-overview')
    expect(ids).toContain('activity-create')
    expect(ids).toContain('categories')
    expect(ids).not.toContain('activity-close')
  })

  it('dc sees lower-role tours plus activity-close', () => {
    expect(isTourVisibleForRole(getOnboardingTour('activity-close')!, 'mw')).toBe(false)
    expect(isTourVisibleForRole(getOnboardingTour('activity-close')!, 'dc')).toBe(true)
    expect(isTourVisibleForRole(getOnboardingTour('activity-approve')!, 'dc')).toBe(true)
    expect(isTourVisibleForRole(getOnboardingTour('issue-return')!, 'dc')).toBe(true)
    expect(isTourVisibleForRole(getOnboardingTour('activity-store')!, 'dc')).toBe(true)
    const ids = filterOnboardingToursForRole('dc').map((t) => t.id)
    expect(ids).toContain('activity-close')
    expect(ids).toContain('activity-store')
    expect(ids).toContain('activity-approve')
    expect(ids).toContain('issue-handoff')
  })

  it('hides mw-only tours from basic members', () => {
    expect(isTourVisibleForRole(getOnboardingTour('issue-return')!, 'l2')).toBe(false)
    expect(isTourVisibleForRole(getOnboardingTour('material-create')!, 'u')).toBe(false)
  })

  it('activity tours have expected wizard targets', () => {
    const activity = getOnboardingTour('activity-create')!
    expect(activity.version).toBeGreaterThanOrEqual(7)
    expect(activity.steps.some((s) => s.target?.includes('activity-create-zeitraum'))).toBe(true)
    expect(activity.steps.some((s) => s.target?.includes('activity-create-material'))).toBe(true)
    expect(activity.steps.some((s) => s.target?.includes('activity-wizard-submit'))).toBe(true)
    expect(activity.steps.length).toBeGreaterThanOrEqual(8)

    const camp = getOnboardingTour('activity-camp-create')!
    expect(camp.steps.some((s) => s.target?.includes('nav-activities'))).toBe(true)
    expect(camp.steps.some((s) => s.target?.includes('activity-camp-js-material'))).toBe(true)
    expect(camp.steps.some((s) => s.target?.includes('activity-detail-submit'))).toBe(true)
    expect(camp.steps.some((s) => s.target?.includes('activity-detail-approve'))).toBe(true)
    expect(camp.browseComplete).toBe(true)
    expect(camp.requiresCampCreate).toBe(true)
    expect(camp.requiresCompletedTours).toEqual(
      expect.arrayContaining(['material-create', 'material-consumable'])
    )
  })

  it('issue-return is pack-only for mw with approved data gate', () => {
    const tour = getOnboardingTour('issue-return')!
    expect(tour.audience).toBe('mw')
    expect(tour.version).toBeGreaterThanOrEqual(7)
    expect(tour.steps.length).toBeGreaterThanOrEqual(9)
    expect(tour.browseComplete).toBe(true)
    expect(tour.requiresApprovedActivityOrCamp).toBe(true)
    expect(tour.requiresAnyCompletedTours).toEqual(
      expect.arrayContaining(['activity-create', 'activity-camp-create'])
    )
    expect(tour.steps.some((s) => s.target?.includes('activities-packing-row'))).toBe(true)
    expect(tour.steps.some((s) => s.target?.includes('activity-detail-accept-pack'))).toBe(true)
    expect(tour.steps.some((s) => s.target?.includes('activity-pack-scan'))).toBe(true)
    expect(tour.steps.some((s) => s.target?.includes('activity-pack-list'))).toBe(true)
  })

  it('chains handoff → store (mw/dc) → close (dc) / workshop (mw)', () => {
    expect(getOnboardingTour('issue-handoff')!.requiresCompletedTours).toContain('issue-return')
    expect(getOnboardingTour('activity-store')!.requiresCompletedTours).toContain('issue-handoff')
    expect(getOnboardingTour('activity-store')!.audience).toBe('mw')
    expect(getOnboardingTour('activity-close')!.requiresCompletedTours).toContain('activity-store')
    expect(getOnboardingTour('workshop-overview')!.requiresCompletedTours).toContain('issue-handoff')
    expect(isActivityReadyForIssueTour('activity', 'approved')).toBe(true)
    expect(isActivityReadyForIssueTour('activity', 'submitted')).toBe(true)
    expect(isActivityReadyForIssueTour('camp', 'packing')).toBe(true)
    expect(isActivityReadyForIssueTour('camp', 'submitted')).toBe(false)
    expect(isActivityReadyForIssueTour('activity', 'draft')).toBe(false)
    expect(isActivityReadyForIssueTour('external', 'approved')).toBe(false)
  })

  it('accept-pack tour list prefers camp/event approved and allows submitted activity', () => {
    expect(isAcceptPackTourListCandidate('camp', 'approved')).toBe(true)
    expect(isAcceptPackTourListCandidate('event', 'approved')).toBe(true)
    expect(isAcceptPackTourListCandidate('camp', 'submitted')).toBe(false)
    expect(isAcceptPackTourListCandidate('activity', 'submitted')).toBe(true)
    expect(isAcceptPackTourListCandidate('activity', 'approved')).toBe(true)
    expect(acceptPackTourTypeRank('camp')).toBeLessThan(acceptPackTourTypeRank('activity'))
  })

  it('profile-overview uses user avatar and start category', () => {
    const tour = getOnboardingTour('profile-overview')!
    expect(tour.category).toBe('start')
    expect(tour.audience).toBe('all')
    expect(tour.useUserAvatar).toBe(true)
    expect(tour.version).toBeGreaterThanOrEqual(3)
    expect(tour.steps.length).toBe(18)
    expect(tour.steps[0]?.target).toContain('sidebar-nav')
    expect(tour.steps.some((s) => s.target?.includes('profile-save'))).toBe(true)
  })

  it('locks activity tour behind material + consumable for mw, not for members', () => {
    const activity = getOnboardingTour('activity-create')!
    expect(activity.requiresCompletedTours).toEqual(
      expect.arrayContaining(['material-create', 'material-consumable'])
    )
    expect(
      getMissingTourPrerequisites(activity, 'mw', new Set(), { canCreateCamp: false })
    ).toEqual(expect.arrayContaining(['material-create', 'material-consumable']))
    expect(
      getMissingTourPrerequisites(activity, 'mw', new Set(['material-create']), {
        canCreateCamp: false,
      })
    ).toEqual(['material-consumable'])
    expect(
      getMissingTourPrerequisites(
        activity,
        'mw',
        new Set(['material-create', 'material-consumable']),
        { canCreateCamp: false }
      )
    ).toEqual([])
    expect(
      getMissingTourPrerequisites(activity, 'l1', new Set(), { canCreateCamp: false })
    ).toEqual([])
  })

  it('locks consumable tour behind material create; step1 is click', () => {
    const tour = getOnboardingTour('material-consumable')!
    expect(tour.audience).toBe('mw')
    expect(tour.requiresCompletedTours).toContain('material-create')
    expect(tour.requiresAnyCompletedTours).toBeUndefined()
    expect(tour.steps[0]?.mode).toBe('click')
    expect(tour.steps[0]?.target).toContain('nav-materials')
    expect(tour.steps[1]?.target).toContain('material-new')
    expect(tour.steps.length).toBe(11)
    expect(
      getMissingTourPrerequisites(tour, 'mw', new Set(), { canCreateCamp: true })
    ).toEqual(['material-create'])
    expect(
      getMissingTourPrerequisites(tour, 'mw', new Set(['material-create']), {
        canCreateCamp: true,
      })
    ).toEqual([])
  })

  it('orders categories start → settings → material and locks material behind categories', () => {
    expect(ONBOARDING_TOUR_CATEGORY_ORDER.slice(0, 3)).toEqual([
      'start',
      'settings',
      'material',
    ])
    const material = getOnboardingTour('material-create')!
    expect(material.requiresCompletedTours).toContain('categories')
    expect(
      getMissingTourPrerequisites(material, 'mw', new Set(), { canCreateCamp: true })
    ).toEqual(['categories'])
    expect(
      getMissingTourPrerequisites(material, 'mw', new Set(['categories']), {
        canCreateCamp: true,
      })
    ).toEqual([])
  })

  it('shows org/suborg admin tours', () => {
    expect(isTourVisibleForRole(getOnboardingTour('org-overview')!, 'organisationschef')).toBe(true)
    expect(isTourVisibleForRole(getOnboardingTour('suborg-overview')!, 'suborgchef')).toBe(true)
    expect(isTourVisibleForRole(getOnboardingTour('org-overview')!, 'mw')).toBe(false)
  })
})
