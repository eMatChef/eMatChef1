import { describe, expect, it } from 'vitest'
import {
  calendarRangeForPhaseChoice,
  isWishPhaseSelectField,
  mapWishPhaseChoiceToCalendarLabel,
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
