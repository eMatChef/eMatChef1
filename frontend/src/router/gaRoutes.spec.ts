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

  it.each([
    ['/dep-1/planung/transporte?create=1#x', '/dep-1/ga/planung/transporte?create=1#x'],
    ['/dep-1/material/bestand/eigen', '/dep-1/ga/material/bestand/eigen'],
    ['/dep-1/mein-ressort', '/dep-1/ga/mein-ressort'],
    ['/dep-1/helferauftrag/g1', '/dep-1/ga/helferauftrag/g1'],
  ])('legacy %s redirects to %s', (legacy, target) => {
    const resolved = router.resolve(legacy)
    const redirect = resolved.matched[resolved.matched.length - 1]?.redirect
    expect(typeof redirect).toBe('function')
    const result = (redirect as (to: unknown) => { path: string; query?: Record<string, string>; hash?: string })(resolved)
    const url = new URL(
      router.resolve({ path: result.path, query: result.query, hash: result.hash }).fullPath,
      'http://x',
    )
    expect(url.pathname + url.search + url.hash).toBe(target)
  })

  it('does not capture global URLs', () => {
    expect(router.resolve('/profile').params.departmentId).toBeUndefined()
    expect(router.resolve('/login').name).toBe('Login')
    expect(router.resolve('/register').name).toBe('Register')
  })
})
