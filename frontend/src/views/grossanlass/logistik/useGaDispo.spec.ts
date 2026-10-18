// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { GaDispatchNeed, GaDispatchOverview, GaDispatchTour } from '@/api/grossanlassLogistics'
import * as api from '@/api/grossanlassLogistics'
import { applyDispatch, dispatchActions, liveCounts, liveFreeDrivers, liveFreeVehicles, liveMatchesView, liveSortNeeds, mapDispatchNeed, mapDispatchTour, priorityOf, useGaDispo } from './useGaDispo'

vi.mock('@/api/grossanlassLogistics', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/grossanlassLogistics')>()),
  getGrossanlassDispatch: vi.fn(),
  createGrossanlassDispatchTour: vi.fn(),
  confirmGrossanlassDispatchTour: vi.fn(),
  releaseGrossanlassDispatchNeed: vi.fn(),
  convertGrossanlassDispatchVehicleNeed: vi.fn(),
  closeGrossanlassDispatchTour: vi.fn(),
  arriveGrossanlassPackManually: vi.fn(),
  requestGrossanlassTripChange: vi.fn(),
  decideGrossanlassChangeRequest: vi.fn(),
}))

const NOW = new Date(2027, 5, 3, 9, 0)

function row(over: Partial<GaDispatchNeed> = {}): GaDispatchNeed {
  return {
    id: 'e1',
    einsatz_id: 'e1',
    vehicle_need_id: null,
    source: 'packen',
    title: 'Festzelt',
    from: null,
    to: 'Festgelände Süd',
    starts_at: '2027-06-03T14:00:00',
    ends_at: '2027-06-03T16:00:00',
    ready: true,
    ready_note: null,
    cargo: '1× Festzelt',
    target: 'Bühne',
    requirements: [],
    scope: 'intern',
    status: 'open',
    released: false,
    einsatz_status: 'planned',
    chauffeur_user_id: null,
    vehicle_id: null,
    pack_ids: [],
    from_place_id: null,
    to_place_id: null,
    tour_id: null,
    tour_status: null,
    pack_status: 'staging',
    ...over,
  }
}

function overview(needs: GaDispatchNeed[]): GaDispatchOverview {
  return {
    now: '2027-06-03T09:00:00',
    can_manage: true,
    needs,
    tours: [],
    suggestions: [],
    change_requests: [],
    drivers: [{ id: 'u1', name: 'Anna' }, { id: 'u2', name: 'Ben' }],
    vehicles: [{ id: 'v1', name: 'Transporter', kind: '', trailer_ok: false, defect: false }, { id: 'v2', name: 'Kipper', kind: '', trailer_ok: false, defect: false }],
  }
}

function tourFixture(): GaDispatchTour {
  return {
    id: 't1', name: 'Tour 1', status: 'confirmed', confirmed: true, vehicle_id: 'v1', chauffeur_user_id: 'u1', trailer: null, confirmed_at: null,
    need_ids: ['a', 'b'], outcomes: [], closed_at: null, stops: [], starts_at: '2027-06-03T14:00:00', ends_at: '2027-06-03T16:00:00', conflicts: [], events: [],
  }
}

