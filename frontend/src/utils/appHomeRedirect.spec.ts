import { describe, expect, it } from 'vitest'
import {
  departmentDashboardPathFromFullPath,
  resolveAuthenticatedHomePath,
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

describe('resolveAuthenticatedHomePath with administration contexts', () => {
  const base = {
    userRoles: ['ROLE_USER'],
    activeDepartmentId: null as string | null,
    activeAdminContext: null as { kind: 'global' | 'management' } | null,
    departments: [] as Array<{ department_id: string; is_primary?: boolean }>,
    hasSupplierAccess: false,
    activeSupplierCompanies: [],
    activeSupplierCompanyId: null,
    currentDepartmentRole: 'u',
    isDepartmentGrossanlass: () => false,
  }
  const home = (over: Partial<typeof base>) =>
    resolveAuthenticatedHomePath({ ...base, ...over } as unknown as Parameters<typeof resolveAuthenticatedHomePath>[0])

  it('opens the global dashboard in the superadmin system context', () => {
    expect(home({ userRoles: ['ROLE_SUPERADMIN'], activeAdminContext: { kind: 'global' } })).toBe('/dashboard')
  })

  it('opens the chosen department when the superadmin acts as a normal member', () => {
    expect(
      home({ userRoles: ['ROLE_SUPERADMIN'], activeDepartmentId: 'd1', departments: [{ department_id: 'd1', is_primary: true }] }),
    ).toBe('/d1')
  })

  it('opens the administration for an org/sub management context and the department otherwise', () => {
    expect(home({ userRoles: ['ROLE_ORGANISATIONSCHEF'], activeAdminContext: { kind: 'management' } })).toBe('/admin-dashboard/verwaltung')
    expect(
      home({ userRoles: ['ROLE_ORGANISATIONSCHEF'], activeDepartmentId: 'c1', departments: [{ department_id: 'c1', is_primary: true }] }),
    ).toBe('/c1')
  })

  it('keeps the waiting room for members without any assignment', () => {
    expect(home({})).toBe('/pending-assignment')
  })
})
