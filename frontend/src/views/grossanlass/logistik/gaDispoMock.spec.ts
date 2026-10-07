import { beforeEach, describe, expect, it } from 'vitest'
import {
  advanceTour,
  assignNeed,
  delayTour,
  dispoCounts,
  freeDrivers,
  freeVehicles,
  markNeedProblem,
  matchesView,
  needById,
  removeNeedFromTour,
  resetGaDispoDemo,
  sortNeeds,
  startTour,
  suggestionsFor,
  tourById,
  tourLegs,
  useGaDispoMock,
  vehicleDefect,
} from './gaDispoMock'

function need(id: string) {
  const found = needById(id)
  if (!found) throw new Error(id)
  return found
}

describe('gaDispoMock', () => {
  beforeEach(() => resetGaDispoDemo())

  it('computes the control-room counts', () => {
    expect(dispoCounts()).toEqual({
      open: 5,
      ready: 3,
      urgent: 3,
      planned: 2,
      underway: 1,
      driversFree: 2,
      vehiclesFree: 2,
    })
  })

  it('filters by view and sorts urgent first', () => {
    const list = useGaDispoMock().needs.value
    expect(list.filter((row) => matchesView(row, 'problems')).map((row) => row.id)).toEqual(['n-pk41', 'n-festzelt'])
    expect(list.filter((row) => matchesView(row, 'scope')).length).toBeGreaterThan(0)
    expect(sortNeeds(list)[0]?.priority).toBe('urgent')
  })

  it('shows the empty leg between Bar West and Häberli Holz', () => {
    const legs = tourLegs(tourById('t-1')!)
    expect(legs.map((leg) => `${leg.from}>${leg.to}:${leg.empty ? 'leer' : 'voll'}`)).toEqual([
      'Zentrallager>Bar West:voll',
      'Bar West>Häberli Holz:leer',
      'Häberli Holz>Zentrallager:voll',
    ])
  })

  it('suggests a running tour that passes the target', () => {
    const hits = suggestionsFor(need('n-werkzeug'))
    expect(hits.find((hit) => hit.tour.id === 't-1')?.reason).toBe('passesTarget')
  })

  it('creates a new tour and adds an order to an existing one', () => {
    const created = assignNeed('n-pk41', { mode: 'new', driverId: 'd-marco', vehicleId: 'v-pickup' })
    expect(created?.stops.map((item) => item.place)).toEqual(['Zentrallager', 'Info-Pagode Eingang'])
    expect(need('n-pk41').status).toBe('planned')
    expect(freeDrivers().some((driver) => driver.id === 'd-marco')).toBe(false)
    expect(freeVehicles().some((vehicle) => vehicle.id === 'v-pickup')).toBe(false)

    assignNeed('n-werkzeug', { mode: 'existing', tourId: 't-1' })
    expect(tourById('t-1')!.stops.length).toBe(6)
    expect(need('n-werkzeug').tourId).toBe('t-1')
  })

  it('inserts an order into a running tour after the current stop', () => {
    assignNeed('n-werkzeug', { mode: 'existing', tourId: 't-2' })
    const tour = tourById('t-2')!
    expect(tour.stops.map((item) => item.place)).toEqual(['Zentrallager', 'Festgelände Süd', 'Lager A', 'Bar West'])
    expect(need('n-werkzeug').status).toBe('underway')
  })

  it('walks a tour to done and completes its orders', () => {
    const tour = tourById('t-1')!
    startTour(tour)
    expect(need('n-holz').status).toBe('underway')
    for (let i = 0; i < tour.stops.length; i += 1) advanceTour(tour)
    expect(tour.status).toBe('done')
    expect(need('n-holz').status).toBe('done')
    expect(need('n-pk42').status).toBe('done')
  })

  it('supports live problems', () => {
    delayTour(tourById('t-2')!, 15)
    expect(tourById('t-2')!.delayMin).toBe(15)
    vehicleDefect(tourById('t-1')!)
    expect(useGaDispoMock().vehicles.value.find((row) => row.id === 'v-sprinter-2')?.defect).toBe(true)
    markNeedProblem('n-pk41', 'targetRefuses', 'Ziel gesperrt')
    expect(need('n-pk41').problem?.kind).toBe('targetRefuses')
    removeNeedFromTour('n-pk42')
    expect(need('n-pk42').status).toBe('open')
    expect(tourById('t-1')!.stops.some((item) => item.needId === 'n-pk42')).toBe(false)
  })
})
