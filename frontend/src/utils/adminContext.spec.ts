import { describe, expect, it } from 'vitest'
import type { AdminContextsResponse } from '@/api/auth'
import {
  DEPARTMENT_CONTEXT_MARKER,
  adminContextHomePath,
  buildAdminContextOptions,
  resolveInitialContext,
} from '@/utils/adminContext'

const SA = ['ROLE_USER', 'ROLE_SUPERADMIN', 'ROLE_WEBADMIN']
const ORG = ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF']
const SUB = ['ROLE_USER', 'ROLE_SUBORGCHEF']

const scopes = (...ids: string[]): AdminContextsResponse => ({
  global: false,
  role: 'org',
  unrestricted: false,
  scopes: ids.map((id) => ({ department_id: id, name: `Dept ${id}`, organisation_id: 'org1', parent_id: null })),
})

describe('buildAdminContextOptions', () => {
  it('gives the superadmin one selectable global context without any department', () => {
    const options = buildAdminContextOptions(SA, null)
    expect(options).toHaveLength(1)
    expect(options[0]).toMatchObject({ key: 'global', kind: 'global', role: 'superadmin', departmentId: null })
  })

  it('gives org/sub one management context per scope root', () => {
    const options = buildAdminContextOptions(ORG, scopes('kv', 'other'))
    expect(options.map((o) => o.key)).toEqual(['management:kv', 'management:other'])
    expect(options.every((o) => o.kind === 'management' && o.role === 'org')).toBe(true)
    expect(buildAdminContextOptions(SUB, { ...scopes('sued'), role: 'sub' })[0]).toMatchObject({ role: 'sub', departmentId: 'sued', name: 'Dept sued' })
  })

  it('offers one unrestricted management context for org/sub without scope', () => {
    const options = buildAdminContextOptions(ORG, { global: false, role: 'org', unrestricted: true, scopes: [] })
    expect(options).toEqual([expect.objectContaining({ key: 'management:all', departmentId: null })])
  })

  it('gives plain members no administration context, whatever the server sent', () => {
    expect(buildAdminContextOptions(['ROLE_USER', 'ROLE_MATWART'], scopes('kv'))).toEqual([])
    expect(buildAdminContextOptions(['ROLE_USER'], null)).toEqual([])
    // Orgchef-Rolle, aber keine passende Serverantwort → nichts erfinden
    expect(buildAdminContextOptions(ORG, null)).toEqual([])
    expect(buildAdminContextOptions(ORG, { global: false, role: 'none', unrestricted: false, scopes: [] })).toEqual([])
  })
})

describe('resolveInitialContext', () => {
  const sa = buildAdminContextOptions(SA, null)
  const org = buildAdminContextOptions(ORG, scopes('kv'))

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
      adminContextKey: 'management:kv',
    })
  })

  it('restores a stored management context only if it is still offered', () => {
    expect(resolveInitialContext({ isSuperAdmin: false, options: org, preferredDepartmentId: 'd1', storedContext: 'management:kv' })).toEqual({
      activeDepartmentId: null,
      adminContextKey: 'management:kv',
    })
    expect(resolveInitialContext({ isSuperAdmin: false, options: org, preferredDepartmentId: 'd1', storedContext: 'management:gone' })).toEqual({
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

describe('adminContextHomePath', () => {
  it('opens the global dashboard for the system context and the administration for management areas', () => {
    expect(adminContextHomePath({ kind: 'global' })).toBe('/dashboard')
    expect(adminContextHomePath({ kind: 'management' })).toBe('/admin-dashboard/verwaltung')
  })
})
