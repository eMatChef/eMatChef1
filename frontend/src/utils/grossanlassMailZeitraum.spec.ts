import { describe, expect, it } from 'vitest'
import { suggestZeitraumTextFromPeriods } from '@/utils/grossanlassMailZeitraum'
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
    created_by_user_id: null,
    created_at: '',
    updated_at: '',
  }
}

describe('grossanlassMailZeitraum', () => {
  it('builds Aufbau / Anlass / Abbau lines from fixed dates', () => {
    expect(
      suggestZeitraumTextFromPeriods(
        [
          period('abbau', '2027-07-08', '2027-07-09'),
          period('aufbau', '2027-07-01', '2027-07-03'),
          period('grossanlass', '2027-07-04', '2027-07-06'),
        ],
        { aufbau: 'Aufbau', grossanlass: 'Anlass', abbau: 'Abbau' },
        'Der genaue Zeitraum richtet sich nach dem jeweiligen Material.',
      ),
    ).toBe(
      'Aufbau: 1.7.2027–3.7.2027\n'
        + 'Anlass: 4.7.2027–6.7.2027\n'
        + 'Abbau: 8.7.2027–9.7.2027\n'
        + 'Der genaue Zeitraum richtet sich nach dem jeweiligen Material.',
    )
  })

  it('returns empty when no event phases exist', () => {
    expect(
      suggestZeitraumTextFromPeriods(
        [period('school_vacation', '2027-07-01', '2027-07-14')],
        { aufbau: 'Aufbau', grossanlass: 'Anlass', abbau: 'Abbau' },
        'x',
      ),
    ).toBe('')
  })
})
