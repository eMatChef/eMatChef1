import { describe, expect, it } from 'vitest'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { buildOrderRows, emptyOrderFilters, materialPercent, matchesOrder, orderCounts, orderTypeOf, tasksPercent } from './gaAuftraegeModel'

const g = (over: Partial<GrossanlassGroup>): GrossanlassGroup => ({
  id: 'x', name: 'X', department_id: 'd', parent_id: null, sort_order: 0, level: 0, kind: 'ressort', node_type: 'ressort',
  member_count: 0, leader_count: 0, members: [], leaders: [], created_at: '', updated_at: '', ...over,
})

const cards = [
  { group_id: 'bar', material: { items: 3, covered_items: 3, at_place_items: 1 }, tasks: { total: 4, scheduled: 4, done: 1 }, staffing: { need: 4, assigned: 2 } },
]

const groups = [
  g({ id: 'r', name: 'Infrastruktur' }),
  g({ id: 'b', name: 'Bereich', parent_id: 'r', kind: 'bereich', node_type: 'unterressort' }),
  g({ id: 'bar', name: 'Bar West', parent_id: 'b', kind: 'teilbereich', node_type: 'bauprojekt', procurement_progress: 'build', build_status: 'build' }),
  g({ id: 'a1', name: 'Putzen', parent_id: 'b', kind: 'auftrag', node_type: 'auftrag', build_status: 'planned' }),
]

describe('gaAuftraegeModel', () => {
  it('maps leaf groups to order types', () => {
    expect(orderTypeOf(groups[2])).toBe('build')
    expect(orderTypeOf(groups[3])).toBe('order')
    expect(orderTypeOf(groups[1])).toBeNull()
  })

  it('lists only auftrag and bauprojekt leaves with path and progress', () => {
    const rows = buildOrderRows(groups, groups, cards)
    expect(rows.map((r) => r.id)).toEqual(['bar', 'a1'])
    expect(rows[0].path).toBe('Infrastruktur › Bereich')
    expect(materialPercent(rows[0].card)).toBe(33)
    expect(tasksPercent(rows[0].card)).toBe(25)
    // ohne Kennzahlen kein erfundener Fortschritt
    expect(rows[1].card).toBeNull()
    expect(materialPercent(rows[1].card)).toBeNull()
    expect(orderCounts(rows)).toEqual({ all: 2, order: 1, build: 1 })
  })

  it('filters by type, ressort, status and search', () => {
    const rows = buildOrderRows(groups, groups, cards)
    const f = emptyOrderFilters()
    expect(rows.filter((r) => matchesOrder(r, { ...f, type: 'order' }, groups)).map((r) => r.id)).toEqual(['a1'])
    expect(rows.filter((r) => matchesOrder(r, { ...f, ressort: 'Infrastruktur' }, groups))).toHaveLength(2)
    expect(rows.filter((r) => matchesOrder(r, { ...f, status: 'build' }, groups)).map((r) => r.id)).toEqual(['bar'])
    expect(rows.filter((r) => matchesOrder(r, { ...f, search: 'putz' }, groups)).map((r) => r.id)).toEqual(['a1'])
  })
})
