import { describe, expect, it } from 'vitest'
import type { DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import { calendarPeriodViewDate } from '@/utils/activityDatePresets'

function period(
  partial: Pick<DepartmentCalendarPeriod, 'label' | 'start_date' | 'end_date'> & { id?: string },
): DepartmentCalendarPeriod {
  return {
    id: partial.id ?? partial.label,
    department_id: 'd1',
    name: partial.label,
    created_by_user_id: null,
    created_at: '',
    updated_at: '',
    ...partial,
  }
}

describe('calendarPeriodViewDate', () => {
  it('prefers the event window when set', () => {
    const event = new Date(2026, 10, 13)
    const view = calendarPeriodViewDate(
      [period({ label: 'aufbau', start_date: '2026-10-01', end_date: '2026-10-03' })],
      event,
    )
    expect(view?.getFullYear()).toBe(2026)
    expect(view?.getMonth()).toBe(10)
    expect(view?.getDate()).toBe(13)
  })

  it('falls back to Event-Durchführung before Aufbau', () => {
    const view = calendarPeriodViewDate([
      period({ label: 'aufbau', start_date: '2026-11-01', end_date: '2026-11-05' }),
      period({ label: 'grossanlass', start_date: '2026-11-13', end_date: '2026-11-15' }),
    ])
    expect(view?.getDate()).toBe(13)
    expect(view?.getMonth()).toBe(10)
  })

  it('ignores past and office-closed periods', () => {
    const view = calendarPeriodViewDate([
      period({ label: 'grossanlass', start_date: '2025-01-01', end_date: '2025-01-03' }),
      period({ label: 'department_break', start_date: '2026-12-01', end_date: '2026-12-05' }),
      period({ label: 'other', start_date: '2026-12-10', end_date: '2026-12-12' }),
    ])
    expect(view?.getDate()).toBe(10)
    expect(view?.getMonth()).toBe(11)
  })

  it('returns null when nothing applies', () => {
    expect(calendarPeriodViewDate([])).toBeNull()
  })
})
