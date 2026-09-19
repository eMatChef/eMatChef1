import { describe, expect, it } from 'vitest'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'

describe('formatBauprojektWindow', () => {
  it('returns empty when neither bound is set', () => {
    expect(formatBauprojektWindow(null, null)).toBe('')
    expect(formatBauprojektWindow('', '')).toBe('')
  })

  it('collapses a single-day window', () => {
    expect(formatBauprojektWindow('2026-09-18', '2026-09-18')).toBe('2026-09-18')
  })

  it('joins a range', () => {
    expect(formatBauprojektWindow('2026-09-18T00:00:00Z', '2026-09-20')).toBe('2026-09-18 – 2026-09-20')
  })
})
