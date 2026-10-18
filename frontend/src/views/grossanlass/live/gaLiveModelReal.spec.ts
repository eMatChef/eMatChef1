// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest'
import type { GaDispatchNeed, GaDispatchOverview, GaDispatchTour } from '@/api/grossanlassLogistics'
import * as api from '@/api/grossanlassLogistics'
import { loadDispatch } from '@/views/grossanlass/logistik/useGaDispo'
import { logistikBoard } from './gaLiveModel'

vi.mock('@/api/grossanlassLogistics', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/grossanlassLogistics')>()),
  getGrossanlassDispatch: vi.fn(),
}))

const none = { ressort: '', bereich: '', project: '' }

function need(over: Partial<GaDispatchNeed>): GaDispatchNeed {
  return {
    id: 'e1', einsatz_id: 'e1', vehicle_need_id: null, source: 'packen', title: 'Zelt', from: null, to: 'Süd',
    starts_at: '2027-06-03T10:00:00', ends_at: '2027-06-03T11:00:00', ready: true, ready_note: null, cargo: '1× Zelt', target: 'Bühne',
    requirements: [], scope: 'intern', status: 'open', released: false, einsatz_status: 'planned', chauffeur_user_id: null, vehicle_id: null,
    pack_ids: [], from_place_id: null, to_place_id: null, tour_id: null, tour_status: null, pack_status: 'staging', ...over,
  }
}

describe('Logistik-Leitstand mit echten Transportdaten', () => {
  it('zeigt Demo-Fahrten nur, solange keine echten Daten geladen sind, und danach ausschliesslich echte', async () => {
    expect(logistikBoard(none).trips.length).toBeGreaterThan(0) // Demo-State ohne Backend

    const tour: GaDispatchTour = {
      id: 't1', name: 'Echte Tour', status: 'confirmed', confirmed: true, vehicle_id: 'v1', chauffeur_user_id: 'u1', trailer: null, confirmed_at: null,
      need_ids: ['e2'], outcomes: [], closed_at: null, conflicts: [], events: [], starts_at: '2027-06-03T11:00:00', ends_at: '2027-06-03T12:00:00',
      stops: [
        { id: 's1', kind: 'load', place_id: null, label: 'Lager', planned_at: '2027-06-03T11:00:00', need_id: 'e2' },
        { id: 's2', kind: 'unload', place_id: 'p', label: 'Süd', planned_at: '2027-06-03T12:00:00', need_id: 'e2' },
      ],
    }
    const data: GaDispatchOverview = {
      now: '2027-06-03T09:00:00', can_manage: true, suggestions: [], change_requests: [], tours: [tour],
      needs: [need({ id: 'e1' }), need({ id: 'e2', status: 'underway', chauffeur_user_id: 'u1', vehicle_id: 'v1', tour_id: 't1', tour_status: 'confirmed', pack_status: 'in_transit', released: true })],
      drivers: [{ id: 'u1', name: 'Anna' }], vehicles: [{ id: 'v1', name: 'Transporter', kind: '', trailer_ok: false, defect: false }],
    }
    vi.mocked(api.getGrossanlassDispatch).mockResolvedValue(data)
    await loadDispatch('d1')

    const board = logistikBoard(none)
    expect(board.trips.map((trip) => trip.id)).toEqual(['e2'])
    expect(board.trips[0]).toMatchObject({ driver: 'Anna', vehicle: 'Transporter', underway: true })
    expect(board.tours.map((entry) => entry.tour.name)).toEqual(['Echte Tour'])
    expect(board.tours[0]).toMatchObject({ driver: 'Anna', vehicle: 'Transporter' })
    expect(board.kpis).toMatchObject({ open: 1, underway: 1 })
    expect(board.openNeeds.map((row) => row.id)).toEqual(['e1'])
  })
})
