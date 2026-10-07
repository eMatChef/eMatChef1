import { beforeEach, describe, expect, it } from 'vitest'
import {
  GA_DEMO_ME,
  GA_FAHRAUFTRAG_STEPS,
  advanceRoute,
  blockedBy,
  bucketOf,
  completeTask,
  emptyAufgabeFilters,
  isOverdue,
  matchesFilters,
  myTodayTasks,
  resetGaAufgabenDemo,
  startTask,
  statusCounts,
  taskProgress,
  toggleStep,
  useGaAufgabenMock,
  type GaAufgabe,
} from './gaAufgabenMock'

function find(id: string): GaAufgabe {
  const task = useGaAufgabenMock().tasks.value.find((row) => row.id === id)
  if (!task) throw new Error(`missing ${id}`)
  return task
}

describe('gaAufgabenMock', () => {
  beforeEach(() => resetGaAufgabenDemo())

  it('shows the Tresen task at 70 percent', () => {
    expect(taskProgress(find('ga-demo-arbeit-1'))).toBe(70)
  })

  it('counts statuses and overdue tasks', () => {
    const counts = statusCounts(useGaAufgabenMock().tasks.value)
    expect(counts.done).toBeGreaterThanOrEqual(1)
    expect(counts.overdue).toBeGreaterThanOrEqual(1)
    expect(isOverdue(find('ga-demo-arbeit-2'))).toBe(true)
  })

  it('filters by kind, person and status', () => {
    const filters = { ...emptyAufgabeFilters(), kind: 'logistik' as const, person: 'Peter' }
    const hits = useGaAufgabenMock().tasks.value.filter((task) => matchesFilters(task, filters))
    expect(hits.map((task) => task.id)).toEqual(['ga-demo-logistik-1'])
    expect(matchesFilters(find('ga-demo-material-2'), { ...emptyAufgabeFilters(), status: 'done' })).toBe(true)
  })

  it('limits the helper view to own work and driving orders of today', () => {
    const mine = myTodayTasks(useGaAufgabenMock().tasks.value)
    expect(mine.length).toBeGreaterThan(0)
    expect(mine.every((task) => task.people.includes(GA_DEMO_ME))).toBe(true)
    expect(mine.every((task) => task.kind === 'arbeit' || task.kind === 'logistik')).toBe(true)
  })

  it('moves a task from open to in progress to done', () => {
    const task = find('ga-demo-material-1')
    startTask(task)
    expect(task.status).toBe('progress')
    toggleStep(task, 0)
    completeTask(task)
    expect(task.status).toBe('done')
    expect(taskProgress(task)).toBe(100)
  })

  it('walks a driving order through all steps', () => {
    const task = find('ga-demo-logistik-1')
    expect(task.routeStepIndex).toBe(-1)
    for (let i = 0; i < GA_FAHRAUFTRAG_STEPS.length; i += 1) advanceRoute(task)
    expect(task.routeStepIndex).toBe(GA_FAHRAUFTRAG_STEPS.length - 1)
    expect(task.status).toBe('progress')
    advanceRoute(task)
    expect(task.status).toBe('done')
  })

  it('buckets by due date', () => {
    expect(bucketOf(find('ga-demo-arbeit-2'))).toBe('overdue')
    expect(bucketOf(find('ga-demo-material-2'))).toBe('done')
    expect(bucketOf(find('ga-demo-arbeit-3'))).toBe('tomorrow')
  })

  it('shows tasks that wait for others in a dependency chain', () => {
    expect(blockedBy(find('ga-demo-bw-material'))).toHaveLength(0)
    expect(blockedBy(find('ga-demo-bw-holz'))).toHaveLength(0)
    expect(blockedBy(find('ga-demo-bw-kran')).map((task) => task.id)).toEqual(['ga-demo-bw-holz'])
    completeTask(find('ga-demo-bw-holz'))
    expect(blockedBy(find('ga-demo-bw-kran'))).toHaveLength(0)
    expect(blockedBy(find('ga-demo-bw-dach')).map((task) => task.id)).toEqual(['ga-demo-bw-kran'])
  })
})
