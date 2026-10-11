// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { AxiosResponse } from 'axios'
import apiClient from '@/api/apiClient'
import { formatWallClock, parseWallClock, useBusinessClockStore } from './businessClock'

const wall = formatWallClock

describe('business clock store', () => {
  const original = apiClient.defaults.adapter
  const calls: Array<{ method?: string; url?: string; data?: string }> = []
  const last = () => calls[calls.length - 1]

  beforeEach(() => {
    setActivePinia(createPinia())
    calls.length = 0
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-07T12:00:00Z'))
    apiClient.defaults.adapter = (config) => {
      calls.push({ method: config.method, url: config.url, data: config.data })
      const real = new Date().toISOString()
      const data = config.method === 'get'
        ? { mode: 'demo', now: '2026-11-04T10:00:00', real_now: real, offset_seconds: 0, can_travel: true }
        : { mode: 'demo', now: JSON.parse(String(config.data ?? '{"now":"2026-11-01T09:00:00"}')).now, real_now: real, offset_seconds: 0, can_travel: true }
      return Promise.resolve({ data, status: 200, statusText: '', headers: {}, config } as AxiosResponse)
    }
  })

  afterEach(() => {
    apiClient.defaults.adapter = original
    vi.useRealTimers()
  })

  it('keeps the displayed time running after load', async () => {
    const store = useBusinessClockStore()
    await store.load('dep000000001')

    expect(store.canTravel).toBe(true)
    expect(wall(store.displayNow)).toBe('2026-11-04T10:00:00')
    vi.advanceTimersByTime(60_000)
    expect(wall(store.displayNow)).toBe('2026-11-04T10:01:00')
    store.stopTicking()
  })

  it('travels through the API client, bumps the revision and shifts relative to the displayed time', async () => {
    const store = useBusinessClockStore()
    await store.load('dep000000001')

    await store.shift(3_600_000)

    expect(last()).toMatchObject({ method: 'put', url: '/api/departments/dep000000001/clock' })
    expect(JSON.parse(last().data!).now).toBe('2026-11-04T11:00:00')
    expect(store.revision).toBe(1)

    await store.reset()
    expect(last().method).toBe('delete')
    expect(store.revision).toBe(2)
    store.stopTicking()
  })

  it('round-trips wall-clock strings without timezone shifts', () => {
    expect(formatWallClock(parseWallClock('2026-10-09T09:00:00'))).toBe('2026-10-09T09:00:00')
  })

  it('exposes the server-derived quick selection and travels to its wall time unchanged', async () => {
    const store = useBusinessClockStore()
    apiClient.defaults.adapter = (config) => {
      calls.push({ method: config.method, url: config.url, data: config.data })
      const body = config.method === 'get'
        ? { mode: 'demo', now: '2027-04-04T16:00:00', real_now: new Date().toISOString(), offset_seconds: 0, can_travel: true, presets: [{ key: 'during_event', at: '2027-06-05T12:00:00' }] }
        : { mode: 'demo', now: JSON.parse(String(config.data)).now, real_now: new Date().toISOString(), offset_seconds: 0, can_travel: true }
      return Promise.resolve({ data: body, status: 200, statusText: '', headers: {}, config } as AxiosResponse)
    }
    await store.load('dep000000001')

    expect(store.presets).toEqual([{ key: 'during_event', at: '2027-06-05T12:00:00' }])
    await store.travelToWall(store.presets[0].at)
    expect(JSON.parse(String(last().data)).now).toBe('2027-06-05T12:00:00')
    expect(wall(store.displayNow)).toBe('2027-06-05T12:00:00')
    store.stopTicking()
  })

  it('shifts on the wall clock across daylight saving changes (Europe/Zurich)', async () => {
    const originalTz = process.env.TZ
    process.env.TZ = 'Europe/Zurich'
    try {
      const store = useBusinessClockStore()
      apiClient.defaults.adapter = (config) => {
        calls.push({ method: config.method, url: config.url, data: config.data })
        const now = config.method === 'get' ? '2027-03-27T16:00:00' : JSON.parse(String(config.data)).now
        return Promise.resolve({ data: { mode: 'demo', now, real_now: new Date().toISOString(), offset_seconds: 0, can_travel: true }, status: 200, statusText: '', headers: {}, config } as AxiosResponse)
      }
      await store.load('dep000000001')

      await store.shift(86_400_000) // +1 Tag über die Umstellung vom 28.03.2027: gleiche Uhrzeit
      expect(JSON.parse(String(last().data)).now).toBe('2027-03-28T16:00:00')
      await store.travelToWall('2027-03-28T01:30')
      await store.shift(3_600_000) // 02:30 existiert als lokale Zeit nicht, ist als Wandzeit aber gültig
      expect(JSON.parse(String(last().data)).now).toBe('2027-03-28T02:30:00')
      store.stopTicking()
    } finally {
      if (originalTz === undefined) delete process.env.TZ
      else process.env.TZ = originalTz
    }
  })
})
