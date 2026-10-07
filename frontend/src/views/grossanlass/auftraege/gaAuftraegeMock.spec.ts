import { beforeEach, describe, expect, it } from 'vitest'
import { resetGaAufgabenDemo, useGaAufgabenMock } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { resetGaPackenDemo } from '@/views/grossanlass/packen/gaPackenMock'
import { resetGaDispoDemo } from '@/views/grossanlass/logistik/gaDispoMock'
import { needsOfOrder, resetGaRessourcenDemo } from '@/views/grossanlass/ressourcen/gaRessourcenMock'
import {
  buildProgress,
  counts,
  deadlineIssues,
  createOrder,
  emptyDraft,
  emptyOrderFilters,
  helperStaffing,
  linkedTasks,
  materialProgress,
  matchesOrder,
  orderById,
  resetGaAuftraegeDemo,
  setOrderStatus,
  transportStatus,
  useGaAuftraegeMock,
} from './gaAuftraegeMock'

const order = (id: string) => orderById(id)!

describe('gaAuftraegeMock', () => {
  beforeEach(() => {
    resetGaAuftraegeDemo()
    resetGaAufgabenDemo()
    resetGaPackenDemo()
    resetGaDispoDemo()
    resetGaRessourcenDemo()
  })

  it('distinguishes orders and build orders', () => {
    expect(counts()).toEqual({ all: 6, order: 3, build: 3 })
    const build = useGaAuftraegeMock().orders.value.filter((row) => matchesOrder(row, { ...emptyOrderFilters(), type: 'build' }))
    expect(build.every((row) => row.type === 'build' && !!row.build)).toBe(true)
    expect(useGaAuftraegeMock().orders.value.filter((row) => matchesOrder(row, { ...emptyOrderFilters(), type: 'order' })).every((row) => !row.build)).toBe(true)
  })

  it('derives links to tasks, helper pool, packing and dispatch', () => {
    expect(linkedTasks(order('ao-bar-west')).map((task) => task.id)).toEqual(['ga-demo-aufbau-1', 'ga-demo-holz-need', 'ga-demo-arbeit-1', 'ga-demo-bw-material', 'ga-demo-bw-holz', 'ga-demo-bw-kran', 'ga-demo-bw-dach', 'ga-demo-bw-elektro'])
    expect(buildProgress(order('ao-bar-west'))).toBeGreaterThan(0)
    expect(materialProgress(order('ao-bar-west'))).toBe(0)
    expect(materialProgress(order('ao-pagode'))).toBe(100)
    expect(materialProgress(order('ao-catering'))).toBeNull()
    expect(helperStaffing(order('ao-bar-west')).need).toBe(4)
    expect(transportStatus(order('ao-bar-west'))).toEqual({ open: 2, underway: 0, done: 0 })
  })

  it('creates a normal order and its tasks in the task demo', () => {
    const draft = { ...emptyDraft('order'), title: 'Aufräumen', ressort: 'Demo-Organisation', bereich: 'Helferwesen', taskTitles: ['Platz kehren', 'Müll sammeln'], helperNeed: [{ skill: 'Abbau', count: 2 }] }
    const created = createOrder(draft)
    expect(created.type).toBe('order')
    expect(created.status).toBe('draft')
    expect(created.links.taskIds).toHaveLength(2)
    const tasks = useGaAufgabenMock().tasks.value
    expect(tasks.find((task) => task.id === created.links.taskIds[0])!.helperNeed).toEqual({ skill: 'Abbau', count: 2 })
    expect(tasks.find((task) => task.id === created.links.taskIds[1])!.helperNeed).toBeUndefined()
  })

  it('creates a build order with the additional build data', () => {
    const draft = emptyDraft('build')
    draft.title = 'Bühne Süd'
    draft.build!.project = 'Bühne Süd · Stahlbau'
    draft.build!.teardownInfo = 'Stahl zurück an Firma'
    const created = createOrder(draft)
    expect(created.type).toBe('build')
    expect(created.build?.project).toBe('Bühne Süd · Stahlbau')
    expect(buildProgress(created)).toBe(0)
    expect(setOrderStatus(created.id, 'planned')).toBe(true)
    expect(setOrderStatus(created.id, 'planned')).toBe(false)
  })

  it('keeps times and resource needs of a new build order', () => {
    const draft = emptyDraft('build')
    draft.title = 'Bühne Süd'
    draft.deadline = new Date(draft.endsAt.getTime() - 3_600_000)
    draft.build!.readyBy = new Date(draft.build!.buildTo.getTime() - 3_600_000)
    draft.resources = [{ type: 'crane', label: 'Mobilkran 20 t', durationH: 2, earliest: draft.startsAt, latest: draft.endsAt, wish: null, flexible: true, qty: 1, note: '' }]
    const created = createOrder(draft)
    expect(needsOfOrder(created.id)).toHaveLength(1)
    expect(needsOfOrder(created.id)[0]!.status).toBe('need')
    expect(deadlineIssues(created)).toEqual(['afterDeadline', 'afterReadyBy'])
    expect(deadlineIssues(order('ao-bar-west'))).toEqual([])
  })
})
