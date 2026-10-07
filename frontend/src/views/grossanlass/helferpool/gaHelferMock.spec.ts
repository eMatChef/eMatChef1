import { beforeEach, describe, expect, it } from 'vitest'
import { resetGaAufgabenDemo, useGaAufgabenMock, anlassAt } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import {
  GA_ANLASS_PERIOD,
  GA_HELPERS,
  assignHelper,
  assignmentsOf,
  emptyHelperFilters,
  freeWindows,
  helperStatus,
  matchNeed,
  matchesHelper,
  needTasks,
  skillMatrix,
  staffing,
  statusCounts,
  unassignHelper,
} from './gaHelferMock'

const helper = (name: string) => GA_HELPERS.find((entry) => entry.name === name)!
const need = () => needTasks()[0]!

describe('gaHelferMock', () => {
  beforeEach(() => resetGaAufgabenDemo())

  it('derives assignments from the task demo', () => {
    expect(assignmentsOf(helper('Marco')).some((entry) => entry.id === 'ga-demo-zelt-1')).toBe(true)
    expect(assignmentsOf(helper('Nora')).map((entry) => entry.id)).toContain('ga-demo-aufbau-1')
  })

  it('computes the helper status in the Anlass week', () => {
    expect(helperStatus(helper('Jana'), GA_ANLASS_PERIOD)).toBe('free')
    expect(helperStatus(helper('Nora'), GA_ANLASS_PERIOD)).toBe('partial')
    expect(helperStatus(helper('Marco'), GA_ANLASS_PERIOD)).toBe('partial')
    expect(helperStatus(helper('Kevin'), { from: anlassAt(4, 0), to: anlassAt(4, 23, 59) })).toBe('unavailable')
    const day = { from: anlassAt(4, 8), to: anlassAt(4, 12) }
    expect(helperStatus(helper('Marco'), day)).toBe('busy')
    expect(statusCounts(GA_ANLASS_PERIOD).unavailable).toBe(0)
  })

  it('shows free windows without assigned times', () => {
    const windows = freeWindows(helper('Marco'), { from: anlassAt(4, 0), to: anlassAt(4, 23, 59) })
    expect(windows.map((entry) => `${entry.from.getHours()}-${entry.to.getHours()}`)).toEqual(['7-8', '12-18'])
  })

  it('filters by skill, license, org and availability', () => {
    const filters = { ...emptyHelperFilters(), skill: 'Holzbau' }
    expect(GA_HELPERS.filter((entry) => matchesHelper(entry, filters, GA_ANLASS_PERIOD)).map((entry) => entry.name)).toEqual(['Peter', 'Marco', 'Nora', 'Tim', 'Jana', 'Ole'])
    expect(GA_HELPERS.filter((entry) => matchesHelper(entry, { ...emptyHelperFilters(), license: 'C' }, GA_ANLASS_PERIOD)).map((entry) => entry.name)).toEqual(['Luca'])
    expect(matchesHelper(helper('Peter'), { ...emptyHelperFilters(), org: 'Cevi Basel' }, GA_ANLASS_PERIOD)).toBe(false)
    expect(skillMatrix().find((row) => row.skill === 'Küche')!.helpers).toHaveLength(3)
  })

  it('matches people for the Holzbau need professionally and in time', () => {
    const matches = matchNeed(need())
    expect(matches.filter((entry) => entry.kind === 'fit').map((entry) => entry.helper.name)).toEqual(['Peter', 'Nora', 'Tim', 'Jana'])
    expect(matches.find((entry) => entry.helper.name === 'Marco')?.kind).toBe('busy')
    expect(matches.find((entry) => entry.helper.name === 'Ole')?.kind).toBe('unavailable')
    expect(matches.some((entry) => entry.helper.name === 'Felix')).toBe(false)
  })

  it('assigns a helper through the task logic so it shows on both sides', () => {
    const task = need()
    expect(staffing(task)).toEqual({ assigned: 0, need: 4 })
    expect(assignHelper(task, helper('Peter'))).toBe(true)
    expect(assignHelper(task, helper('Peter'))).toBe(false)
    expect(useGaAufgabenMock().tasks.value.find((row) => row.id === task.id)!.people).toContain('Peter')
    expect(assignmentsOf(helper('Peter')).map((entry) => entry.id)).toContain(task.id)
    expect(matchNeed(task)[0]!.kind).toBe('assigned')
    expect(staffing(task).assigned).toBe(1)
    unassignHelper(task, helper('Peter'))
    expect(staffing(task).assigned).toBe(0)
  })
})
