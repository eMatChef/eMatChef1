import { describe, expect, it, vi } from 'vitest'
import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router'
import { carryOAuthReturnParams } from './oauthReturnParams'

const route = (query: Record<string, string>) => ({ query }) as unknown as RouteLocationNormalized

describe('carryOAuthReturnParams', () => {
  it('leaves every navigation untouched without the return marker', () => {
    const next = vi.fn()
    const wrapped = carryOAuthReturnParams(route({ foo: 'bar' }), next as unknown as NavigationGuardNext)

    expect(wrapped).toBe(next)
  })

  it('keeps the marker through string redirects (e.g. /dashboard → /{departmentId})', () => {
    const next = vi.fn()
    carryOAuthReturnParams(route({ profile_security: '1' }), next as unknown as NavigationGuardNext)('/d0000000001')

    expect(next).toHaveBeenCalledWith('/d0000000001?profile_security=1')
  })

  it('keeps existing query and hash of the redirect target', () => {
    const next = vi.fn()
    carryOAuthReturnParams(route({ profile_security: '1' }), next as unknown as NavigationGuardNext)('/pending-assignment?join_code=AB#x')

    expect(next).toHaveBeenCalledWith('/pending-assignment?join_code=AB&profile_security=1#x')
  })

  it('keeps the marker through object redirects, replacing nothing else', () => {
    const next = vi.fn()
    carryOAuthReturnParams(route({ profile_security: '1' }), next as unknown as NavigationGuardNext)({
      name: 'DevicesHome',
      params: { departmentId: 'd1' },
      query: { a: '1' },
      replace: true,
    })

    expect(next).toHaveBeenCalledWith({ name: 'DevicesHome', params: { departmentId: 'd1' }, query: { a: '1', profile_security: '1' }, replace: true })
  })

  it('passes plain continuation, abort and errors through unchanged', () => {
    const next = vi.fn()
    const wrapped = carryOAuthReturnParams(route({ profile_security: '1' }), next as unknown as NavigationGuardNext)
    wrapped()
    wrapped(false)
    const error = new Error('x')
    wrapped(error)

    expect(next.mock.calls).toEqual([[undefined], [false], [error]])
  })

  it('does not carry the marker when its value is not exactly 1', () => {
    const next = vi.fn()
    const wrapped = carryOAuthReturnParams(route({ profile_security: '0' }), next as unknown as NavigationGuardNext)

    expect(wrapped).toBe(next)
  })
})
