import { beforeEach, describe, expect, it } from 'vitest'
import { advanceRoute, completeTask, resetGaAufgabenDemo, startTask, taskById } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { resetGaAuftraegeDemo } from '@/views/grossanlass/auftraege/gaAuftraegeMock'
import { advanceTour, resetGaDispoDemo, tourById } from '@/views/grossanlass/logistik/gaDispoMock'
import { createPalette, markPaletteDelivered, markPaletteReady, resetGaPackenDemo, sendFahrauftrag, useGaPackenMock } from '@/views/grossanlass/packen/gaPackenMock'
import { resetGaRessourcenDemo } from '@/views/grossanlass/ressourcen/gaRessourcenMock'
import { clearTripStarted, etaProgress, logEvent, markTripStarted, recentEvents, resetGaLiveDemo, setTracking, trackingOf, useGaLive } from './gaLiveEvents'
import { logistikBoard, materialBoard, orderPlaceName, overall, projectBoard, projectBoards, trips } from './gaLiveModel'
import { orderById } from '@/views/grossanlass/auftraege/gaAuftraegeMock'

const none = { ressort: '', bereich: '', project: '' }

describe('gaLive', () => {
  beforeEach(() => {
    resetGaLiveDemo()
    resetGaAufgabenDemo()
    resetGaPackenDemo()
    resetGaDispoDemo()
    resetGaAuftraegeDemo()
    resetGaRessourcenDemo()
  })

  it('logs actions of the existing mocks into the shared event log', () => {
    const before = useGaLive().events.value.length
    startTask(taskById('ga-demo-material-1')!)
    completeTask(taskById('ga-demo-material-1')!)
    const palette = createPalette('pp-crew-zelt', [{ lineId: 'pl-cz-boden', qty: 24 }])!
    markPaletteReady(palette)
    sendFahrauftrag(palette)
    const texts = recentEvents(10).map((event) => event.text)
    expect(useGaLive().events.value.length).toBe(before + 5)
    expect(texts.some((text) => text.includes('gestartet'))).toBe(true)
    expect(texts.some((text) => text.includes(`Pack ${palette.code} fertig`))).toBe(true)
    expect(texts.some((text) => text.includes('transportbereit'))).toBe(true)
    expect(recentEvents(1)[0]!.text).toContain('transportbereit')
    advanceTour(tourById('t-2')!)
    expect(recentEvents(1)[0]!.area).toBe('logistics')
  })

  it('changes the logistics board when the Fahrauftrag steps are clicked', () => {
    const task = taskById('ga-demo-logistik-1')!
    expect(trips(none).find((trip) => trip.task.id === task.id)!.underway).toBe(false)
    startTask(task)
    advanceRoute(task) // atSupplier
    advanceRoute(task) // loaded
    expect(trips(none).find((trip) => trip.task.id === task.id)!.stepKey).toBe('loaded')
    advanceRoute(task) // onTheWay
    const underway = trips(none).find((trip) => trip.task.id === task.id)!
    expect(underway.stepKey).toBe('onTheWay')
    expect(underway.underway).toBe(true)
    expect(underway.startedAt).not.toBeNull()
    expect(logistikBoard(none).kpis.underway).toBeGreaterThanOrEqual(1)
    advanceRoute(task) // arrived
    advanceRoute(task) // unloaded
    expect(trips(none).find((trip) => trip.task.id === task.id)!.underway).toBe(false)
    expect(recentEvents(1)[0]!.text).toContain('entladen')
    advanceRoute(task) // abschliessen
    expect(trips(none).find((trip) => trip.task.id === task.id)!.done).toBe(true)
    expect(recentEvents(1)[0]!.text).toContain('erledigt')
  })

  it('simulates ETA progress only as an estimate and keeps GPS as a future state', () => {
    const task = taskById('ga-demo-logistik-1')!
    expect(trackingOf(task.id)).toBe('eta')
    const start = new Date(Date.now() - 15 * 60_000)
    markTripStarted(task.id, start)
    const progress = etaProgress(task.id, 30)!
    expect(progress).toBeGreaterThan(0.45)
    expect(progress).toBeLessThan(0.55)
    clearTripStarted(task.id)
    expect(etaProgress(task.id, 30)).toBeNull()
    setTracking(task.id, 'gps')
    expect(trackingOf(task.id)).toBe('gps')
    expect(trackingOf('ga-demo-logistik-2')).toBe('none')
  })

  it('separates missing from available, packed-waiting and on-site material', () => {
    const board = materialBoard(none)
    const bar = board.projects.find((entry) => entry.project.id === 'pp-bar-west')!
    const holz = bar.lines.find((state) => state.line.id === 'pl-bw-holz')!
    expect(holz).toMatchObject({ missing: 0, availableNotPacked: 14, packedWaiting: 0, onSite: 0 })
    const platte = bar.lines.find((state) => state.line.id === 'pl-bw-platte')!
    expect(platte.missing).toBe(2)
    expect(platte.supply?.cause).toBe('pickupPlanned')
    const pagode = board.projects.find((entry) => entry.project.id === 'pp-info-pagode')!
    expect(pagode.lines.find((state) => state.line.id === 'pl-ip-pagode')).toMatchObject({ missing: 0, packedWaiting: 1, onSite: 0 })
    markPaletteDelivered(useGaPackenMock().palettes.value.find((row) => row.id === 'pal-41')!)
    const after = materialBoard(none).projects.find((entry) => entry.project.id === 'pp-info-pagode')!
    expect(after.lines.find((state) => state.line.id === 'pl-ip-pagode')).toMatchObject({ packedWaiting: 0, onSite: 1 })
    expect(board.kpis.waitingTransport).toBe(1)
    expect(board.kpis.missingItems).toBeGreaterThanOrEqual(6)
  })

  it('differentiates the causes of missing material', () => {
    const causes = materialBoard(none).missing.map((entry) => entry.state.supply?.cause)
    for (const cause of ['partial', 'pickupPlanned', 'late', 'ordered', 'notProcured', 'unknown']) expect(causes).toContain(cause)
  })

  it('derives the project health for the map', () => {
    const bar = projectBoard(orderById('ao-bar-west')!)
    expect(bar.health).toBe('attention')
    expect(bar.currentTask?.status).toBe('progress')
    const pagode = projectBoard(orderById('ao-pagode')!)
    expect(pagode.health).toBe('critical')
    expect(pagode.blockers.some((blocker) => blocker.kind === 'problem')).toBe(true)
    expect(projectBoard(orderById('ao-crew-zelt')!).health).toBe('ok')
    expect(orderPlaceName(orderById('ao-bar-west')!, (name) => name === 'Bar West')).toBe('Bar West')
  })

  it('applies display filters and builds the overall view without own data', () => {
    expect(projectBoards({ ressort: 'Demo-Bauten', bereich: 'Zelte', project: '' }).map((board) => board.order.id)).toEqual(['ao-crew-zelt'])
    expect(projectBoards({ ressort: '', bereich: '', project: 'Bar' }).map((board) => board.order.id)).toEqual(['ao-bar-west'])
    const all = overall(none)
    expect(all.boards).toHaveLength(3)
    expect(all.next.length).toBeGreaterThan(0)
    expect(all.running.every((task) => task.status === 'progress')).toBe(true)
    logEvent({ area: 'problem', actor: 'Test', text: 'x' })
    expect(recentEvents(1)[0]!.text).toBe('x')
  })
})
