import { describe, expect, it } from 'vitest'
import type { GaUebersichtEinsatz } from '@/api/grossanlassUebersicht'
import { isTripReleased, isTripRow, mapTripTask, tripTaskStatus } from './gaFahrauftragTasks'

const row = (over: Partial<GaUebersichtEinsatz> = {}): GaUebersichtEinsatz => ({
  id: 'e1', kind: 'einsatz', object_id: 'c1', object_family: 'vehicle', object_name: 'Lieferwagen', einsatz_kind: 'unique', qty: 1, stock: 1,
  from: '2027-06-01T08:00:00+02:00', to: '2027-06-01T10:00:00+02:00', ressort: 'Bau', group_id: null,
  status: 'planned', who: 'Bühne', place: 'lager', packed: false, wish_line_id: null, chauffeur_user_id: 'u1',
  issued_to_user_id: null, delivery: 'trip', trip_released: false, trip_released_at: null, destination_place_id: null,
  bar_role: 'einsatz', ...over,
})

describe('gaFahrauftragTasks', () => {
  it('zeigt Fahraufträge nur bei Backend-Freigabe', () => {
    expect(isTripReleased(row())).toBe(false)
    expect(isTripReleased(row({ status: 'pending_approval', trip_released: true }))).toBe(false)
    expect(isTripReleased(row({ status: 'issued' }))).toBe(true)
    expect(isTripReleased(row({ trip_released: true }))).toBe(true)
    // das Backend-Feld hat Vorrang vor der Ableitung aus dem Status
    expect(isTripReleased(row({ status: 'issued', released: false }))).toBe(false)
    expect(isTripReleased(row({ released: true }))).toBe(true)
  })

  it('nimmt nur offene Fahrten, keine Abholungen oder Retouren', () => {
    expect(isTripRow(row())).toBe(true)
    expect(isTripRow(row({ delivery: 'pickup' }))).toBe(false)
    expect(isTripRow(row({ status: 'returned' }))).toBe(false)
  })

  it('übernimmt Status, Termin, Fahrzeug und Fahrer aus dem Einsatz', () => {
    const task = mapTripTask(row({ status: 'issued', destination_place_name: 'Festplatz' }), [], () => 'Mia')
    expect(task.kind).toBe('logistik')
    expect(task.id).toBe('trip:e1')
    expect(task.status).toBe('progress')
    expect(task.fahrauftrag).toMatchObject({ einsatzId: 'e1', status: 'issued', vehicle: 'Lieferwagen', destination: 'Festplatz' })
    expect(task.people).toEqual(['Mia'])
    expect(task.startsAt.getTime()).toBe(new Date(2027, 5, 1, 8, 0).getTime())
    expect(task.origin).toEqual(['Bau'])
  })

  it('gibt nur Fahrzeug-Einsätze als Fahrzeug aus', () => {
    expect(mapTripTask(row({ object_family: 'material', object_name: 'Kantholz' }), [], () => null).fahrauftrag?.vehicle).toBe('')
  })

  it('bildet API-Status auf Kachelstatus ab', () => {
    expect(tripTaskStatus('planned')).toBe('open')
    expect(tripTaskStatus('pending_approval')).toBe('open')
    expect(tripTaskStatus('issued')).toBe('progress')
  })
})
