import { describe, expect, it } from 'vitest'
import { GA_HELFERPOOL_ROUTE_ROLES, gaCanManageStaffing, gaCanSeeStaffing, gaDeptStageBadge } from './grossanlassAccess'
import { canAssignGrossanlassDeptRoles, getDeptRoleShort, hierarchyForDepartment, normalizeDeptRole } from './departmentMemberRoles'

describe('Helferpool roles', () => {
  it('lets GA-HV, GA-SUBHV and by default GA-MW and GA-OK manage the pool and assignments', () => {
    for (const role of ['hv', 'subhv', 'ga_hv', 'mw', 'dc']) expect(gaCanManageStaffing(role)).toBe(true)
    for (const role of ['cmw', 'bl', 'lw', 'u', 'h', '', null, undefined]) expect(gaCanManageStaffing(role as string)).toBe(false)
  })

  it('switches GA-MW and GA-OK off independently while GA-HV/GA-SUBHV stay allowed', () => {
    expect(gaCanManageStaffing('mw', { mw: false })).toBe(false)
    expect(gaCanManageStaffing('dc', { mw: false })).toBe(true)
    expect(gaCanManageStaffing('dc', { ok: false })).toBe(false)
    expect(gaCanManageStaffing('mw', { ok: false })).toBe(true)
    for (const role of ['hv', 'subhv']) expect(gaCanManageStaffing(role, { mw: false, ok: false })).toBe(true)
  })

  it('lets the GA leadership read the staffing overview without managing', () => {
    for (const role of ['hv', 'subhv', 'mw', 'cmw', 'dc']) expect(gaCanSeeStaffing(role)).toBe(true)
    for (const role of ['bl', 'komm', 'lw', 'u', 'h']) expect(gaCanSeeStaffing(role)).toBe(false)
  })

  it('knows hv/subhv as grossanlass roles that the GA leadership can assign', () => {
    expect(hierarchyForDepartment(true)).toEqual(expect.arrayContaining(['hv', 'subhv']))
    expect(hierarchyForDepartment(false)).not.toContain('hv')
    expect(normalizeDeptRole('GA_SUBHV')).toBe('subhv')
    expect(getDeptRoleShort('hv', true)).toBe('HV')
    expect(gaDeptStageBadge('subhv')).toEqual({ short: 'SUBHV', role: 'subhv' })
    expect(canAssignGrossanlassDeptRoles('dc')).toBe(true)
    expect(canAssignGrossanlassDeptRoles('hv')).toBe(false)
  })

  it('opens the helper pool route for the same roles as the sidebar entry', () => {
    expect([...GA_HELFERPOOL_ROUTE_ROLES]).toEqual(expect.arrayContaining(['hv', 'subhv', 'mw', 'cmw', 'dc']))
    expect([...GA_HELFERPOOL_ROUTE_ROLES]).not.toContain('bl')
  })
})
