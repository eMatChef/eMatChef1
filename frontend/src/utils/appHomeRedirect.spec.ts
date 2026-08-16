import { describe, expect, it } from 'vitest'
import {
  departmentDashboardPathFromFullPath,
  parseInternalRedirectPath,
  pathHasOnboardingTourQuery,
  sanitizeLoginRedirectPath,
} from '@/utils/appHomeRedirect'
import { loginRedirectUrl } from '@/api/unauthorizedRedirect'

describe('login redirect without onboarding tour', () => {
  it('detects tour query params', () => {
    expect(
      pathHasOnboardingTourQuery('/dep/activities?onboardingTour=activity-create&onboardingTourStep=2')
    ).toBe(true)
    expect(pathHasOnboardingTourQuery('/dep/activities')).toBe(false)
  })

  it('maps tour URL to department dashboard', () => {
    expect(
      sanitizeLoginRedirectPath(
        '/abc-dept/activities?onboardingTour=activity-create&onboardingTourStep=4'
      )
    ).toBe('/abc-dept')
    expect(departmentDashboardPathFromFullPath('/abc-dept/materials?foo=1')).toBe('/abc-dept')
  })

  it('keeps normal redirects', () => {
    expect(parseInternalRedirectPath('/abc-dept/activities')).toBe('/abc-dept/activities')
    expect(sanitizeLoginRedirectPath('/abc-dept/help/tours')).toBe('/abc-dept/help/tours')
  })

  it('loginRedirectUrl drops tour and points to department home', () => {
    expect(
      loginRedirectUrl('/abc-dept/activities?onboardingTour=activity-create&onboardingTourStep=2')
    ).toBe('/login?redirect=%2Fabc-dept')
  })
})
