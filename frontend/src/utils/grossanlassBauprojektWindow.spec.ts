import { describe, expect, it } from 'vitest'
import {
  formatBauprojektWindow,
  packBauprojektWindow,
  unpackBauprojektWindow,
} from '@/utils/grossanlassBauprojektWindow'

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

describe('packBauprojektWindow', () => {
  it('packs empty bounds as a pipe', () => {
    expect(packBauprojektWindow('', '')).toBe('|')
    expect(packBauprojektWindow(null, null)).toBe('|')
  })

  it('round-trips a range', () => {
    expect(unpackBauprojektWindow(packBauprojektWindow('2026-10-28', '2026-11-13'))).toEqual({
      start: '2026-10-28',
      end: '2026-11-13',
    })
  })
})
