import { describe, expect, it } from 'vitest'
import {
  calendarRangeForPhaseChoice,
  isWishPhaseSelectField,
  mapWishPhaseChoiceToCalendarLabel,
  periodsWithinEvent,
  resolveWishNeedPeriod,
  unionCalendarPeriods,
  wishPeriodLooksLikeSubmitTime,
  wishPeriodLooksUnreliable,
  wishPeriodMatchesRange,
} from '@/utils/grossanlassWishPeriod'
import type { DepartmentCalendarPeriod } from '@/api/calendarPeriods'

function period(
  label: DepartmentCalendarPeriod['label'],
  start: string,
  end: string,
): DepartmentCalendarPeriod {
  return {
    id: label,
    department_id: 'dept',
    label,
    name: label,
    start_date: start,
    end_date: end,
    start_time: '08:00',
    end_time: '18:00',
    created_by_user_id: null,
    created_at: '',
    updated_at: '',
  }
}

describe('grossanlassWishPeriod', () => {
  it('maps Vor/Am/Nach to Fixe Daten', () => {
    expect(mapWishPhaseChoiceToCalendarLabel('Vor dem Anlass')).toBe('aufbau')
    expect(mapWishPhaseChoiceToCalendarLabel('Am Anlass')).toBe('grossanlass')
    expect(mapWishPhaseChoiceToCalendarLabel('Nach dem Anlass')).toBe('abbau')
  })

  it('unions selected Fixe Daten in the event year', () => {
    const range = unionCalendarPeriods(
      [
        period('aufbau', '2027-08-20', '2027-08-21'),
        period('grossanlass', '2027-08-21', '2027-09-10'),
        period('abbau', '2027-09-10', '2027-09-11'),
        period('aufbau', '2026-08-20', '2026-08-21'),
      ],
      ['aufbau', 'grossanlass', 'abbau'],
    )
    expect(range).toEqual({
      from: '2027-08-20T08:00:00',
      to: '2027-09-11T18:00:00',
    })
  })

  it('detects submit-time stored as need period', () => {
    expect(wishPeriodLooksLikeSubmitTime(
      '2026-08-30T15:31:00+02:00',
      '2026-08-30T15:31:00+02:00',
      '2026-08-30T15:31:12+02:00',
    )).toBe(true)
    expect(wishPeriodLooksLikeSubmitTime(
      '2026-08-30T15:23:00+02:00',
      '2026-08-30T15:23:00+02:00',
      '2026-08-30T17:31:00+02:00',
    )).toBe(true)
    expect(wishPeriodLooksLikeSubmitTime(
      '2027-08-21T12:00:00',
      '2027-09-11T12:00:00',
      '2026-08-30T15:31:00',
    )).toBe(false)
  })

  it('resolves a phase choice to its Fixe-Daten range', () => {
    expect(calendarRangeForPhaseChoice(
      'Vor dem Anlass',
      [
        period('aufbau', '2027-08-18', '2027-09-02'),
        period('grossanlass', '2027-09-03', '2027-09-05'),
      ],
    )).toEqual({
      from: '2027-08-18T08:00:00',
      to: '2027-09-02T18:00:00',
    })
  })

  it('recognizes Vor/Am/Nach as the combined when-field', () => {
    expect(isWishPhaseSelectField({
      custom_type: 'select',
      options: { multiple: true, choices: ['Vor dem Anlass', 'Am Anlass', 'Nach dem Anlass'] },
    })).toBe(true)
    expect(isWishPhaseSelectField({
      custom_type: 'select',
      options: { choices: ['Rot', 'Blau'] },
    })).toBe(false)
    expect(isWishPhaseSelectField({ custom_type: 'text' })).toBe(false)
  })

  it('treats submit-time and wrong event year as unreliable', () => {
    const periods = [period('grossanlass', '2027-09-03', '2027-09-05')]
    expect(wishPeriodLooksUnreliable(
      '2026-06-21T17:45:00',
      '2026-06-21T17:45:00',
      '2026-06-21T17:45:12',
      periods,
    )).toBe(true)
    expect(wishPeriodLooksUnreliable(
      '2026-08-28T08:00:00',
      '2026-08-30T18:00:00',
      '2026-06-21T17:45:00',
      periods,
    )).toBe(true)
    expect(wishPeriodLooksUnreliable(
      '2027-08-18T12:00:00',
      '2027-09-15T12:00:00',
      '2026-06-21T17:45:00',
      periods,
    )).toBe(false)
  })

  it('resolves Bedarf from Vor/Am/Nach when stored period is the submit instant', () => {
    const periods = [
      period('aufbau', '2027-08-18', '2027-09-02'),
      period('grossanlass', '2027-09-03', '2027-09-05'),
      period('abbau', '2027-09-05', '2027-09-15'),
    ]
    expect(resolveWishNeedPeriod({
      valid_from: '2026-06-21T17:45:00',
      valid_to: '2026-06-21T17:45:00',
      created_at: '2026-06-21T17:45:12',
      custom_values: {
        when: ['Vor dem Anlass', 'Am Anlass', 'Nach dem Anlass'],
      },
    }, periods)).toEqual({
      from: '2027-08-18T08:00:00',
      to: '2027-09-15T18:00:00',
    })
  })

  it('keeps a real custom date that does not match the phases', () => {
    const periods = [
      period('aufbau', '2027-08-18', '2027-09-02'),
      period('grossanlass', '2027-09-03', '2027-09-05'),
    ]
    expect(resolveWishNeedPeriod({
      valid_from: '2027-08-01T08:00:00',
      valid_to: '2027-08-02T18:00:00',
      created_at: '2026-06-21T17:45:00',
      custom_values: { when: ['Am Anlass'] },
    }, periods)).toEqual({
      from: '2027-08-01T08:00:00',
      to: '2027-08-02T18:00:00',
    })
  })

  it('matches stored need period to Fixe-Daten union', () => {
    expect(wishPeriodMatchesRange(
      '2027-08-20T08:00:00',
      '2027-09-11T18:00:00',
      { from: '2027-08-20T08:00:00', to: '2027-09-11T18:00:00' },
    )).toBe(true)
    expect(wishPeriodMatchesRange(
      '2027-06-01T08:00:00',
      '2027-06-02T18:00:00',
      { from: '2027-08-20T08:00:00', to: '2027-09-11T18:00:00' },
    )).toBe(false)
  })
})

