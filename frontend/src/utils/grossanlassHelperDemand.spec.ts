import { describe, expect, it } from 'vitest'
import { helperDemandPeak } from './grossanlassHelperDemand'

const h = (hour: number) => Date.UTC(2027, 5, 1, hour)

describe('helperDemandPeak', () => {
  it('adds nothing for time-separated tasks', () => {
    expect(helperDemandPeak([
      { startMs: h(8), endMs: h(10), total: 5 },
      { startMs: h(13), endMs: h(15), total: 4 },
    ]).peak).toBe(5)
  })

  it('does not overlap back-to-back tasks', () => {
    expect(helperDemandPeak([
      { startMs: h(8), endMs: h(10), total: 5 },
      { startMs: h(10), endMs: h(12), total: 4 },
    ]).peak).toBe(5)
  })

  it('adds overlapping tasks and undated tasks', () => {
    expect(helperDemandPeak([
      { startMs: h(8), endMs: h(10), total: 5 },
      { startMs: h(9), endMs: h(11), total: 4 },
      { startMs: null, endMs: null, total: 2 },
    ])).toEqual({ peak: 11, undated: 2 })
  })

  it('ignores tasks without need', () => {
    expect(helperDemandPeak([{ startMs: h(8), endMs: h(10), total: 0 }]).peak).toBe(0)
  })
})
