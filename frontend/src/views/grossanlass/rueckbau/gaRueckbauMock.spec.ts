import { beforeEach, describe, expect, it } from 'vitest'
import {
  advanceReturn,
  applyPlanned,
  canAdvanceReturn,
  decide,
  deviatesFromPlan,
  disposeItems,
  effectiveFate,
  fateCounts,
  firmItems,
  itemStatus,
  lastResaleResult,
  markDisposed,
  markReturnLabel,
  matchesFateFilter,
  projectProgress,
  resetGaRueckbauDemo,
  returnFirms,
  returnGroup,
  scanReturnItem,
  sendReturnTransport,
  useGaRueckbauMock,
} from './gaRueckbauMock'
import { resetGaVerkaufDemo, useGaVerkaufMock } from '@/views/grossanlass/weiterverkauf/gaVerkaufMock'

const items = () => useGaRueckbauMock().items.value
const row = (id: string) => items().find((entry) => entry.id === id)!

describe('gaRueckbauMock', () => {
  beforeEach(() => resetGaRueckbauDemo())

  it('shows the Bar West example statuses', () => {
    expect(itemStatus(row('rb-1'))).toBe('done')
    expect(itemStatus(row('rb-2'))).toBe('done')
    expect(itemStatus(row('rb-3'))).toBe('open')
    expect(itemStatus(row('rb-6'))).toBe('warning')
    expect(effectiveFate(row('rb-6'))).toBe('workshop')
    expect(projectProgress(items(), 'Bar West').doneRows).toBe(2)
  })

  it('filters by planned or chosen fate', () => {
    expect(items().filter((entry) => matchesFateFilter(entry, 'return')).map((entry) => entry.id)).toEqual(['rb-4', 'rb-5', 'rb-9'])
    expect(fateCounts(items()).dispose).toBe(1)
    expect(fateCounts(items()).all).toBe(11)
  })

  it('applies the planned fate and detects deviations', () => {
    expect(applyPlanned('rb-6')).toBe(true)
    expect(row('rb-6').chosenFate).toBe('lager')
    expect(decide('rb-3', { fate: 'lager', qty: 40, detail: 'Lager A' })).toBe(true)
    expect(deviatesFromPlan(row('rb-3'))).toBe(true)
  })

  it('supports partial decisions with open remainder', () => {
    decide('rb-3', { fate: 'lager', qty: 10, detail: 'Zentrallager' })
    expect(row('rb-3').qtyDone).toBe(10)
    expect(itemStatus(row('rb-3'))).toBe('open')
  })

  it('walks a firm return from collecting to the transport need', () => {
    expect(returnFirms()).toEqual(['Eventtechnik AG', 'Zeltbau AG'])
    const firm = 'Eventtechnik AG'
    expect(canAdvanceReturn(firm)).toBe(false)
    expect(scanReturnItem(firm, 'gitter-füsse')?.id).toBe('rb-5')
    expect(scanReturnItem(firm, 'absperrgitter')?.collected).toBe(true)
    firmItems(firm).forEach((entry) => { entry.collected = true })
    expect(advanceReturn(firm)).toBe(true)
    expect(advanceReturn(firm)).toBe(true)
    expect(returnGroup(firm).packCode).toBe('RP-0001')
    expect(advanceReturn(firm)).toBe(true)
    expect(advanceReturn(firm)).toBe(false)
    markReturnLabel(firm)
    expect(advanceReturn(firm)).toBe(true)
    expect(sendReturnTransport(firm)).toBe(true)
    expect(firmItems(firm)).toHaveLength(0)
    expect(row('rb-4').transportRequested).toBe(true)
  })

  it('reconciles the planned resale with the confirmed condition per quantity', () => {
    resetGaVerkaufDemo()
    decide('rb-3', { fate: 'sale', qty: 40, priceChf: 1.5, pickup: 'Lager A', split: { good: 20, wear: 10, damaged: 5, notSellable: 3, workshop: 2 } })
    const offer = useGaVerkaufMock().offers.value.find((entry) => entry.rueckbauItemId === 'rb-3')!
    expect(offer.id).toBe('of-bretter')
    expect(offer.phase).toBe('afterUse')
    expect(offer.confirmed).toEqual({ good: 20, wear: 10, damaged: 5, notSellable: 3, workshop: 2 })
    expect(lastResaleResult()?.sellable).toBe(30)
    expect(lastResaleResult()?.planned).toBe(40)
    // 10 reserviert (Cevi Basel) < 30 verkaufbar: nur die Abweichung wird gemeldet
    expect(lastResaleResult()?.warnings).toEqual([{ kind: 'deviates', planned: 40, sellable: 30 }])
    expect(row('rb-3').qtyDone).toBe(40)
  })

  it('warns when reservations exceed the sellable quantity after teardown', () => {
    resetGaVerkaufDemo()
    decide('rb-3', { fate: 'sale', qty: 40, split: { good: 4, wear: 4, damaged: 20, notSellable: 12, workshop: 0 } })
    expect(lastResaleResult()?.warnings.map((entry) => entry.kind)).toEqual(['reservedExceeds', 'deviates'])
  })

  it('disposes goods', () => {
    expect(disposeItems().map((entry) => entry.id)).toEqual(['rb-7'])
    expect(markDisposed('rb-7')).toBe(true)
    expect(itemStatus(row('rb-7'))).toBe('done')
    expect(markDisposed('rb-7')).toBe(false)
  })
})
