// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import type { AxiosResponse } from 'axios'
import apiClient from './apiClient'
import { releaseGrossanlassGuestMaterial, updateGrossanlassGuestRelease, withdrawGrossanlassGuestRelease } from './grossanlassGaeste'

describe('guest release requests', () => {
  const original = apiClient.defaults.adapter
  afterEach(() => {
    apiClient.defaults.adapter = original
  })

  function capture(): Array<{ method?: string; url?: string; body: unknown }> {
    const calls: Array<{ method?: string; url?: string; body: unknown }> = []
    apiClient.defaults.adapter = (config) => {
      calls.push({ method: config.method, url: config.url, body: config.data ? JSON.parse(String(config.data)) : null })
      return Promise.resolve({ data: { items: [], releases: [] }, status: 200, statusText: '', headers: {}, config } as AxiosResponse)
    }
    return calls
  }

  it('sends quantity and time window with the release', async () => {
    const calls = capture()
    await releaseGrossanlassGuestMaterial('g1', 'h1', { material_item_id: 'm1', qty: 4, from: '2027-06-01', to: '2027-06-05' })
    expect(calls[0]).toEqual({
      method: 'post',
      url: '/api/departments/g1/grossanlass/hosts/h1/freigaben',
      body: { material_item_id: 'm1', qty: 4, from: '2027-06-01', to: '2027-06-05' },
    })
  })

  it('updates and withdraws a release of the own department only through apiClient', async () => {
    const calls = capture()
    await updateGrossanlassGuestRelease('g1', 'h1', 's1', { qty: 3 })
    await withdrawGrossanlassGuestRelease('g1', 'h1', 's1')
    expect(calls.map((c) => [c.method, c.url])).toEqual([
      ['patch', '/api/departments/g1/grossanlass/hosts/h1/freigaben/s1'],
      ['delete', '/api/departments/g1/grossanlass/hosts/h1/freigaben/s1'],
    ])
    expect(calls[0].body).toEqual({ qty: 3 })
  })
})
