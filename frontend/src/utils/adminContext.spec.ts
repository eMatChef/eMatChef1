import { describe, expect, it } from 'vitest'
import type { AdminContextsResponse } from '@/api/auth'
import {
  DEPARTMENT_CONTEXT_MARKER,
  adminContextHomePath,
  buildAdminContextOptions,
  resolveInitialContext,
  visibleOrganisationIds,
} from '@/utils/adminContext'

const SA = ['ROLE_USER', 'ROLE_SUPERADMIN', 'ROLE_WEBADMIN']
const ORG = ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']
const SUB = ['ROLE_USER', 'ROLE_SUBORGCHEF']

const deptScope = (id: string, org = 'org1') => ({ kind: 'department' as const, department_id: id, name: `Dept ${id}`, organisation_id: org, parent_id: null })
const orgScope = (org: string) => ({ kind: 'organisation' as const, department_id: null, name: `Org ${org}`, organisation_id: org, parent_id: null })
const scopes = (...items: AdminContextsResponse['scopes']): AdminContextsResponse => ({ global: false, role: 'org', scopes: items })

describe('buildAdminContextOptions', () => {
  it('gives the superadmin one selectable global context without any department', () => {
    const options = buildAdminContextOptions(SA, null)
    expect(options).toHaveLength(1)
    expect(options[0]).toMatchObject({ key: 'global', kind: 'global', role: 'superadmin', departmentId: null })
  })

  it('gives org/sub one context per assigned department root', () => {
    const options = buildAdminContextOptions(ORG, scopes(deptScope('kv'), deptScope('other')))
    expect(options.map((o) => o.key)).toEqual(['admin-dept:kv', 'admin-dept:other'])
    expect(options.every((o) => o.kind === 'department' && o.role === 'org')).toBe(true)
    expect(buildAdminContextOptions(SUB, { ...scopes(deptScope('sued')), role: 'sub' })[0]).toMatchObject({ role: 'sub', departmentId: 'sued', name: 'Dept sued' })
  })

  it('offers organisation contexts, separate from department contexts, and allows combining them', () => {
    const options = buildAdminContextOptions(SUB, { global: false, role: 'sub', scopes: [orgScope('o1'), deptScope('d1', 'o2')] })
    expect(options.map((o) => [o.key, o.kind, o.role, o.departmentId, o.organisationId])).toEqual([
      ['admin-org:o1', 'organisation', 'sub', null, 'o1'],
      ['admin-dept:d1', 'department', 'sub', 'd1', 'o2'],
    ])
  })

  it('keeps administration contexts distinct from the department of the same name or id', () => {
    const keys = buildAdminContextOptions(ORG, scopes(orgScope('abc'), deptScope('abc'))).map((o) => o.key)
    expect(new Set(keys).size).toBe(2)
    expect(keys).not.toContain('abc')
    expect(keys).not.toContain(DEPARTMENT_CONTEXT_MARKER)
  })

  it('gives org/sub without any assignment no context (no implicit access to everything)', () => {
    expect(buildAdminContextOptions(ORG, { global: false, role: 'org', scopes: [] })).toEqual([])
  })

  it('gives plain members no administration context, whatever the server sent', () => {
    expect(buildAdminContextOptions(['ROLE_USER', 'ROLE_MATWART'], scopes(deptScope('kv')))).toEqual([])
    expect(buildAdminContextOptions(['ROLE_USER'], null)).toEqual([])
    // Orgchef-Rolle, aber keine passende Serverantwort → nichts erfinden
    expect(buildAdminContextOptions(ORG, null)).toEqual([])
    expect(buildAdminContextOptions(ORG, { global: false, role: 'none', scopes: [] })).toEqual([])
    // Rolle und Serverantwort müssen übereinstimmen (Suborgchef erhält nie Orgchef-Kontexte)
    expect(buildAdminContextOptions(SUB, scopes(deptScope('kv')))).toEqual([])
  })
})

describe('resolveInitialContext', () => {
  const sa = buildAdminContextOptions(SA, null)
  const org = buildAdminContextOptions(ORG, scopes(deptScope('kv')))

  it('starts the superadmin globally, even with department memberships', () => {
    expect(resolveInitialContext({ isSuperAdmin: true, options: sa, preferredDepartmentId: 'd1', storedContext: null })).toEqual({
      activeDepartmentId: null,
      adminContextKey: 'global',
    })
  })

  it('lets the superadmin resume a department they chose as a normal member', () => {
    expect(
      resolveInitialContext({ isSuperAdmin: true, options: sa, preferredDepartmentId: 'd1', storedContext: DEPARTMENT_CONTEXT_MARKER }),
    ).toEqual({ activeDepartmentId: 'd1', adminContextKey: null })
    // ohne Mitgliedschaft bleibt nur der globale Kontext
    expect(
      resolveInitialContext({ isSuperAdmin: true, options: sa, preferredDepartmentId: null, storedContext: DEPARTMENT_CONTEXT_MARKER }),
    ).toEqual({ activeDepartmentId: null, adminContextKey: 'global' })
  })

  it('starts org/sub in their department when they have a membership', () => {
    expect(resolveInitialContext({ isSuperAdmin: false, options: org, preferredDepartmentId: 'd1', storedContext: null })).toEqual({
      activeDepartmentId: 'd1',
      adminContextKey: null,
    })
  })

  it('starts org/sub in the management area without a membership (no waiting room)', () => {
    expect(resolveInitialContext({ isSuperAdmin: false, options: org, preferredDepartmentId: null, storedContext: null })).toEqual({
      activeDepartmentId: null,
      adminContextKey: 'admin-dept:kv',
    })
  })

  it('restores a stored management context only if it is still offered', () => {
    expect(resolveInitialContext({ isSuperAdmin: false, options: org, preferredDepartmentId: 'd1', storedContext: 'admin-dept:kv' })).toEqual({
      activeDepartmentId: null,
      adminContextKey: 'admin-dept:kv',
    })
    expect(resolveInitialContext({ isSuperAdmin: false, options: org, preferredDepartmentId: 'd1', storedContext: 'admin-dept:gone' })).toEqual({
      activeDepartmentId: 'd1',
      adminContextKey: null,
    })
  })

  it('never invents an administration context for plain members', () => {
    expect(resolveInitialContext({ isSuperAdmin: false, options: [], preferredDepartmentId: 'd1', storedContext: 'global' })).toEqual({
      activeDepartmentId: 'd1',
      adminContextKey: null,
    })
  })
})

describe('visibleOrganisationIds', () => {
  it('lists the organisations of all assigned scopes once and nothing without assignment', () => {
    expect(visibleOrganisationIds(scopes(orgScope('o1'), deptScope('d1', 'o1'), deptScope('d2', 'o2'))).sort()).toEqual(['o1', 'o2'])
    expect(visibleOrganisationIds(null)).toEqual([])
    expect(visibleOrganisationIds(scopes())).toEqual([])
  })
})

describe('adminContextHomePath', () => {
  it('opens the global dashboard for the system context and the administration for management areas', () => {
    expect(adminContextHomePath({ kind: 'global' })).toBe('/dashboard')
    expect(adminContextHomePath({ kind: 'department' })).toBe('/admin-dashboard/verwaltung')
    expect(adminContextHomePath({ kind: 'organisation' })).toBe('/admin-dashboard/verwaltung')
  })
})
