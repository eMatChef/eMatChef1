// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { AxiosResponse } from 'axios'
import apiClient from '@/api/apiClient'
import { useBusinessClockStore } from '@/stores/businessClock'
import { bucketOf, matchesFilters, emptyAufgabeFilters, statusCounts, type GaAufgabe } from './gaAufgabenMock'
import { statusKey, useGaNow } from './gaAufgabenUi'

const task = (id: string, startsAt: Date, endsAt: Date): GaAufgabe =>
  ({ id, status: 'open', kind: 'arbeit', startsAt, endsAt, origin: [], people: [], title: id, description: '', ressort: '', bereich: '' }) as unknown as GaAufgabe

describe('useGaNow (BusinessClock)', () => {
  const original = apiClient.defaults.adapter
  let mode: 'real' | 'demo' = 'demo'
  let wallNow = '2027-06-10T10:00:00'

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
    vi.setSystemTime(new Date(2026, 9, 13, 12, 0, 0))
    apiClient.defaults.adapter = (config) => {
      const body = config.method === 'put' ? JSON.parse(String(config.data)).now : wallNow
      const data = { mode, now: body, real_now: body, offset_seconds: 0, can_travel: mode !== 'real' }
      return Promise.resolve({ data, status: 200, statusText: '', headers: {}, config } as AxiosResponse)
    }
  })
  afterEach(() => {
    apiClient.defaults.adapter = original
    vi.useRealTimers()
  })

  it('nutzt ohne Demo-Modus die normale aktuelle Zeit', async () => {
    mode = 'real'
    const store = useBusinessClockStore()
    await store.load('d1')
    expect(useGaNow().value.getTime()).toBe(new Date(2026, 9, 13, 12, 0, 0).getTime())
  })

  it('folgt der Demo-Zeit und einer Zeitreise für Heute/Überfällig/Später', async () => {
    mode = 'demo'
    wallNow = '2027-06-10T10:00:00'
    const store = useBusinessClockStore()
    await store.load('d1')
    const now = useGaNow()
    const a = task('a', new Date(2027, 5, 10, 8), new Date(2027, 5, 10, 9)) // vorbei
    const b = task('b', new Date(2027, 5, 10, 9), new Date(2027, 5, 10, 18)) // heute
    const c = task('c', new Date(2027, 5, 12, 9), new Date(2027, 5, 12, 10)) // später

    expect(now.value.getFullYear()).toBe(2027)
    expect(statusKey(a, now.value)).toBe('overdue')
    expect(bucketOf(a, now.value)).toBe('overdue')
    expect(bucketOf(b, now.value)).toBe('today')
    expect(bucketOf(c, now.value)).toBe('later')
    const today = { ...emptyAufgabeFilters(), period: 'today' as const }
    expect([a, b, c].filter((t) => matchesFilters(t, today, { now: now.value })).map((t) => t.id)).toEqual(['a', 'b'])
    expect(statusCounts([a, b, c], now.value).overdue).toBe(1)

    await store.travelToWall('2027-06-12T09:30:00')
    expect(bucketOf(a, now.value)).toBe('overdue')
    expect(bucketOf(b, now.value)).toBe('overdue')
    expect(bucketOf(c, now.value)).toBe('today')
    expect(statusCounts([a, b, c], now.value).overdue).toBe(2)
  })
})