describe('Event 2027: fixe Daten im Anlasszeitraum', () => {
  function timed(
    label: DepartmentCalendarPeriod['label'],
    start: [string, string],
    end: [string, string],
  ): DepartmentCalendarPeriod {
    return { ...period(label, start[0], end[0]), start_time: start[1], end_time: end[1] }
  }
  const aufbau = timed('aufbau', ['2027-05-19', '13:00'], ['2027-06-04', '16:00'])
  const durchfuehrung = timed('grossanlass', ['2027-06-04', '16:00'], ['2027-06-06', '16:00'])
  const abbau = timed('abbau', ['2027-06-06', '17:00'], ['2027-06-23', '12:00'])
  const anlass = { start: '2027-05-19T13:00:00', end: '2027-06-23T12:00:00' }

  it('offers all three phases with their own time range', () => {
    const periods = periodsWithinEvent([abbau, durchfuehrung, aufbau], anlass)
    expect(periods).toHaveLength(3)
    expect(calendarRangeForPhaseChoice('Aufbau', periods)).toEqual({ from: '2027-05-19T13:00:00', to: '2027-06-04T16:00:00' })
    expect(calendarRangeForPhaseChoice('Während Event', periods)).toEqual({ from: '2027-06-04T16:00:00', to: '2027-06-06T16:00:00' })
    expect(calendarRangeForPhaseChoice('Abbau', periods)).toEqual({ from: '2027-06-06T17:00:00', to: '2027-06-23T12:00:00' })
  })

  it('keeps the one-hour gap between operation and teardown', () => {
    const union = unionCalendarPeriods([durchfuehrung, abbau], ['grossanlass', 'abbau'])
    expect(union).toEqual({ from: '2027-06-04T16:00:00', to: '2027-06-23T12:00:00' })
    expect(calendarRangeForPhaseChoice('Während Event', [durchfuehrung, abbau])?.to).toBe('2027-06-06T16:00:00')
    expect(calendarRangeForPhaseChoice('Abbau', [durchfuehrung, abbau])?.from).toBe('2027-06-06T17:00:00')
  })

  it('does not offer fixed dates outside the event period', () => {
    const outside = timed('abbau', ['2027-06-23', '13:00'], ['2027-06-30', '12:00'])
    const stray = timed('other', ['2026-03-01', '08:00'], ['2026-03-02', '18:00'])
    const kept = periodsWithinEvent([aufbau, durchfuehrung, outside, stray], anlass)
    expect(kept.map((p) => p.label)).toEqual(['aufbau', 'grossanlass'])
  })

  it('leaves the list untouched without a known event period', () => {
    const all = [aufbau, durchfuehrung, abbau]
    expect(periodsWithinEvent(all, null)).toBe(all)
    expect(periodsWithinEvent(all, { start: '2027-05-19T13:00:00', end: null })).toBe(all)
  })
})