describe('useGaDispo', () => {
  it('leitet Priorität aus Status und Abstand zur Geschäftszeit ab', () => {
    expect(priorityOf('open', new Date(2027, 5, 3, 14), NOW)).toBe('urgent')
    expect(priorityOf('planned', new Date(2027, 5, 3, 14), NOW)).toBe('normal')
    expect(priorityOf('open', new Date(2027, 5, 20, 14), NOW)).toBe('low')
    expect(priorityOf('done', new Date(2027, 5, 3, 14), NOW)).toBe('low')
  })

  it('mappt Zeitfenster, Tagesversatz und Lager als Herkunft', () => {
    const need = mapDispatchNeed(row({ starts_at: '2027-06-04T08:30:00', ends_at: '2027-06-04T10:00:00' }), NOW)
    expect(need.timingLabel).toBe('08:30–10:00')
    expect(need.dayOffset).toBe(1)
    expect(need.hour).toBe(8.5)
    expect(need.to).toBe('Festgelände Süd')
    expect(need.from).toBeTruthy()
  })

  it('zählt Status und belegt Chauffeure und Fahrzeuge nur bei disponierten oder laufenden Fahrten', () => {
    applyDispatch(overview([
      row({ id: 'a' }),
      row({ id: 'b', status: 'planned', chauffeur_user_id: 'u1', vehicle_id: 'v1' }),
      row({ id: 'c', status: 'underway', chauffeur_user_id: 'u2' }),
      row({ id: 'd', status: 'done', chauffeur_user_id: 'u2', vehicle_id: 'v2' }),
    ]))
    const counts = liveCounts()
    expect(counts).toMatchObject({ open: 1, ready: 1, planned: 1, underway: 1, driversFree: 0, vehiclesFree: 1 })
    expect(liveFreeDrivers()).toEqual([])
    expect(liveFreeVehicles().map((vehicle) => vehicle.id)).toEqual(['v2'])
    expect(useGaDispo().tours.value).toEqual([])
    expect(useGaDispo().canManage.value).toBe(true)
  })

  it('filtert Ansichten und sortiert dringend zuerst', () => {
    applyDispatch(overview([
      row({ id: 'late', starts_at: '2027-06-10T08:00:00', ends_at: '2027-06-10T09:00:00' }),
      row({ id: 'soon' }),
      row({ id: 'ext', scope: 'extern', status: 'planned' }),
    ]))
    const list = useGaDispo().needs.value
    expect(liveSortNeeds(list).map((need) => need.id)).toEqual(['soon', 'ext', 'late'])
    expect(list.filter((need) => liveMatchesView(need, 'scope')).map((need) => need.id)).toEqual(['ext'])
    expect(list.filter((need) => liveMatchesView(need, 'open')).map((need) => need.id)).toEqual(['late', 'soon'])
  })

  describe('Touren', () => {
    function tour(over: Partial<GaDispatchTour> = {}): GaDispatchTour {
      return {
        id: 't1',
        name: 'Tour 1',
        status: 'draft',
        confirmed: false,
        vehicle_id: 'v1',
        chauffeur_user_id: 'u1',
        trailer: null,
        confirmed_at: null,
        need_ids: ['b'],
        outcomes: [{ need_id: 'b', outcome: null }],
        closed_at: null,
        stops: [
          { id: 's1', kind: 'load', place_id: null, label: 'Lager', planned_at: '2027-06-03T14:00:00', need_id: 'b' },
          { id: 's2', kind: 'unload', place_id: 'p1', label: 'Süd', planned_at: '2027-06-03T16:00:00', need_id: 'b' },
        ],
        starts_at: '2027-06-03T14:00:00',
        ends_at: '2027-06-03T16:00:00',
        conflicts: [],
        events: [],
        ...over,
      }
    }

    beforeEach(() => vi.clearAllMocks())

    it('mappt Tourstatus, Zeitfenster und sperrt laufende Touren', () => {
      const draft = mapDispatchTour(tour())
      expect(draft.locked).toBe(false)
      expect(draft.startsAt?.getHours()).toBe(14)
      expect(draft.stops[1]).toMatchObject({ kind: 'unload', placeId: 'p1', needId: 'b' })
      expect(mapDispatchTour(tour({ status: 'underway', confirmed: true })).locked).toBe(true)
      expect(mapDispatchTour(tour({ status: 'done', confirmed: true })).locked).toBe(true)
    })

    it('führt Bedarfe einer Tour als disponiert, bindet Chauffeur/Fahrzeug und meldet Konflikte als Problem', () => {
      applyDispatch({
        ...overview([row({ id: 'a' }), row({ id: 'b', status: 'planned', tour_id: 't1', tour_status: 'draft' })]),
        tours: [tour({ conflicts: [{ kind: 'vehicle', with: 'Tour 2', from: '2027-06-03T14:00:00', to: '2027-06-03T16:00:00' }] })],
        suggestions: [{ id: 's', reason: 'sameTarget', need_ids: ['a'], day: '2027-06-03', starts_at: '2027-06-03T14:00:00', ends_at: '2027-06-03T16:00:00' }],
        can_manage: false,
      })
      const state = useGaDispo()
      const inTour = state.needs.value.find((need) => need.id === 'b')!
      expect(inTour.tourId).toBe('t1')
      expect(inTour.tourStatus).toBe('draft')
      expect(liveMatchesView(inTour, 'open')).toBe(false)
      expect(liveMatchesView(inTour, 'planned')).toBe(true)
      expect(liveMatchesView(inTour, 'problems')).toBe(true)
      expect(liveMatchesView(state.needs.value.find((need) => need.id === 'a')!, 'problems')).toBe(false)
      expect(state.canManage.value).toBe(false)
      expect(state.suggestions.value).toHaveLength(1)
      expect(liveFreeDrivers().map((driver) => driver.id)).toEqual(['u2'])
      expect(liveFreeVehicles().map((vehicle) => vehicle.id)).toEqual(['v2'])
    })

    it('lädt nach einer Aktion neu und gibt Serverfehler zurück, ohne lokalen Zwischenstand', async () => {
      vi.mocked(api.getGrossanlassDispatch).mockResolvedValue(overview([row({ id: 'a' })]))
      vi.mocked(api.createGrossanlassDispatchTour).mockResolvedValue(tour())
      await expect(dispatchActions.createTour('d1', { need_ids: ['a'] })).resolves.toEqual({ ok: true })
      expect(api.createGrossanlassDispatchTour).toHaveBeenCalledWith('d1', { need_ids: ['a'] })
      expect(api.getGrossanlassDispatch).toHaveBeenCalledTimes(1)

      vi.mocked(api.confirmGrossanlassDispatchTour).mockRejectedValue({ response: { data: { error: 'Tour nicht bestätigbar: Fahrzeug fehlt' } } })
      await expect(dispatchActions.confirmTour('d1', 't1')).resolves.toEqual({ ok: false, error: 'Tour nicht bestätigbar: Fahrzeug fehlt' })
      expect(api.getGrossanlassDispatch).toHaveBeenCalledTimes(2)
    })
  })

  describe('Freigabe, Ablieferung, Abschluss, Änderungswünsche', () => {
    beforeEach(() => vi.clearAllMocks())

    it('übernimmt Packstand, Freigabe und Pack-IDs aus dem Backend', () => {
      applyDispatch(overview([row({ id: 'a', released: true, pack_status: 'in_transit', pack_ids: ['p1'], status: 'underway' })]))
      expect(useGaDispo().needs.value[0]).toMatchObject({ released: true, packStatus: 'in_transit', packIds: ['p1'], status: 'underway' })
    })

    it('zeigt abgeschlossene Touren gesperrt und trägt je Bedarf das Ergebnis', () => {
      const closed = mapDispatchTour({
        ...tourFixture(), status: 'closed', closed_at: '2027-06-03T18:00:00',
        outcomes: [{ need_id: 'a', outcome: 'delivered' }, { need_id: 'b', outcome: 'not_executed' }],
      })
      expect(closed.locked).toBe(true)
      expect(closed.outcomes).toEqual([{ needId: 'a', outcome: 'delivered' }, { needId: 'b', outcome: 'not_executed' }])
    })

    it('ruft die zentralen Aktionen auf und gibt Serverfehler unverändert weiter', async () => {
      vi.mocked(api.getGrossanlassDispatch).mockResolvedValue(overview([row({ id: 'a' })]))
      vi.mocked(api.releaseGrossanlassDispatchNeed).mockResolvedValue(row({ id: 'a', released: true }))
      await expect(dispatchActions.releaseNeed('d1', 'a')).resolves.toEqual({ ok: true })
      expect(api.releaseGrossanlassDispatchNeed).toHaveBeenCalledWith('d1', 'a')

      vi.mocked(api.convertGrossanlassDispatchVehicleNeed).mockResolvedValue(row({ id: 'e1', einsatz_id: 'e1', converted_from_vehicle_need_id: 'v1' }))
      await expect(dispatchActions.convertVehicleNeed('d1', 'vn:v1')).resolves.toEqual({ ok: true })
      expect(api.convertGrossanlassDispatchVehicleNeed).toHaveBeenCalledWith('d1', 'vn:v1')
      vi.mocked(api.convertGrossanlassDispatchVehicleNeed).mockRejectedValue({ response: { data: { error: 'Der Fahrzeugwunsch wurde bereits in einen Fahrauftrag überführt' } } })
      await expect(dispatchActions.convertVehicleNeed('d1', 'vn:v1')).resolves.toEqual({ ok: false, error: 'Der Fahrzeugwunsch wurde bereits in einen Fahrauftrag überführt' })
      vi.mocked(api.arriveGrossanlassPackManually).mockRejectedValue({ response: { data: { error: 'Begründung für die Ablieferung ohne QR fehlt' } } })
      await expect(dispatchActions.arriveManually('d1', 'p1', 'kurz')).resolves.toEqual({ ok: false, error: 'Begründung für die Ablieferung ohne QR fehlt' })

      vi.mocked(api.closeGrossanlassDispatchTour).mockResolvedValue(tourFixture())
      await dispatchActions.closeTour('d1', 't1', 'Fahrzeug fiel aus')
      expect(api.closeGrossanlassDispatchTour).toHaveBeenCalledWith('d1', 't1', 'Fahrzeug fiel aus')

      vi.mocked(api.requestGrossanlassTripChange).mockResolvedValue({} as never)
      await dispatchActions.requestChange('d1', 'a', { note: 'früher' })
      expect(api.requestGrossanlassTripChange).toHaveBeenCalledWith('d1', 'a', { note: 'früher' })

      vi.mocked(api.decideGrossanlassChangeRequest).mockResolvedValue({} as never)
      await dispatchActions.decideChange('d1', 'r1', { decision: 'reject', note: 'Ziel gesperrt' })
      expect(api.decideGrossanlassChangeRequest).toHaveBeenCalledWith('d1', 'r1', { decision: 'reject', note: 'Ziel gesperrt' })
    })
  })
})
