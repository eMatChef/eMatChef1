// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import router from './index'

describe('global profile routes', () => {
  it.each([
    ['/profile', 'Profile'],
    ['/profile/security', 'ProfileSecurity'],
    ['/profile/emails', 'ProfileEmails'],
    ['/profile/identities', 'ProfileIdentities'],
    ['/profile/departments', 'ProfileDepartments'],
  ])('%s resolves to the named profile route and never to a department', (path, name) => {
    const resolved = router.resolve(path)
    expect(resolved.name).toBe(name)
    expect(resolved.params.departmentId).toBeUndefined()
    expect(resolved.meta.requiresAuth).toBe(true)
    expect(resolved.meta.globalProfile).toBe(true)
  })

  it('keeps the return path across tab URLs', () => {
    const resolved = router.resolve({ name: 'ProfileSecurity', query: { from: '/b57aa6184ef5/ga/planning' } })
    expect(resolved.fullPath).toBe('/profile/security?from=/b57aa6184ef5/ga/planning')
  })

  it('requires no department role on any profile route', () => {
    for (const name of ['Profile', 'ProfileSecurity', 'ProfileEmails', 'ProfileIdentities', 'ProfileDepartments']) {
      const route = router.resolve({ name })
      expect(route.meta.requireDepartmentRoles).toBeUndefined()
      expect(route.meta.denyDepartmentRoles).toBeUndefined()
      expect(route.meta.requiresGrossanlassDepartment).toBeUndefined()
    }
  })

  it('still resolves other first segments as departments', () => {
    expect(router.resolve('/b57aa6184ef5').params.departmentId).toBe('b57aa6184ef5')
  })
})
