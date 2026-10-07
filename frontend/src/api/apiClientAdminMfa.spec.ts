// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { AxiosAdapter, AxiosResponse, InternalAxiosRequestConfig } from 'axios'
import apiClient, { setAdminMfaHandlers } from './apiClient'

function reply(config: InternalAxiosRequestConfig, status: number, data: unknown): Promise<AxiosResponse> {
  const response = { data, status, statusText: '', headers: {}, config } as AxiosResponse
  if (status >= 400) {
    return Promise.reject(Object.assign(new Error(`HTTP ${status}`), { config, response, isAxiosError: true }))
  }
  return Promise.resolve(response)
}

describe('apiClient admin MFA handling', () => {
  const originalAdapter = apiClient.defaults.adapter
  const stepUp = vi.fn<() => Promise<boolean>>()
  const setupRequired = vi.fn()
  let calls: string[]

  beforeEach(() => {
    calls = []
    stepUp.mockReset()
    setupRequired.mockReset()
    setAdminMfaHandlers({ stepUp, setupRequired })
  })

  afterEach(() => {
    apiClient.defaults.adapter = originalAdapter
    setAdminMfaHandlers(null)
  })

  function adapter(responses: Array<[number, unknown]>): AxiosAdapter {
    let i = 0
    return (config) => {
      calls.push(`${config.method} ${config.url}`)
      const [status, data] = responses[Math.min(i++, responses.length - 1)]
      return reply(config, status, data)
    }
  }

  it('step_up_required: opens step-up once and repeats the original request exactly once', async () => {
    apiClient.defaults.adapter = adapter([[403, { error: 'step_up_required' }], [200, { ok: true }]])
    stepUp.mockResolvedValue(true)

    const res = await apiClient.patch('/api/users/u1/admin', { global_admin_role: 'sub' })

    expect(res.data).toEqual({ ok: true })
    expect(stepUp).toHaveBeenCalledTimes(1)
    expect(calls).toEqual(['patch /api/users/u1/admin', 'patch /api/users/u1/admin'])
  })

  it('mfa_required behaves like step_up_required', async () => {
    apiClient.defaults.adapter = adapter([[403, { error: 'mfa_required' }], [200, {}]])
    stepUp.mockResolvedValue(true)

    await apiClient.get('/api/admin/security-monitoring')

    expect(stepUp).toHaveBeenCalledTimes(1)
    expect(calls).toHaveLength(2)
  })

  it('does not loop when the backend still denies after a successful step-up', async () => {
    apiClient.defaults.adapter = adapter([[403, { error: 'step_up_required' }]])
    stepUp.mockResolvedValue(true)

    await expect(apiClient.patch('/api/users/u1/admin', {})).rejects.toBeTruthy()

    expect(stepUp).toHaveBeenCalledTimes(1)
    expect(calls).toHaveLength(2)
  })

  it('does not retry when the step-up is cancelled', async () => {
    apiClient.defaults.adapter = adapter([[403, { error: 'step_up_required' }]])
    stepUp.mockResolvedValue(false)

    await expect(apiClient.patch('/api/users/u1/admin', {})).rejects.toBeTruthy()

    expect(calls).toHaveLength(1)
  })

  it('mfa_setup_required shows the setup hint and never retries', async () => {
    apiClient.defaults.adapter = adapter([[403, { error: 'mfa_setup_required', message: 'Richte 2FA ein' }]])

    await expect(apiClient.get('/api/admin/security-monitoring')).rejects.toBeTruthy()

    expect(setupRequired).toHaveBeenCalledWith('Richte 2FA ein')
    expect(stepUp).not.toHaveBeenCalled()
    expect(calls).toHaveLength(1)
  })

  it('leaves unrelated 403 responses alone', async () => {
    apiClient.defaults.adapter = adapter([[403, { error: 'Forbidden' }]])

    await expect(apiClient.get('/api/departments/d1')).rejects.toBeTruthy()

    expect(stepUp).not.toHaveBeenCalled()
    expect(setupRequired).not.toHaveBeenCalled()
  })

  it('never treats the step-up endpoint itself as a retry candidate', async () => {
    apiClient.defaults.adapter = adapter([[403, { error: 'step_up_required' }]])

    await expect(apiClient.post('/api/auth/step-up', { code: '1' })).rejects.toBeTruthy()

    expect(stepUp).not.toHaveBeenCalled()
  })
})
