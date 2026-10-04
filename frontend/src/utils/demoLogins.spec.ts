// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import { consumeDemoLogin, DEMO_LOGIN_PASSWORD, stashDemoLogin } from './demoLogins'

const KEY = 'emc_demo_login'

describe('demoLogins', () => {
  afterEach(() => {
    sessionStorage.removeItem(KEY)
  })

  it('stores only email in sessionStorage', () => {
    stashDemoLogin('user@ematchef.ch')
    const raw = sessionStorage.getItem(KEY)
    expect(raw).toBeTruthy()
    expect(raw).not.toContain(DEMO_LOGIN_PASSWORD)
    expect(JSON.parse(raw!)).toEqual({ email: 'user@ematchef.ch' })
  })

  it('returns the shared demo password on consume', () => {
    stashDemoLogin('user@ematchef.ch')
    expect(consumeDemoLogin()).toEqual({
      email: 'user@ematchef.ch',
      password: DEMO_LOGIN_PASSWORD,
    })
  })
})
