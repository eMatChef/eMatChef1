import { describe, expect, it } from 'vitest'
import type { GaLiveData } from '@/api/displayLayout'
import { filterOptions, liveFeed, liveLogistikBoard, liveMaterialBoard, liveOverall, liveProjectBoards, liveTasks, liveWarnings, matchesGroup } from './gaLiveAdapter'

const NO_FILTER = { ressort: '', bereich: '', project: '' }

const data: GaLiveData = {
  state: 'ready',
  generated_at: '2027-06-04T08:00:00',
  groups: [
    { id: 'g-bar', name: 'Bar West', kind: 'auftrag', ressort: 'Gastro', bereich: 'Bars', origin: ['Gastro', 'Bars', 'Bar West'], window_end: '2027-06-05T18:00:00' },
    { id: 'g-stage', name: 'Hauptbühne', kind: 'auftrag', ressort: 'Technik', bereich: 'Bühnen', origin: ['Technik', 'Bühnen', 'Hauptbühne'], window_end: null },
  ],
  material: {
    groups: [],
    items: [
      { id: 'i1', group_id: 'g-bar', label: 'Bierbänke', unit: 'Stk', required: 10, covered: 10, open: 0, received: 10, allocated: 10, packed: 4, ready_for_transport: 4, in_transit: 0, at_place: 0 },
      { id: 'i2', group_id: 'g-bar', label: 'Kühlschrank', unit: 'Stk', required: 2, covered: 0, open: 2, received: 0, allocated: 0, packed: 0, ready_for_transport: 0, in_transit: 0, at_place: 0 },
      { id: 'i3', group_id: 'g-stage', label: 'Traversen', unit: 'm', required: 20, covered: 20, open: 0, received: 20, allocated: 20, packed: 20, ready_for_transport: 0, in_transit: 0, at_place: 20 },
    ],
    packs: [{ id: 'p1', code: 'PK-1', status: 'trip_released', group_id: 'g-bar', title: 'Bierbänke', lines: [{ label: 'Bierbänke', qty: 4 }], target: 'Bar West' }],
    missing: [], incoming: [],
  },
  logistics: {
    counts: {}, drivers: 3, vehicles: 2, active_tours: 1,
    needs: [
      { id: 'n1', title: 'Bänke', from: 'Lager', to: 'Bar West', target: 'Bar West', cargo: '4× Bierbänke', starts_at: '2027-06-04T09:00:00', ends_at: '2027-06-04T10:00:00', status: 'open', ready: true, released: false, urgent: true, tour_id: null, kind: 'transport', group_id: 'g-bar' },
      { id: 'n2', title: 'Traversen', from: 'Lager', to: 'Bühne', target: 'Hauptbühne', cargo: '', starts_at: '2027-06-05T09:00:00', ends_at: '2027-06-05T10:00:00', status: 'underway', ready: true, released: true, urgent: false, tour_id: 't1', kind: 'transport', group_id: 'g-stage' },
    ],
    tours: [{ id: 't1', name: 'Tour 1', status: 'underway', confirmed: true, vehicle: 'Sprinter', trailer: '', driver: 'Marco', starts_at: null, ends_at: null, need_count: 1, has_conflict: false, group_ids: ['g-stage'], stops: [{ label: 'Lager', kind: 'load', planned_at: null, done: true }, { label: 'Bühne', kind: 'unload', planned_at: null, done: false }] }],
  },
  tasks: [
    { id: 't-a', title: 'Aufbau', group_id: 'g-bar', group_name: 'Bar West', ressort: 'Gastro', bereich: 'Bars', origin: ['Gastro', 'Bars', 'Bar West'], status: 'progress', starts_at: '2027-06-04T07:00:00', ends_at: '2027-06-04T12:00:00', steps_total: 4, steps_done: 1, blocked: false, blocked_reason: null, overdue: false },
    { id: 't-b', title: 'Strom', group_id: 'g-stage', group_name: 'Hauptbühne', ressort: 'Technik', bereich: 'Bühnen', origin: ['Technik', 'Bühnen', 'Hauptbühne'], status: 'open', starts_at: '2027-06-05T07:00:00', ends_at: '2027-06-05T12:00:00', steps_total: 0, steps_done: 0, blocked: true, blocked_reason: 'Kabel fehlt', overdue: false },
  ],
  projects: [
    { group_id: 'g-bar', name: 'Bar West', progress: 25, tasks: { total: 1, open: 0, progress: 1, review: 0, done: 0, blocked: 0, overdue: 0 }, material: { required: 12, packed: 4, open_items: 1 }, helpers: { need: 4, assigned: 2 }, current_task: 'Aufbau', next_task: null, deadline: '2027-06-05T18:00:00' },
    { group_id: 'g-stage', name: 'Hauptbühne', progress: 0, tasks: { total: 1, open: 1, progress: 0, review: 0, done: 0, blocked: 1, overdue: 0 }, material: null, helpers: { need: 0, assigned: 0 }, current_task: null, next_task: 'Strom', deadline: null },
  ],
  feed: [
    { id: 'e1', at: '2027-06-04T07:30:00', area: 'task', kind: 'task_reported', group_id: 'g-bar', subject: 'Aufbau', detail: 'Bar West', severity: 'info' },
    { id: 'e2', at: '2027-06-04T07:40:00', area: 'logistics', kind: 'dispatch_released', group_id: 'g-stage', subject: '', detail: '', severity: 'info' },
  ],
  warnings: [
    { level: 'critical', kind: 'task_blocked', group_id: 'g-stage', title: 'Strom', detail: 'Kabel fehlt' },
    { level: 'warning', kind: 'material_missing', group_id: 'g-bar', title: 'Bar West', detail: '1' },
  ],
}

