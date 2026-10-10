// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import type { AxiosResponse } from 'axios'
import apiClient from './apiClient'
import { setPrimaryDepartment } from './auth'

describe('setPrimaryDepartment request', () => {
  const original = apiClient.defaults.adapter
  afterEach(() => {
    apiClient.defaults.adapter = original
  })

  function capture(): Array<{ method?: string; url?: string; body: unknown }> {
    const calls: Array<{ method?: string; url?: string; body: unknown }> = []
    apiClient.defaults.adapter = (config) => {
      calls.push({ method: config.method, url: config.url, body: JSON.parse(String(config.data)) })
      return Promise.resolve({ data: { success: true }, status: 200, statusText: '', headers: {}, config } as AxiosResponse)
    }
    return calls
  }

  it('sends the new primary department through apiClient', async () => {
    const calls = capture()
    await setPrimaryDepartment('u1', 'dep1')
    expect(calls).toEqual([{ method: 'put', url: '/api/users/u1/set-primary-department', body: { department_id: 'dep1' } }])
  })

  it('sends null to remove the primary status', async () => {
    const calls = capture()
    await setPrimaryDepartment('u1', null)
    expect(calls[0].body).toEqual({ department_id: null })
  })
})
