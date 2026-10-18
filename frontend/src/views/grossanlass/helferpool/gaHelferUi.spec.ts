import { describe, expect, it } from 'vitest'
import type { GaCoverage, GaStaffingNeedRow } from '@/api/grossanlassStaffing'
import { barStyle, defaultScheduleStart, fullName, functionRows, helperMatchesWish, maxMissing, needState, percent, resolveHelferpoolTab, scheduleRange, splitIso, toIso } from './gaHelferUi'

const coverage = (over: Partial<GaCoverage> = {}): GaCoverage => ({
  required: 2, minutes: 240, segments: [], gaps: [], min_assigned: 2, peak_assigned: 2, fill_ratio: 1, overbooked: false, ...over,
})
const need = (over: Partial<GaStaffingNeedRow> = {}): GaStaffingNeedRow => ({
  id: 'n1', task_id: 't1', task_title: 'Zelt', is_default_task: false, group_id: 'g1', label: '', required_count: 2,
  starts_at: '2027-06-01T08:00:00', ends_at: '2027-06-01T12:00:00', dated: true, assigned_count: 0, coverage: coverage(), ...over,
})

describe('gaHelferUi', () => {
  it('converts between date/time inputs and the API timestamp', () => {
    expect(toIso('2027-06-01', '08:30')).toBe('2027-06-01T08:30:00')
    expect(toIso('', '08:30')).toBe('')
    expect(splitIso('2027-06-01T08:30:00')).toEqual({ date: '2027-06-01', time: '08:30' })
    expect(fullName({ first_name: 'Anna', last_name: '' })).toBe('Anna')
  })

  it('derives the coverage state from the computed coverage', () => {
    expect(needState(need({ dated: false, coverage: null }))).toBe('undated')
    expect(needState(need({ coverage: coverage({ gaps: [{ from: 'a', to: 'b', assigned: 0, missing: 2 }], fill_ratio: 0 }) }))).toBe('open')
    expect(needState(need({ assigned_count: 1, coverage: coverage({ gaps: [{ from: 'a', to: 'b', assigned: 1, missing: 1 }], fill_ratio: 0.5 }) }))).toBe('partial')
    expect(needState(need({ assigned_count: 2 }))).toBe('covered')
    expect(needState(need({ coverage: coverage({ overbooked: true }) }))).toBe('over')
    expect(needState(need({ required_count: 0, coverage: coverage({ required: 0, fill_ratio: null }) }))).toBe('covered')
  })

  it('formats ratios and the largest gap', () => {
    expect(percent(0.756)).toBe('76 %')
    expect(percent(null)).toBe('–')
    expect(maxMissing(coverage({ gaps: [{ from: 'a', to: 'b', assigned: 1, missing: 1 }, { from: 'b', to: 'c', assigned: 0, missing: 2 }] }))).toBe(2)
    expect(maxMissing(null)).toBe(0)
  })

  it('positions schedule bars within the range', () => {
    const { start } = scheduleRange('2027-06-01', 2)
    expect(barStyle('2027-06-01T12:00:00', '2027-06-02T00:00:00', start, 2)).toMatchObject({ left: '25%', width: '25%' })
    expect(barStyle('2027-05-01T00:00:00', '2027-05-02T00:00:00', start, 2).display).toBe('none')
    expect(scheduleRange('2027-06-01', 7)).toMatchObject({ from: '2027-06-01T00:00:00', to: '2027-06-08T00:00:00' })
  })

  it('starts the schedule at the earliest dated need', () => {
    expect(defaultScheduleStart([need({ starts_at: '2027-06-03T08:00:00' }), need({ starts_at: '2027-06-01T08:00:00' })])).toBe('2027-06-01')
    expect(defaultScheduleStart([need({ dated: false, starts_at: null, ends_at: null })], new Date(2027, 0, 5))).toBe('2027-01-05')
  })

  it('aggregates function wishes once and weights coverage by person-minutes', () => {
    const rows = functionRows([
      need({ id: 'a', label: 'Elektriker', required_count: 2, assigned_count: 1, coverage: coverage({ fill_ratio: 0.5 }) }),
      need({ id: 'b', label: 'Elektriker', required_count: 2, assigned_count: 2, coverage: coverage({ fill_ratio: 1 }) }),
      need({ id: 'c', label: '', required_count: 3, assigned_count: 0, coverage: coverage({ fill_ratio: 0 }) }),
      need({ id: 'd', label: 'Koch', required_count: 0 }),
    ])
    expect(rows.map((r) => r.label)).toEqual(['Elektriker', ''])
    expect(rows[0]).toMatchObject({ required: 4, assigned: 3, fill: 0.75, needs: 2 })
    expect(rows[1]).toMatchObject({ required: 3, assigned: 0, fill: 0 })
  })
})

describe('helperMatchesWish', () => {
  it('matches a wished skill case-insensitively and never without a wish', () => {
    expect(helperMatchesWish({ skills: ['Elektro', 'Gabelstapler'] }, ' elektro ')).toBe(true)
    expect(helperMatchesWish({ skills: ['Elektro'] }, 'Koch')).toBe(false)
    expect(helperMatchesWish({ skills: ['Elektro'] }, '')).toBe(false)
    expect(helperMatchesWish({}, 'Elektro')).toBe(false)
  })
})

describe('resolveHelferpoolTab', () => {
  it('übernimmt gültige Werte und fällt sonst auf den Standardreiter zurück', () => {
    expect(resolveHelferpoolTab('api', true)).toBe('api')
    expect(resolveHelferpoolTab(['timeline', 'x'], true)).toBe('timeline')
    expect(resolveHelferpoolTab('nope', true)).toBe('helpers')
    expect(resolveHelferpoolTab(undefined, true)).toBe('helpers')
  })
  it('lässt Nicht-Berechtigte nur den Personalbedarf öffnen', () => {
    expect(resolveHelferpoolTab('api', false)).toBe('needs')
    expect(resolveHelferpoolTab(undefined, false)).toBe('needs')
  })
})
