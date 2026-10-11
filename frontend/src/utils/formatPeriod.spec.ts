import { afterAll, beforeAll, describe, expect, it } from 'vitest'
import { formatEventPeriodCompact } from './formatPeriod'

describe('formatEventPeriodCompact', () => {
  const originalTz = process.env.TZ

  beforeAll(() => {
    process.env.TZ = 'Europe/Zurich'
  })

  afterAll(() => {
    if (originalTz === undefined) delete process.env.TZ
    else process.env.TZ = originalTz
  })

  it('shows the stored last day, not the next day, east of UTC', () => {
    expect(formatEventPeriodCompact('2026-10-15T00:00:00+00:00', '2026-10-17T23:59:59+00:00')).toBe('15.–17.10.26')
  })

  it('handles month and year changes and single days', () => {
    expect(formatEventPeriodCompact('2026-10-30T00:00:00+00:00', '2026-11-02T23:59:59+00:00')).toBe('30.10.–02.11.26')
    expect(formatEventPeriodCompact('2026-12-30T00:00:00+00:00', '2027-01-02T23:59:59+00:00')).toBe('30.12.26–02.01.27')
    expect(formatEventPeriodCompact('2026-10-15T00:00:00+00:00', '2026-10-15T23:59:59+00:00')).toBe('15.10.26')
    expect(formatEventPeriodCompact('2026-10-15', null)).toBe('15.10.26')
    expect(formatEventPeriodCompact(null, null)).toBe('')
  })
})