describe('Live-Adapter', () => {
  it('berechnet den Materialstand mit den Statusregeln der Demo-Ansicht', () => {
    const board = liveMaterialBoard(data, NO_FILTER)
    const bar = board.projects.find((p) => p.project.id === 'g-bar')!
    expect(bar.needed).toBe(12)
    expect(bar.packed).toBe(4)
    expect(bar.missing).toBe(2) // Kühlschränke sind weder da noch gepackt
    expect(bar.status).toBe('partial')
    expect(bar.lines.find((l) => l.line.label === 'Kühlschrank')!.supply?.cause).toBe('notProcured')
    expect(bar.palettes[0]).toMatchObject({ code: 'PK-1', status: 'ready' })
    expect(board.projects.find((p) => p.project.id === 'g-stage')!.status).toBe('done')
    expect(board.kpis).toMatchObject({ packOpen: 1, missingItems: 1, waitingTransport: 1 })
  })

  it('filtert nach Ressort, Bereich und Bauauftrag über die echte GA-Hierarchie', () => {
    const only = (f: Partial<typeof NO_FILTER>) => liveMaterialBoard(data, { ...NO_FILTER, ...f }).projects.map((p) => p.project.id)
    expect(only({ ressort: 'Technik' })).toEqual(['g-stage'])
    expect(only({ bereich: 'Bars' })).toEqual(['g-bar'])
    expect(only({ project: 'bar west' })).toEqual(['g-bar'])
    expect(only({ ressort: 'Technik', bereich: 'Bars' })).toEqual([])
    expect(liveTasks(data, { ...NO_FILTER, ressort: 'Gastro' }).map((t) => t.id)).toEqual(['t-a'])
    expect(liveLogistikBoard(data, { ...NO_FILTER, bereich: 'Bühnen' }).kpis).toMatchObject({ open: 0, underway: 1 })
    expect(liveLogistikBoard(data, { ...NO_FILTER, ressort: 'Technik' }).tours).toHaveLength(1)
    expect(liveLogistikBoard(data, { ...NO_FILTER, ressort: 'Gastro' }).tours).toHaveLength(0)
    expect(liveFeed(data, { ...NO_FILTER, ressort: 'Technik' }, (e) => e.kind).map((e) => e.id)).toEqual(['e2'])
    expect(liveWarnings(data, { ...NO_FILTER, ressort: 'Gastro' }).map((w) => w.kind)).toEqual(['material_missing'])
    // Unbekannte Zuordnung wird bei aktivem Filter nicht fälschlich eingeschlossen.
    expect(matchesGroup({ groups: new Map(), filter: { ...NO_FILTER, ressort: 'Technik' } }, null)).toBe(false)
  })

  it('liefert Logistik, Projekte und Gesamtsicht in der Form der Anzeigekomponenten', () => {
    const logistics = liveLogistikBoard(data, NO_FILTER)
    expect(logistics.kpis).toMatchObject({ open: 1, underway: 1, urgent: 1, driversFree: 2, vehiclesFree: 1 })
    expect(logistics.urgent[0]).toMatchObject({ timingLabel: '09:00–10:00', priority: 'urgent' })
    expect(logistics.trips[0]).toMatchObject({ driver: 'Marco', vehicle: 'Sprinter', underway: true })
    expect(logistics.tours[0]!.nextStop).toMatchObject({ id: 't1-1' })
    const boards = liveProjectBoards(data, NO_FILTER)
    expect(boards.find((b) => b.order.id === 'g-stage')!.health).toBe('critical')
    expect(boards.find((b) => b.order.id === 'g-bar')!).toMatchObject({ health: 'attention', material: 33, helpers: { need: 4, assigned: 2 } })
    const all = liveOverall(data, NO_FILTER)
    expect(all.tasks).toMatchObject({ open: 1, progress: 1, done: 0 })
    expect(all.running.map((t) => t.id)).toEqual(['t-a'])
  })

  it('bietet Filterwerte aus der echten Hierarchie an', () => {
    expect(filterOptions(data.groups!, 'filter_ressort', { ressort: '', bereich: '' })).toEqual(['Gastro', 'Technik'])
    expect(filterOptions(data.groups!, 'filter_bereich', { ressort: 'Technik', bereich: '' })).toEqual(['Bühnen'])
    expect(filterOptions(data.groups!, 'filter_project', { ressort: '', bereich: 'Bars' })).toEqual(['Bar West'])
  })
})
