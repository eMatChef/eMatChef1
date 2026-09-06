import { describe, expect, it } from 'vitest'
import { barStyleInWindow, spanningMonthWindow } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

describe('spanningMonthWindow', () => {
  it('covers both months of a wish that crosses month-end', () => {
    const window = spanningMonthWindow([
      new Date(2027, 7, 18, 12, 0),
      new Date(2027, 8, 15, 12, 0),
    ])
    expect(window?.start).toEqual(new Date(2027, 7, 1))
    expect(window?.end).toEqual(new Date(2027, 9, 1))
  })

  it('stays on one month when the span fits', () => {
    const window = spanningMonthWindow([
      new Date(2027, 7, 3, 8, 0),
      new Date(2027, 7, 20, 18, 0),
    ])
    expect(window?.start).toEqual(new Date(2027, 7, 1))
    expect(window?.end).toEqual(new Date(2027, 8, 1))
  })
})

describe('barStyleInWindow', () => {
  it('places a month bar at the time of day, not at midnight', () => {
    const start = new Date(2027, 7, 1)
    const end = new Date(2027, 9, 1)
    const style = barStyleInWindow(
      { id: 'w', fromIso: '2027-08-18T12:00:00', toIso: '2027-09-15T12:00:00' } as never,
      start,
      end,
      'month',
    )
    const noonOf18 = ((new Date(2027, 7, 18, 12, 0).getTime() - start.getTime()) / (end.getTime() - start.getTime())) * 100
    const midnightOf18 = ((new Date(2027, 7, 18).getTime() - start.getTime()) / (end.getTime() - start.getTime())) * 100
    const left = Number(style?.left.replace('%', ''))
    expect(left).toBeCloseTo(noonOf18, 4)
    expect(left).not.toBeCloseTo(midnightOf18, 1)
  })
})
