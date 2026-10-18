import { describe, expect, it } from 'vitest'
import { findRecordLists, leafPaths, parseJson, valueAt } from './gaExternalJson'

const json = { meta: { total: 2 }, data: { bookings: [{ id: 1, person: { name: 'Anna' }, shifts: [{ start: 'x' }] }, { id: 2, person: { name: 'Ben', mail: 'b@x.ch' }, shifts: [] }] } }

describe('gaExternalJson', () => {
  it('findet Datensatzlisten', () => {
    expect(findRecordLists(json)).toEqual([{ path: 'data.bookings', count: 2 }])
    expect(findRecordLists([{ a: 1 }])).toEqual([{ path: '', count: 1 }])
    expect(findRecordLists({ a: [1, 2] })).toEqual([])
  })
  it('liest Werte per Punktpfad', () => {
    expect(valueAt(json, 'data.bookings.1.person.name')).toBe('Ben')
    expect(valueAt(json, 'data.nope')).toBeUndefined()
  })
  it('sammelt Felder über mehrere Datensätze mit Beispielwert', () => {
    const leaves = leafPaths(json.data.bookings)
    expect(leaves.map((l) => l.path)).toEqual(expect.arrayContaining(['id', 'person.name', 'person.mail', 'shifts.0.start']))
    expect(leaves.find((l) => l.path === 'person.name')?.sample).toBe('Anna')
  })
  it('meldet ungültiges JSON', () => {
    expect(parseJson('{').ok).toBe(false)
    expect(parseJson('[1]').ok).toBe(true)
  })
})
