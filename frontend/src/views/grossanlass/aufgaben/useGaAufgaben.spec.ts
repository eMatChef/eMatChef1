import { describe, expect, it } from 'vitest'
import type { GaBauprojektTask } from '@/api/grossanlassBauprojekt'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { bucketOf, isOverdue, statusCounts, taskProgress } from './gaAufgabenMock'
import { mapApiTask } from './useGaAufgaben'

const group = (id: string, name: string, parent_id: string | null, extra: Partial<GrossanlassGroup> = {}) =>
  ({ id, name, parent_id, members: [], leaders: [], ...extra }) as unknown as GrossanlassGroup

const groups = [
  group('r', 'Bau', null, { members: [{ user_id: 'u1', name: 'Lea' }] as never }),
  group('b', 'Bühne', 'r'),
  group('p', 'Bar West', 'b', { window_start: '2027-06-10', window_end: '2027-06-12' }),
]

const task = (extra: Partial<GaBauprojektTask> = {}): GaBauprojektTask => ({
  id: 't1',
  group_id: 'p',
  title: 'Tresen montieren',
  sort_order: 0,
  status: 'open',
  created_at: '2027-06-01T10:00:00+00:00',
  ...extra,
})

describe('mapApiTask', () => {
  it('übernimmt die Backend-Freigabe und gilt ohne Feld als nicht freigegeben', () => {
    expect(mapApiTask(task({ released: true }), groups).released).toBe(true)
    expect(mapApiTask(task({ released: false }), groups).released).toBe(false)
    expect(mapApiTask(task(), groups).released).toBe(false)
  })

  it('leitet Ressort und Bereich aus dem Gruppenbaum ab', () => {
    const row = mapApiTask(task(), groups)
    expect(row.origin).toEqual(['Bau', 'Bühne', 'Bar West'])
    expect(row.ressort).toBe('Bau')
    expect(row.bereich).toBe('Bühne')
  })

  it('nutzt Termin und Dauer, sonst das Projektfenster', () => {
    const timed = mapApiTask(task({ starts_at: '2027-06-10T09:00:00', duration_minutes: 90 }), groups)
    expect(timed.endsAt.getTime() - timed.startsAt.getTime()).toBe(90 * 60_000)
    const untimed = mapApiTask(task(), groups)
    expect(untimed.scheduled).toBe(false)
    expect(untimed.startsAt.getDate()).toBe(10)
    expect(untimed.endsAt.getDate()).toBe(12)
  })

  it('zeigt den Namen des Zuständigen aus dem Ressort statt der ID', () => {
    expect(mapApiTask(task({ assignee_user_id: 'u1' }), groups).people).toEqual(['Lea'])
  })

  it('übernimmt Checkliste, Blockierung und Rechte vom Server', () => {
    const row = mapApiTask(
      task({
        steps: [
          { id: 's1', label: 'Zuschnitt', sort_order: 0, done: true },
          { id: 's2', label: 'Verschrauben', sort_order: 1, done: false },
        ],
        blocked: { reason: 'Material fehlt', by_user_id: 'u1', at: '2027-06-10T09:00:00+00:00' },
        permissions: { edit: false, check: true, report: true, unblock: false, approve: false },
      }),
      groups,
    )
    expect(row.steps).toEqual([
      { id: 's1', label: 'Zuschnitt', done: true },
      { id: 's2', label: 'Verschrauben', done: false },
    ])
    expect(taskProgress(row)).toBe(50)
    expect(row.blocked?.reason).toBe('Material fehlt')
    expect(row.permissions?.unblock).toBe(false)
  })

  it('übernimmt Status «fertig gemeldet» und den Verlauf; Fertigmeldung gilt nicht als überfällig', () => {
    const row = mapApiTask(
      task({
        status: 'review',
        starts_at: '2027-06-10T09:00:00',
        events: [{ id: 'e1', type: 'reported', by_user_id: 'u1', by_name: 'Lea', note: null, at: '2027-06-10T10:00:00+00:00' }],
      }),
      groups,
    )
    expect(row.status).toBe('review')
    expect(row.events).toEqual([{ id: 'e1', type: 'reported', byName: 'Lea', note: null, at: '2027-06-10T10:00:00+00:00' }])
    const later = new Date(2030, 0, 1)
    expect(isOverdue(row, later)).toBe(false)
    expect(bucketOf(row, later)).toBe('review')
    expect(taskProgress(row)).toBe(100)
    expect(statusCounts([row], later).review).toBe(1)
  })

  it('übernimmt «wartet auf …» und Abhängigkeiten', () => {
    const row = mapApiTask(
      task({
        waiting: true,
        waiting_on: ['Fundament giessen'],
        dependencies: [
          { id: 'd1', kind: 'task', mode: 'sequential', successor_id: 't1', predecessor_visible: true, predecessor_id: 't0', predecessor_title: 'Fundament giessen', predecessor_complete: false, open: true, released: null, created_at: '2027-06-01T10:00:00+00:00' },
        ],
      }),
      groups,
    )
    expect(row.waiting).toBe(true)
    expect(row.waitingOn).toEqual(['Fundament giessen'])
    expect(row.dependencies?.[0]?.open).toBe(true)
    const plain = mapApiTask(task(), groups)
    expect(plain.waiting).toBe(false)
    expect(plain.dependencies).toEqual([])
  })

  it('übernimmt den Gesamtabnahme-Zustand des Bauauftrags', () => {
    expect(mapApiTask(task({ order_status: 'accepted' }), groups).orderStatus).toBe('accepted')
    expect(mapApiTask(task(), groups).orderStatus).toBe('open')
  })

  it('kennzeichnet Nachbesserungen mit dem Rückweisungsgrund', () => {
    const row = mapApiTask(task({ rework: { event_id: 'gz1', reason: 'Kante fehlt', at: '2027-06-10T10:00:00+00:00' } }), groups)
    expect(row.rework).toEqual({ eventId: 'gz1', reason: 'Kante fehlt', at: '2027-06-10T10:00:00+00:00' })
    expect(mapApiTask(task(), groups).rework).toBeNull()
  })

  it('kommt ohne Checkliste und Blockierung aus (Altbestand)', () => {
    const row = mapApiTask(task(), groups)
    expect(row.steps).toEqual([])
    expect(row.blocked).toBeNull()
    expect(row.permissions).toBeNull()
  })
})
