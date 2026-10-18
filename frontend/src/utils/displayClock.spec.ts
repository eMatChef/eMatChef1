import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { applyDisplayClock, displayClockRevision, displayNow, isDisplayClockDemo, resetDisplayClock } from './displayClock'

describe('displayClock', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date(2026, 9, 18, 10, 0, 0))
    resetDisplayClock()
  })
  afterEach(() => {
    resetDisplayClock()
    vi.useRealTimers()
  })

  it('uses the real time without a demo clock', () => {
    applyDisplayClock({ mode: 'real' })
    expect(isDisplayClockDemo()).toBe(false)
    expect(displayNow().getTime()).toBe(Date.now())
  })

  it('shows the business time in demo mode and keeps it running', () => {
    expect(applyDisplayClock({ mode: 'demo', now: '2027-05-19T13:00:00', offset_seconds: 1 })).toBe(true)
    expect(isDisplayClockDemo()).toBe(true)
    expect(displayNow()).toEqual(new Date(2027, 4, 19, 13, 0, 0))
    vi.advanceTimersByTime(90_000)
    expect(displayNow()).toEqual(new Date(2027, 4, 19, 13, 1, 30))
  })

  it('detects a clock jump but not normal running time', () => {
    applyDisplayClock({ mode: 'demo', now: '2027-05-19T13:00:00' })
    const rev = displayClockRevision()
    vi.advanceTimersByTime(8_000)
    expect(applyDisplayClock({ mode: 'demo', now: '2027-05-19T13:00:08' })).toBe(false)
    expect(displayClockRevision()).toBe(rev)
    vi.advanceTimersByTime(8_000)
    expect(applyDisplayClock({ mode: 'demo', now: '2027-06-04T16:00:00' })).toBe(true)
    expect(displayClockRevision()).toBe(rev + 1)
    expect(displayNow()).toEqual(new Date(2027, 5, 4, 16, 0, 0))
  })

  it('returns to the real time when the department leaves demo mode', () => {
    applyDisplayClock({ mode: 'demo', now: '2027-05-19T13:00:00' })
    expect(applyDisplayClock({ mode: 'real' })).toBe(true)
    expect(displayNow().getTime()).toBe(Date.now())
  })
})
