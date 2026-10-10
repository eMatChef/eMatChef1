// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import router from '@/router'

type Redirect = (to: unknown) => { name?: string; params?: Record<string, string> }

function resolveRedirect(path: string) {
  const to = router.resolve(path)
  const record = to.matched[to.matched.length - 1]
  const redirect = record?.redirect as Redirect | undefined
  return { to, target: redirect ? redirect(to) : null }
}

describe('Help routes', () => {
  it('serves the GA help under /ga/help/ga and the department help under /dept/help/department', () => {
    expect(router.resolve('/d1/ga/help/ga').name).toBe('GrossanlassHilfe')
    expect(router.resolve('/d1/ga/help/ga/freigabe').params.topic).toBe('freigabe')
    expect(router.resolve('/d1/dept/help/department').name).toBe('HelpDokumentation')
    expect(router.resolve('/d1/dept/help/department').fullPath).toBe('/d1/dept/help/department')
  })

  it('keeps the tours route unchanged', () => {
    expect(router.resolve('/d1/dept/help/tours').name).toBe('HelpTours')
    expect(router.resolve('/d1/dept/help/einrichtung').name).toBe('HelpTours')
  })

  it('redirects the old GA help path, with and without topic', () => {
    expect(resolveRedirect('/d1/ga-hilfe').target).toMatchObject({ name: 'GrossanlassHilfe', params: { departmentId: 'd1' } })
    expect(resolveRedirect('/d1/ga-hilfe/freigabe').target).toMatchObject({
      name: 'GrossanlassHilfe',
      params: { departmentId: 'd1', topic: 'freigabe' },
    })
  })

  it('redirects the old department help paths', () => {
    for (const legacy of ['dokumentation', 'overview']) {
      expect(resolveRedirect(`/d1/dept/help/${legacy}`).target).toMatchObject({ name: 'HelpDokumentation', params: { departmentId: 'd1' } })
    }
  })
})
