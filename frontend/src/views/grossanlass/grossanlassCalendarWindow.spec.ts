import { describe, expect, it } from 'vitest'
import {
  barStyleInWindow,
  formatCalendarTitle,
  midMonthWindow,
  shiftCalendarAnchor,
  spanningMonthWindow,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { resolveCalendarWindow } from '@/views/grossanlass/gaCalendarAxis'

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

describe('resolveCalendarWindow', () => {
  it('spans event months until the axis is pinned', () => {
    const window = resolveCalendarWindow(
      'month',
      new Date(2027, 7, 16),
      [new Date(2027, 7, 18), new Date(2027, 8, 3)],
      true,
    )
    expect(window.start).toEqual(new Date(2027, 7, 1))
    expect(window.end).toEqual(new Date(2027, 9, 1))
  })

  it('uses a single month after the first arrow', () => {
    const window = resolveCalendarWindow(
      'month',
      new Date(2027, 8, 1),
      [new Date(2027, 7, 18), new Date(2027, 8, 3)],
      false,
    )
    expect(window.start).toEqual(new Date(2027, 8, 1))
    expect(window.end).toEqual(new Date(2027, 9, 1))
  })
})

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

describe('shiftCalendarAnchor', () => {
  it('jumps full months from the 1st so the month line stays put', () => {
    const next = shiftCalendarAnchor('month', new Date(2027, 7, 31), 1)
    expect(next).toEqual(new Date(2027, 8, 1))
    const prev = shiftCalendarAnchor('month', new Date(2027, 8, 15), -1)
    expect(prev).toEqual(new Date(2027, 7, 1))
  })
})

describe('midMonthWindow', () => {
  it('shows the second half of this month and the first half of the next', () => {
    const window = midMonthWindow(new Date(2027, 7, 1))
    expect(window.start).toEqual(new Date(2027, 7, 16))
    expect(window.end).toEqual(new Date(2027, 8, 16))
  })
})

describe('formatCalendarTitle', () => {
  it('names both months when the event crosses month-end', () => {
    const title = formatCalendarTitle(
      'month',
      new Date(2027, 7, 1),
      new Date(2027, 9, 1),
      'de-CH',
    )
    expect(title.toLowerCase()).toContain('aug')
    expect(title.toLowerCase()).toContain('sep')
  })
})
