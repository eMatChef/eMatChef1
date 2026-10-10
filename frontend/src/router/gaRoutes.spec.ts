// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import router from './index'

describe('grossanlass routes under /:departmentId/ga', () => {
  it.each([
    ['/dep-1/ga/planung/wuensche', 'GrossanlassPlanungWuensche'],
    ['/dep-1/ga/planung/auftraege', 'GrossanlassPlanungAuftraege'],
    ['/dep-1/ga/planung/transporte', 'GrossanlassPlanungTransporte'],
    ['/dep-1/ga/material/bestand', undefined],
    ['/dep-1/ga/beschaffung/anfragen', 'GrossanlassBeschaffungAnfragen'],
  ])('%s resolves inside the grossanlass guard scope', (path, name) => {
    const resolved = router.resolve(path)
    expect(resolved.params.departmentId).toBe('dep-1')
    if (name) expect(resolved.name).toBe(name)
    expect(resolved.matched.some((r) => r.meta.requiresGrossanlassDepartment)).toBe(true)
  })

  it('keeps no legacy GA routes below /:departmentId', () => {
    for (const legacy of ['/dep-1/planung/transporte', '/dep-1/material/bestand', '/dep-1/mein-ressort']) {
      expect(router.resolve(legacy).matched.some((r) => r.meta.requiresGrossanlassDepartment)).toBe(false)
      expect(router.resolve(legacy).name).toBeUndefined()
    }
  })

  it('does not capture global URLs', () => {
    expect(router.resolve('/profile').params.departmentId).toBeUndefined()
    expect(router.resolve('/login').name).toBe('Login')
    expect(router.resolve('/register').name).toBe('Register')
  })

  it.each([
    ['/dep-1/dept/activities', 'Activities'],
    ['/dep-1/dept/materials', 'Materials'],
    ['/dep-1/dept/dashboard', 'Dashboard'],
  ])('department page %s resolves to %s', (path, name) => {
    expect(router.resolve(path).name).toBe(name)
  })

  it('has no old department routes', () => {
    for (const old of ['/dep-1/activities', '/dep-1/materials', '/dep-1/settings', '/dep-1/dashboard']) {
      expect(router.resolve(old).name).toBeUndefined()
    }
  })

  it('keeps /:departmentId as the real entry', () => {
    expect(router.resolve('/dep-1').name).toBe('DepartmentEntry')
  })
})
