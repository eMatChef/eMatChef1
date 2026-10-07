// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import type { AxiosResponse } from 'axios'
import apiClient from './apiClient'
import { verifyMfa } from './auth'

describe('verifyMfa request body', () => {
  const original = apiClient.defaults.adapter
  afterEach(() => {
    apiClient.defaults.adapter = original
  })

  function capture(): { bodies: Array<Record<string, unknown>> } {
    const state = { bodies: [] as Array<Record<string, unknown>> }
    apiClient.defaults.adapter = (config) => {
      state.bodies.push(JSON.parse(String(config.data)))
      const data = { token: 't', user: { id: 'u' }, profile: { id: 'p' } }
      return Promise.resolve({ data, status: 200, statusText: '', headers: {}, config } as AxiosResponse)
    }
    return state
  }

  it('sends trust_device only when the user chose it', async () => {
    const state = capture()

    await verifyMfa('c', 'totp', '123456')
    await verifyMfa('c', 'recovery_code', 'AAAAA-BBBBB', true)

    expect(state.bodies[0]).toEqual({ challenge: 'c', method: 'totp', code: '123456' })
    expect(state.bodies[1]).toEqual({ challenge: 'c', method: 'recovery_code', code: 'AAAAA-BBBBB', trust_device: true })
  })
})
