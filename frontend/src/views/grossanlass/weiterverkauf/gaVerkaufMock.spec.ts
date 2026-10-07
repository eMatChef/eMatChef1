import { beforeEach, describe, expect, it } from 'vitest'
import {
  confirmResaleFromRueckbau,
  offerWarnings,
  sellableQty,
  addToCart,
  setAfterUse,
  stageReserved,
  purchaseById,
  completeOnboarding,
  followUpAfterHandover,
  cartTotal,
  completeHandover,
  declineRequest,
  loginAsDepartment,
  offerById,
  offerStats,
  prepareHandover,
  reserveRequest,
  resetGaVerkaufDemo,
  saveOffer,
  setOfferStatus,
  soldRevenue,
  submitCart,
  useGaVerkaufMock,
  visibleFor,
} from './gaVerkaufMock'

const offer = (id: string) => offerById(id)!
const request = (id: string) => useGaVerkaufMock().requests.value.find((row) => row.id === id)!

describe('gaVerkaufMock', () => {
  beforeEach(() => resetGaVerkaufDemo())

  it('derives available, reserved and sold per offer', () => {
    expect(offerStats(offer('of-1'))).toEqual({ reserved: 2, sold: 1, available: 3, open: 1 })
    expect(offerStats(offer('of-bretter'))).toEqual({ reserved: 10, sold: 0, available: 30, open: 0 })
    expect(soldRevenue()).toBe(35)
  })

  it('creates and edits offers and keeps the plan separate from use', () => {
    const created = saveOffer({ name: 'Stichsägen', description: '', qty: 2, priceChf: 0, condition: 'used', images: 0, availableFrom: '2026-11-09', pickup: 'Lager A', visibility: 'public', purchasedQty: 2, useQty: 2, expectedCondition: 'toCheck', earlyPublish: false })
    expect(created.status).toBe('draft')
    expect(created.phase).toBe('planned')
    expect(created.qty).toBe(created.purchasedQty)
    saveOffer({ ...created, priceChf: 25, qty: 2 })
    expect(offer(created.id).priceChf).toBe(25)
    expect(setOfferStatus(created.id, 'published')).toBe(true)
    expect(setOfferStatus(created.id, 'paused')).toBe(true)
  })

  it('may sell the whole purchased quantity (no purchased minus needed)', () => {
    const akku = offer('of-akku')
    expect(akku.purchasedQty).toBe(4)
    expect(akku.useQty).toBe(4)
    expect(akku.qty).toBe(4)
    expect(sellableQty(akku)).toBe(4)
  })

  it('shows pre-use offers publicly only with early publishing', () => {
    expect(visibleFor({ kind: 'department', department: 'Cevi Basel' }).open.map((row) => row.id)).toContain('of-akku')
    expect(visibleFor({ kind: 'guest' }).open.map((row) => row.id)).toContain('of-bretter')
    setOfferStatus('of-bretter', 'paused')
    offer('of-bretter').earlyPublish = false
    setOfferStatus('of-bretter', 'published')
    expect(visibleFor({ kind: 'guest' }).open.map((row) => row.id)).not.toContain('of-bretter')
  })

  it('reconciles the confirmed condition after teardown with the planned quantity and reservations', () => {
    const result = confirmResaleFromRueckbau({
      itemId: 'rb-11',
      name: 'Akkuschrauber (neu gekauft)',
      project: 'Bar West',
      split: { good: 1, wear: 1, damaged: 1, notSellable: 0, workshop: 1 },
      priceChf: 0,
      pickup: 'Zentrallager',
    })
    expect(result.offer.id).toBe('of-akku')
    expect(result.planned).toBe(4)
    expect(result.sellable).toBe(2)
    expect(result.offer.phase).toBe('afterUse')
    expect(sellableQty(result.offer)).toBe(2)
    // 3 reserviert (Jubla Aarau) > 2 verkaufbar
    expect(offerWarnings(result.offer)).toEqual([
      { kind: 'reservedExceeds', reserved: 3, sellable: 2 },
      { kind: 'deviates', planned: 4, sellable: 2 },
    ])
    expect(offerStats(result.offer).available).toBe(0)
  })

  it('creates a draft when teardown confirms resale without a planned offer', () => {
    const result = confirmResaleFromRueckbau({
      itemId: 'rb-new',
      name: 'Absperrband',
      project: 'Crew-Zelt Backstage',
      split: { good: 5, wear: 0, damaged: 0, notSellable: 1, workshop: 0 },
      priceChf: 3,
      pickup: 'Lager A',
    })
    expect(result.offer.status).toBe('draft')
    expect(result.offer.origin).toBe('Rückbau · Crew-Zelt Backstage')
    expect(result.planned).toBeNull()
    expect(result.sellable).toBe(5)
    expect(result.warnings).toEqual([])
  })

  it('shows department-only offers as locked for guests', () => {
    expect(visibleFor({ kind: 'guest' }).locked.map((row) => row.id)).toEqual(['of-akku', 'of-1'])
    expect(visibleFor({ kind: 'guest' }).open.map((row) => row.id)).toEqual(['of-bretter', 'of-2', 'of-4'])
    expect(visibleFor({ kind: 'department', department: 'Cevi Basel' }).locked).toHaveLength(0)
  })

  it('needs a department login for department-only offers', () => {
    expect(addToCart('of-1', 1)).toBe(false)
    loginAsDepartment('Cevi Basel')
    expect(addToCart('of-1', 2)).toBe(true)
    expect(addToCart('of-2', 1)).toBe(true)
    expect(cartTotal()).toBe(2 * 35 + 60)
  })

  it('turns a cart into new requests that do not reserve yet', () => {
    loginAsDepartment('Cevi Basel')
    addToCart('of-1', 1)
    const created = submitCart({ name: 'Cevi Basel', contact: 'x@y.ch', kind: 'department', organisation: 'Cevi Basel', handover: 'transport' })
    expect(created).toHaveLength(1)
    expect(created[0]!.status).toBe('new')
    expect(offerStats(offer('of-1')).available).toBe(3)
    expect(useGaVerkaufMock().cart.value).toHaveLength(0)
  })

  it('walks a request from new to sold including the transport mock', () => {
    expect(reserveRequest('rq-2')).toBe(true)
    expect(offerStats(offer('of-1')).available).toBe(0)
    expect(prepareHandover('rq-2')).toBe(true)
    expect(request('rq-2').transportSent).toBe(true)
    expect(request('rq-2').handoverCode).toBe('UE-0002')
    expect(completeHandover('rq-2')).toBe(true)
    expect(offerStats(offer('of-1')).sold).toBe(4)
  })

  it('does not reserve more than available and can decline', () => {
    expect(reserveRequest('rq-5')).toBe(true)
    expect(reserveRequest('rq-5')).toBe(false)
    expect(declineRequest('rq-3')).toBe(true)
    expect(declineRequest('rq-3')).toBe(false)
    const big = saveOffer({ name: 'Test', description: '', qty: 1, priceChf: 1, condition: 'good', images: 0, availableFrom: '2026-01-01', pickup: 'Lager A', visibility: 'public', purchasedQty: null, useQty: null, expectedCondition: 'new', earlyPublish: false })
    expect(big.status).toBe('draft')
  })

  it('supports requests without eMatChef and with onboarding', () => {
    addToCart('of-2', 1)
    const external = submitCart({ name: 'A. Muster', organisation: 'Turnverein', contact: 'a@b.ch', phone: '079', kind: 'external', handover: 'pickup', note: 'Bitte melden' })
    expect(external[0]!.requesterKind).toBe('external')
    expect(external[0]!.organisation).toBe('Turnverein')
    expect(followUpAfterHandover(external[0]!)).toBe('none')

    addToCart('of-4', 2)
    const onboarding = submitCart({ name: 'Leitung', contact: 'l@x.ch', kind: 'onboarding', handover: 'transport' })
    expect(onboarding[0]!.onboarding).toBe('invited')
    expect(onboarding[0]!.status).toBe('new')
    expect(completeOnboarding(onboarding.map((row) => row.id), 'Pfadi Neuhof')).toBe(1)
    expect(onboarding[0]!.requesterName).toBe('Pfadi Neuhof')
    expect(onboarding[0]!.onboarding).toBe('linked')
    expect(useGaVerkaufMock().viewer.value).toEqual({ kind: 'department', department: 'Pfadi Neuhof' })
    expect(useGaVerkaufMock().departments.value).toContain('Pfadi Neuhof')
    expect(followUpAfterHandover(onboarding[0]!)).toBe('takeover')
  })

  it('plans the resale already at quote/agreement/purchase time', () => {
    const purchase = purchaseById('pu-saegen')!
    expect(purchase.afterUse).toBe('open')
    const offer = setAfterUse('pu-saegen', { afterUse: 'sale', saleQty: 2, availableFrom: '2026-11-10', expectedCondition: 'toCheck', priceChf: 25, pickup: 'Lager A', earlyPublish: true })!
    expect(offer.phase).toBe('planned')
    expect(offer.status).toBe('published')
    expect(offer.purchasedQty).toBe(2)
    expect(offer.qty).toBe(2)
    expect(offer.expectedCondition).toBe('toCheck')
    expect(offer.origin).toBe('Beschaffung · Offerte (Bar West)')
    expect(purchase.offerId).toBe(offer.id)
    // frühe Veröffentlichung: auch während des Anlasses reservierbar
    expect(visibleFor({ kind: 'department', department: 'Cevi Basel' }).open.map((row) => row.id)).toContain(offer.id)
    loginAsDepartment('Cevi Basel')
    expect(addToCart(offer.id, 1)).toBe(true)
  })

  it('allows the whole purchased quantity to be planned for resale and caps it at the purchase', () => {
    const offer = setAfterUse('pu-akku', { afterUse: 'sale', saleQty: 999, earlyPublish: false })!
    expect(offer.id).toBe('of-akku')
    expect(offer.qty).toBe(4)
    expect(offer.status).toBe('draft')
    expect(offer.phase).toBe('inUse')
  })

  it('pauses the offer when the planned outcome changes away from resale', () => {
    setAfterUse('pu-bretter', { afterUse: 'lager' })
    expect(purchaseById('pu-bretter')!.afterUse).toBe('lager')
    expect(offer('of-bretter').status).toBe('paused')
    expect(setAfterUse('pu-kantholz', { afterUse: 'reuse', reuseTarget: 'Crew-Zelt Backstage' })).toBeNull()
    expect(purchaseById('pu-kantholz')!.reuseTarget).toBe('Crew-Zelt Backstage')
  })

  it('stages reserved quantities directly for buyers', () => {
    expect(stageReserved('of-akku')).toBe(1)
    expect(stageReserved('of-akku')).toBe(0)
    expect(request('rq-8').staged).toBe(true)
  })
})
