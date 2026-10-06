// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const apiLogin = vi.fn()
const apiVerifyMfa = vi.fn()

vi.mock('@/api/auth', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/auth')>()
  return { ...actual, login: (...a: unknown[]) => apiLogin(...a), verifyMfa: (...a: unknown[]) => apiVerifyMfa(...a) }
})

import { useAuthStore } from './auth'

const challenge = { mfa_required: true, challenge: 'abc', methods: ['totp', 'recovery_code'], expires_in: 300 }

describe('auth store MFA', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiLogin.mockReset()
    apiVerifyMfa.mockReset()
  })

  it('does not create a session when a challenge is required', async () => {
    apiLogin.mockResolvedValue(challenge)
    const store = useAuthStore()

    const ok = await store.login('a@b.test', 'pw')

    expect(ok).toBe(false)
    expect(store.pendingMfa?.challenge).toBe('abc')
    expect(store.isLoggedIn).toBe(false)
    expect(store.error).toBeNull()
  })

  it('keeps the challenge pending after a wrong code', async () => {
    apiLogin.mockResolvedValue(challenge)
    apiVerifyMfa.mockRejectedValue({ response: { status: 400, data: { code: 'invalid_code', error: 'Der Code ist ungültig.' } } })
    const store = useAuthStore()
    await store.login('a@b.test', 'pw')

    expect(await store.completeMfa('totp', '000000')).toBe(false)
    expect(store.pendingMfa).not.toBeNull()
    expect(store.error).toBe('Der Code ist ungültig.')
  })

  it('drops the challenge when it expired or was used', async () => {
    apiLogin.mockResolvedValue(challenge)
    apiVerifyMfa.mockRejectedValue({ response: { status: 401, data: { code: 'invalid_challenge', error: 'abgelaufen' } } })
    const store = useAuthStore()
    await store.login('a@b.test', 'pw')

    expect(await store.completeMfa('totp', '123456')).toBe(false)
    expect(store.pendingMfa).toBeNull()
  })

  it('passes the trust-device choice to the verification and keeps the trust days of the challenge', async () => {
    apiLogin.mockResolvedValue({ ...challenge, trust_days: 30 })
    apiVerifyMfa.mockRejectedValue({ response: { status: 400, data: { code: 'invalid_code', error: 'x' } } })
    const store = useAuthStore()
    await store.login('a@b.test', 'pw')

    expect(store.pendingMfa?.trustDays).toBe(30)
    await store.completeMfa('totp', '123456', true)
    await store.completeMfa('totp', '123456')

    expect(apiVerifyMfa).toHaveBeenNthCalledWith(1, 'abc', 'totp', '123456', true)
    expect(apiVerifyMfa).toHaveBeenNthCalledWith(2, 'abc', 'totp', '123456', false)
  })

  it('cancelMfa clears the challenge', () => {
    const store = useAuthStore()
    store.beginMfa('xyz')
    expect(store.pendingMfa?.challenge).toBe('xyz')
    store.cancelMfa()
    expect(store.pendingMfa).toBeNull()
  })
})
