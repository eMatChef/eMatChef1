// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/auth', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/auth')>()
  return { ...actual, saveLastUsedDepartment: vi.fn().mockResolvedValue(undefined) }
})
vi.mock('@/api/departmentSettings', () => ({ getGeneralSettings: vi.fn().mockResolvedValue({ timezone: 'Europe/Zurich' }) }))

import { useAuthStore } from './auth'
import { getGeneralSettings } from '@/api/departmentSettings'
import type { ServerSessionResponse } from '@/api/auth'

type Dept = ServerSessionResponse['departments'][number]
const dept = (id: string, name: string, role: string, primary = false): Dept => ({
  id,
  name,
  organisation_id: 'org1',
  role,
  is_primary: primary,
})

function session(roles: string[], departments: Dept[], adminContexts?: ServerSessionResponse['admin_contexts']): ServerSessionResponse {
  return {
    user: { id: 'u1', state: 'active', profile_id: 'p1' },
    profile: { id: 'p1', email: 'x@demo.ematchef.ch', language: 'de', roles },
    departments,
    admin_contexts: adminContexts,
    primary_department: departments.find((d) => d.is_primary)?.id ?? departments[0]?.id ?? null,
    last_used_department: null,
  }
}


async function load(store: ReturnType<typeof useAuthStore>, s: ServerSessionResponse) {
  const api = await import('@/api/auth')
  vi.spyOn(api, 'loadSessionFromServer').mockResolvedValue(s)
  await store.loadUserSessionFromCookie(true)
}

describe('auth store contexts', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.restoreAllMocks()
    vi.mocked(getGeneralSettings).mockResolvedValue({ timezone: 'Europe/Zurich' } as Awaited<ReturnType<typeof getGeneralSettings>>)
  })

  it('superadmin: global context is selectable, no department is created or required', async () => {
    const store = useAuthStore()
    await load(store, session(['ROLE_USER', 'ROLE_SUPERADMIN'], [dept('d1', 'Materialverwaltung', 'u', true), dept('d2', 'Event', 'lw')], {
      global: true, role: 'superadmin', scopes: [],
    }))

    expect(store.availableAdminContexts.map((c) => c.key)).toEqual(['global'])
    expect(store.isAdminContextActive).toBe(true)
    expect(store.activeDepartmentId).toBeNull()
    // die Mitgliedschaften sind unverändert und tragen ausdrücklich zugewiesene Rollen, keine MW-Rolle
    expect(store.departments.map((d) => d.role)).toEqual(['u', 'lw'])

    await store.setActiveDepartment('d2')
    expect(store.isAdminContextActive).toBe(false)
    expect(store.activeDepartmentId).toBe('d2')
    expect(store.currentDepartmentRole).toBe('lw')

    expect(store.selectAdminContext('global')?.kind).toBe('global')
    expect(store.activeDepartmentId).toBeNull()
    expect(store.isAdminContextActive).toBe(true)
    expect(localStorage.getItem('active_department_id')).toBeNull()
  })

  it('superadmin resumes the department after reload, but never a department he is not a member of', async () => {
    const store = useAuthStore()
    const s = session(['ROLE_USER', 'ROLE_SUPERADMIN'], [dept('d1', 'Materialverwaltung', 'u', true)], { global: true, role: 'superadmin', scopes: [] })
    await load(store, s)
    await store.setActiveDepartment('d1')

    setActivePinia(createPinia())
    const reloaded = useAuthStore()
    await load(reloaded, s)
    expect(reloaded.activeDepartmentId).toBe('d1')
    expect(reloaded.isAdminContextActive).toBe(false)

    await reloaded.setActiveDepartment('fremd')
    expect(reloaded.activeDepartmentId).toBe('d1')
  })

  it('orgchef: management areas are selectable next to a normal member role in another department', async () => {
    const store = useAuthStore()
    await load(store, session(['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [dept('c1', 'Camp', 'u', true)], {
      global: false, role: 'org', scopes: [{ kind: 'department', department_id: 'kv', name: 'Kantonalverband', organisation_id: 'org1', parent_id: null }],
    }))

    expect(store.activeDepartmentId).toBe('c1')
    expect(store.currentDepartmentRole).toBe('u')
    expect(store.availableAdminContexts.map((c) => c.key)).toEqual(['admin-dept:kv'])

    const option = store.selectAdminContext('admin-dept:kv')
    expect(option).toMatchObject({ kind: 'department', departmentId: 'kv' })
    expect(store.activeAdminContext?.name).toBe('Kantonalverband')
    expect(store.activeDepartmentId).toBeNull()
    // keine künstliche Mitgliedschaft entsteht durch die Auswahl
    expect(store.departments.map((d) => d.department_id)).toEqual(['c1'])
  })

  it('suborgchef without any membership lands in the management area instead of the waiting room', async () => {
    const store = useAuthStore()
    await load(store, session(['ROLE_USER', 'ROLE_SUBORGCHEF'], [], {
      global: false, role: 'sub', scopes: [{ kind: 'department', department_id: 'sued', name: 'Abteilung Süd', organisation_id: 'org1', parent_id: 'kv' }],
    }))

    expect(store.departments).toEqual([])
    expect(store.isAdminContextActive).toBe(true)
    expect(store.activeAdminContext?.key).toBe('admin-dept:sued')
  })

  it('orgchef with organisation and department scopes: separate contexts next to a normal role in the same department', async () => {
    const store = useAuthStore()
    await load(store, session(['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [dept('c1', 'Camp', 'u', true)], {
      global: false,
      role: 'org',
      scopes: [
        { kind: 'organisation', department_id: null, name: 'Org Camp', organisation_id: 'o1', parent_id: null },
        { kind: 'department', department_id: 'c1', name: 'Camp', organisation_id: 'o1', parent_id: null },
      ],
    }))

    expect(store.availableAdminContexts.map((c) => [c.key, c.kind])).toEqual([['admin-org:o1', 'organisation'], ['admin-dept:c1', 'department']])
    // die Mitgliedschaft im selben Department ist ein eigener Kontext mit der tatsächlichen Rolle
    expect(store.activeDepartmentId).toBe('c1')
    expect(store.activeAdminContext).toBeNull()
    store.selectAdminContext('admin-org:o1')
    expect(store.activeAdminContext?.kind).toBe('organisation')
    expect(store.activeDepartmentId).toBeNull()
    store.selectAdminContext('admin-dept:c1')
    expect(store.activeAdminContext?.kind).toBe('department')
    expect(store.departments.map((d) => d.role)).toEqual(['u'])
  })

  it('org/sub without any assignment: no context, no organisation visible, membership still works', async () => {
    const store = useAuthStore()
    await load(store, session(['ROLE_USER', 'ROLE_SUBORGCHEF'], [dept('d1', 'Abteilung', 'u', true)], { global: false, role: 'sub', scopes: [] }))

    expect(store.availableAdminContexts).toEqual([])
    expect(store.isAdminContextActive).toBe(false)
    expect(store.activeDepartmentId).toBe('d1')
    expect(store.canAccessOrganisation('org1')).toBe(false)
    expect(store.selectAdminContext('global')).toBeNull()
  })

  it('rejects contexts that were not granted by the server', async () => {
    const store = useAuthStore()
    await load(store, session(['ROLE_USER', 'ROLE_MATWART'], [dept('d1', 'Materialverwaltung', 'mw', true)]))

    expect(store.availableAdminContexts).toEqual([])
    expect(store.selectAdminContext('global')).toBeNull()
    expect(store.selectAdminContext('admin-org:none')).toBeNull()
    expect(store.activeDepartmentId).toBe('d1')
  })
})
