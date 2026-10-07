/**
 * UI-Prototyp «Weiterverkauf» (intern) und öffentliche Vorschau qr.ematchef.ch.
 * Ein gemeinsamer lokaler Demo-State, ohne API und ohne Persistenz.
 * Flow: Rückbau → Weiterverkauf → veröffentlichen → öffentliche Seite → Abteilung/Interessent
 * → MW sieht Anfrage → bestätigt → Übergabe / ggf. Disposition → verkauft.
 */
import { logEvent } from '@/views/grossanlass/live/gaLiveEvents'
import { computed, reactive } from 'vue'

export type GaOfferCondition = 'good' | 'used' | 'damaged'
/** Verbleib nach dem Anlass, bei Offerte/Absprache/Kauf erfasst. */
export type GaAfterUse = 'lager' | 'reuse' | 'sale' | 'dispose' | 'open'
export type GaPurchaseSource = 'quote' | 'agreement' | 'purchase'
export const GA_AFTER_USE: GaAfterUse[] = ['lager', 'reuse', 'sale', 'dispose', 'open']

export type GaPurchase = {
  id: string
  label: string
  qty: number
  supplier: string
  source: GaPurchaseSource
  project: string
  afterUse: GaAfterUse
  /** Zielprojekt bei «Weiterverwenden» */
  reuseTarget: string
  rueckbauItemId?: string
  offerId?: string
}

export type GaOfferStatus = 'draft' | 'published' | 'paused'
/** Lebenslauf: gekauft/geplant → wird im Anlass eingesetzt → nach Rückbau bestätigt. */
export type GaOfferPhase = 'planned' | 'inUse' | 'afterUse'
export type GaExpectedCondition = 'new' | 'used' | 'wear' | 'toCheck'
export type GaConfirmedKey = 'good' | 'wear' | 'damaged' | 'notSellable' | 'workshop'
export type GaConfirmedSplit = Record<GaConfirmedKey, number>
export const GA_CONFIRMED_KEYS: GaConfirmedKey[] = ['good', 'wear', 'damaged', 'notSellable', 'workshop']
export type GaOfferVisibility = 'departments' | 'public'
export type GaRequestStatus = 'new' | 'reserved' | 'confirmed' | 'declined' | 'handed'
export type GaHandover = 'pickup' | 'transport'
export type GaRequesterKind = 'department' | 'onboarding' | 'external'
export type GaOnboardingState = 'invited' | 'linked'

export const GA_OFFER_PICKUPS = ['Zentrallager', 'Lager A', 'Unterlager Nord']
export const GA_VERKAUF_DEPARTMENTS = ['Pfadi Löwen', 'Cevi Basel', 'Jubla Aarau', 'Abteilung Falkenstein']

export type GaOffer = {
  id: string
  name: string
  description: string
  /** geplante Verkaufsmenge nach dem Anlass (darf der ganzen gekauften Menge entsprechen) */
  qty: number
  /** Preis, 0 = noch offen (optional) */
  priceChf: number
  condition: GaOfferCondition
  phase: GaOfferPhase
  /** gekaufte Menge (Beschaffung) und Verwendung während des Anlasses */
  purchasedQty: number | null
  useQty: number | null
  /** erwarteter Zustand beim Verkauf (vor dem Rückbau) */
  expectedCondition: GaExpectedCondition
  /** schon vor dem Rückbau veröffentlichen */
  earlyPublish: boolean
  /** beim Rückbau bestätigter tatsächlicher Zustand je Menge */
  confirmed: GaConfirmedSplit | null
  /** Anzahl Bild-Platzhalter */
  images: number
  /** ISO-Datum (yyyy-mm-dd) */
  availableFrom: string
  pickup: string
  visibility: GaOfferVisibility
  status: GaOfferStatus
  /** Herkunft, z. B. «Rückbau · Bar West» */
  origin: string
  rueckbauItemId?: string
}

export type GaOfferRequest = {
  id: string
  offerId: string
  requesterName: string
  requesterKind: GaRequesterKind
  /** Organisation / Verein / Abteilung (bei externen Interessenten) */
  organisation: string
  contact: string
  phone: string
  /** nur bei «eMatChef wird eingerichtet» */
  onboarding?: GaOnboardingState
  qty: number
  handover: GaHandover
  status: GaRequestStatus
  note: string
  /** Mock: Transportbedarf an Disposition gemeldet */
  transportSent: boolean
  /** beim Rückbau direkt für den Käufer bereitgestellt (nicht eingelagert) */
  staged?: boolean
  handoverCode: string
}

export type GaCartLine = { offerId: string; qty: number }
export type GaViewer = { kind: 'guest' } | { kind: 'department'; department: string }

type State = {
  purchases: GaPurchase[]
  offers: GaOffer[]
  requests: GaOfferRequest[]
  cart: GaCartLine[]
  viewer: GaViewer
  nextId: number
  nextCode: number
  departments: string[]
}

function isoOffset(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() + days)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function build(): State {
  return {
    purchases: [
      { id: 'pu-bretter', label: 'Bretter 24×200', qty: 40, supplier: 'Sägerei Roth', source: 'purchase', project: 'Bar West', afterUse: 'sale', reuseTarget: '', rueckbauItemId: 'rb-3', offerId: 'of-bretter' },
      { id: 'pu-akku', label: 'Akkuschrauber (neu)', qty: 4, supplier: 'Werkzeug AG', source: 'purchase', project: 'Bar West', afterUse: 'sale', reuseTarget: '', rueckbauItemId: 'rb-11', offerId: 'of-akku' },
      { id: 'pu-kantholz', label: 'Kantholz 6×12 cm', qty: 14, supplier: 'Häberli Holz', source: 'agreement', project: 'Bar West', afterUse: 'reuse', reuseTarget: 'Bühne & Gerüst', rueckbauItemId: 'rb-2' },
      { id: 'pu-schrauben', label: 'Schrauben 6×120', qty: 200, supplier: 'Baumarkt', source: 'purchase', project: 'Bar West', afterUse: 'dispose', reuseTarget: '' },
      { id: 'pu-saegen', label: 'Stichsägen', qty: 2, supplier: 'Werkzeug AG', source: 'quote', project: 'Bar West', afterUse: 'open', reuseTarget: '' },
    ],
    offers: [
      { id: 'of-bretter', name: 'Bretter 24×200 (aus dem Bau)', description: 'Für den Bau der Bar West gekauft. Nach dem Rückbau möglichst alle brauchbaren Bretter weiterverkauft.', qty: 40, priceChf: 1.5, condition: 'used', phase: 'inUse', purchasedQty: 40, useQty: 40, expectedCondition: 'wear', earlyPublish: true, confirmed: null, images: 0, availableFrom: '2026-11-09', pickup: 'Lager A', visibility: 'public', status: 'published', origin: 'Beschaffung · Kauf (Bar West)', rueckbauItemId: 'rb-3' },
      { id: 'of-akku', name: 'Akkuschrauber (neu gekauft)', description: 'Für Aufbau und Rückbau gekauft und genutzt, danach alle 4 gebraucht zum Verkauf vorgesehen.', qty: 4, priceChf: 60, condition: 'used', phase: 'inUse', purchasedQty: 4, useQty: 4, expectedCondition: 'wear', earlyPublish: true, confirmed: null, images: 0, availableFrom: '2026-11-10', pickup: 'Zentrallager', visibility: 'departments', status: 'published', origin: 'Beschaffung · Kauf (Werkzeug)', rueckbauItemId: 'rb-11' },
      { id: 'of-1', name: 'Festbank-Garnitur (gebraucht)', description: 'Tisch mit zwei Bänken, Holz, leichte Gebrauchsspuren. Klappbar.', qty: 6, priceChf: 35, condition: 'used', phase: 'afterUse', purchasedQty: null, useQty: null, expectedCondition: 'used', earlyPublish: false, confirmed: { good: 3, wear: 3, damaged: 0, notSellable: 0, workshop: 0 }, images: 2, availableFrom: isoOffset(0), pickup: 'Zentrallager', visibility: 'departments', status: 'published', origin: 'Rückbau · Catering-Zelt' },
      { id: 'of-2', name: 'Palette Schalbretter', description: 'Schalbretter 24×200 mm, sägeroh, ideal für Bauten und Lagerregale.', qty: 3, priceChf: 60, condition: 'good', phase: 'afterUse', purchasedQty: null, useQty: null, expectedCondition: 'new', earlyPublish: false, confirmed: { good: 3, wear: 0, damaged: 0, notSellable: 0, workshop: 0 }, images: 1, availableFrom: isoOffset(0), pickup: 'Lager A', visibility: 'public', status: 'published', origin: 'Rückbau · Bar West' },
      { id: 'of-3', name: 'Bodenplatten 1×2 m (gebraucht)', description: 'Bodenplatten aus dem Crew-Zelt, voll nutzbar.', qty: 24, priceChf: 8, condition: 'used', phase: 'afterUse', purchasedQty: null, useQty: null, expectedCondition: 'used', earlyPublish: false, confirmed: { good: 14, wear: 10, damaged: 0, notSellable: 0, workshop: 0 }, images: 0, availableFrom: isoOffset(3), pickup: 'Unterlager Nord', visibility: 'public', status: 'paused', origin: 'Rückbau · Crew-Zelt Backstage' },
      { id: 'of-4', name: 'Kantholz 6×12 cm Restposten', description: 'Kantholz in Längen von 2 bis 4 m.', qty: 10, priceChf: 4.5, condition: 'good', phase: 'afterUse', purchasedQty: null, useQty: null, expectedCondition: 'new', earlyPublish: false, confirmed: { good: 10, wear: 0, damaged: 0, notSellable: 0, workshop: 0 }, images: 1, availableFrom: isoOffset(0), pickup: 'Zentrallager', visibility: 'public', status: 'published', origin: 'Rückbau · Bar West' },
    ],
    requests: [
      { id: 'rq-1', offerId: 'of-1', requesterName: 'Pfadi Löwen', requesterKind: 'department', organisation: 'Pfadi Löwen', contact: 'material@pfadi-loewen.example', phone: '', qty: 2, handover: 'pickup', status: 'reserved', note: 'Abholung am Samstag.', transportSent: false, handoverCode: '' },
      { id: 'rq-2', offerId: 'of-1', requesterName: 'Cevi Basel', requesterKind: 'department', organisation: 'Cevi Basel', contact: 'material@cevi-basel.example', phone: '', qty: 3, handover: 'transport', status: 'new', note: 'Bitte liefern, wir haben kein Fahrzeug.', transportSent: false, handoverCode: '' },
      { id: 'rq-3', offerId: 'of-2', requesterName: 'M. Keller', requesterKind: 'external', organisation: 'Privat', contact: 'keller@example.ch', phone: '', qty: 1, handover: 'pickup', status: 'new', note: '', transportSent: false, handoverCode: '' },
      { id: 'rq-4', offerId: 'of-1', requesterName: 'Jubla Aarau', requesterKind: 'department', organisation: 'Jubla Aarau', contact: 'jubla-aarau@example.ch', phone: '', qty: 1, handover: 'pickup', status: 'handed', note: '', transportSent: false, handoverCode: 'UE-0001' },
      { id: 'rq-6', offerId: 'of-2', requesterName: 'Pfadi Wolfsburg', requesterKind: 'onboarding', organisation: 'Pfadi Wolfsburg', contact: 'leitung@pfadi-wolfsburg.example', phone: '', qty: 1, handover: 'pickup', status: 'new', note: 'Wir richten eMatChef gerade ein.', transportSent: false, handoverCode: '', onboarding: 'invited' },
      { id: 'rq-7', offerId: 'of-bretter', requesterName: 'Cevi Basel', requesterKind: 'department', organisation: 'Cevi Basel', contact: 'material@cevi-basel.example', phone: '', qty: 10, handover: 'pickup', status: 'reserved', note: 'Für unser Lager, ab 09.11.', transportSent: false, handoverCode: '' },
      { id: 'rq-8', offerId: 'of-akku', requesterName: 'Jubla Aarau', requesterKind: 'department', organisation: 'Jubla Aarau', contact: 'jubla-aarau@example.ch', phone: '', qty: 3, handover: 'pickup', status: 'reserved', note: '', transportSent: false, handoverCode: '' },
      { id: 'rq-5', offerId: 'of-4', requesterName: 'Schreinerei Huber', requesterKind: 'external', organisation: 'Schreinerei Huber AG', contact: 'huber@example.ch', phone: '062 000 00 00', qty: 10, handover: 'transport', status: 'new', note: 'Gesamte Menge, Lieferung nach Zofingen.', transportSent: false, handoverCode: '' },
    ],
    cart: [],
    viewer: { kind: 'guest' },
    nextId: 10,
    nextCode: 2,
    departments: [...GA_VERKAUF_DEPARTMENTS],
  }
}

const state = reactive<State>(build())

export function resetGaVerkaufDemo(): void {
  const fresh = build()
  state.purchases = fresh.purchases
  state.offers = fresh.offers
  state.requests = fresh.requests
  state.cart = fresh.cart
  state.viewer = fresh.viewer
  state.nextId = fresh.nextId
  state.nextCode = fresh.nextCode
  state.departments = fresh.departments
}

export function useGaVerkaufMock() {
  return {
    offers: computed(() => state.offers),
    purchases: computed(() => state.purchases),
    requests: computed(() => state.requests),
    cart: computed(() => state.cart),
    viewer: computed(() => state.viewer),
    departments: computed(() => state.departments),
  }
}

// ── Ableitungen ────────────────────────────────────────────────

export function offerById(id: string | undefined | null): GaOffer | undefined {
  return state.offers.find((row) => row.id === id)
}

const RESERVING: GaRequestStatus[] = ['reserved', 'confirmed']

/** Verkaufbare Menge: nach dem Rückbau die bestätigte Menge (gut + Gebrauchsspuren), vorher die geplante. */
export function sellableQty(offer: GaOffer): number {
  return offer.confirmed ? offer.confirmed.good + offer.confirmed.wear : offer.qty
}

export function offerStats(offer: GaOffer) {
  const own = state.requests.filter((row) => row.offerId === offer.id)
  const reserved = own.filter((row) => RESERVING.includes(row.status)).reduce((sum, row) => sum + row.qty, 0)
  const sold = own.filter((row) => row.status === 'handed').reduce((sum, row) => sum + row.qty, 0)
  return { reserved, sold, available: Math.max(0, sellableQty(offer) - reserved - sold), open: own.filter((row) => row.status === 'new').length }
}

export function requestsOf(status: GaRequestStatus[] ): GaOfferRequest[] {
  return state.requests.filter((row) => status.includes(row.status))
}

export function soldRevenue(): number {
  return state.requests
    .filter((row) => row.status === 'handed')
    .reduce((sum, row) => sum + row.qty * (offerById(row.offerId)?.priceChf ?? 0), 0)
}

/** Öffentlich sichtbar: veröffentlicht, vor dem Rückbau nur bei «frühzeitig veröffentlichen». */
export function isPublicVisible(offer: GaOffer): boolean {
  return offer.status === 'published' && (offer.phase === 'afterUse' || offer.earlyPublish)
}

export type GaOfferWarning = { kind: 'reservedExceeds'; reserved: number; sellable: number } | { kind: 'deviates'; planned: number; sellable: number }

/** Reservierungen vs. verkaufbare Menge, und Abweichung zwischen geplanter und bestätigter Menge. */
export function offerWarnings(offer: GaOffer): GaOfferWarning[] {
  const out: GaOfferWarning[] = []
  const { reserved } = offerStats(offer)
  const sellable = sellableQty(offer)
  if (reserved > sellable) out.push({ kind: 'reservedExceeds', reserved, sellable })
  if (offer.confirmed && sellable !== offer.qty) out.push({ kind: 'deviates', planned: offer.qty, sellable })
  return out
}

/** Was der aktuelle Betrachter auf der öffentlichen Seite sieht. */
export function visibleFor(viewer: GaViewer): { open: GaOffer[]; locked: GaOffer[] } {
  const published = state.offers.filter(isPublicVisible)
  const open = published.filter((offer) => offer.visibility === 'public' || viewer.kind === 'department')
  const locked = published.filter((offer) => !open.includes(offer))
  return { open, locked }
}

// ── interne Aktionen ───────────────────────────────────────────

export type GaOfferDraft = Omit<GaOffer, 'id' | 'status' | 'origin' | 'phase' | 'confirmed'> & { id?: string; status?: GaOfferStatus; origin?: string; phase?: GaOfferPhase }

export function saveOffer(draft: GaOfferDraft): GaOffer {
  const existing = draft.id ? offerById(draft.id) : undefined
  if (existing) {
    Object.assign(existing, {
      name: draft.name,
      description: draft.description,
      qty: draft.qty,
      priceChf: draft.priceChf,
      condition: draft.condition,
      images: draft.images,
      availableFrom: draft.availableFrom,
      pickup: draft.pickup,
      visibility: draft.visibility,
      purchasedQty: draft.purchasedQty,
      useQty: draft.useQty,
      expectedCondition: draft.expectedCondition,
      earlyPublish: draft.earlyPublish,
    })
    return existing
  }
  const offer: GaOffer = {
    id: `of-${state.nextId}`,
    status: draft.status ?? 'draft',
    origin: draft.origin ?? 'Manuell',
    phase: draft.phase ?? 'planned',
    confirmed: null,
    ...draft,
  }
  state.nextId += 1
  state.offers.unshift(offer)
  return offer
}

export type GaRueckbauResaleInput = {
  itemId: string
  name: string
  project: string
  /** tatsächlicher Zustand je Menge */
  split: GaConfirmedSplit
  priceChf: number
  pickup: string
}

export type GaRueckbauResaleResult = {
  offer: GaOffer
  sellable: number
  planned: number | null
  warnings: GaOfferWarning[]
}

export function splitTotal(split: GaConfirmedSplit): number {
  return GA_CONFIRMED_KEYS.reduce((sum, key) => sum + split[key], 0)
}

/**
 * Rückbau bestätigt den Zustand je Menge. Ein geplantes Angebot (aus Beschaffung/Kauf) wird abgeglichen:
 * verkaufbar = gut + Gebrauchsspuren. Ohne geplantes Angebot entsteht ein Entwurf. Reservierungen bleiben erhalten.
 */
export function confirmResaleFromRueckbau(input: GaRueckbauResaleInput): GaRueckbauResaleResult {
  const sellable = input.split.good + input.split.wear
  const condition: GaOfferCondition = input.split.wear > 0 ? 'used' : 'good'
  const existing = state.offers.find((row) => row.rueckbauItemId === input.itemId)
  if (existing) {
    const planned = existing.qty
    existing.confirmed = { ...input.split }
    existing.phase = 'afterUse'
    existing.condition = condition
    existing.availableFrom = isoOffset(0)
    if (existing.priceChf <= 0 && input.priceChf > 0) existing.priceChf = input.priceChf
    return { offer: existing, sellable, planned, warnings: offerWarnings(existing) }
  }
  const offer = saveOffer({
    name: input.name,
    description: '',
    qty: sellable,
    priceChf: input.priceChf,
    condition,
    images: 0,
    availableFrom: isoOffset(0),
    pickup: input.pickup,
    visibility: 'departments',
    status: 'draft',
    origin: `Rückbau · ${input.project}`,
    rueckbauItemId: input.itemId,
    purchasedQty: null,
    useQty: null,
    expectedCondition: 'used',
    earlyPublish: false,
    phase: 'afterUse',
  })
  offer.confirmed = { ...input.split }
  return { offer, sellable, planned: null, warnings: offerWarnings(offer) }
}

export function setOfferStatus(offerId: string, status: GaOfferStatus): boolean {
  const offer = offerById(offerId)
  if (!offer) return false
  if (status === 'published' && (offer.qty <= 0 || offer.priceChf < 0)) return false
  offer.status = status
  return true
}

export function declineRequest(requestId: string): boolean {
  const request = state.requests.find((row) => row.id === requestId)
  if (!request || request.status === 'handed' || request.status === 'declined') return false
  request.status = 'declined'
  return true
}

export function reserveRequest(requestId: string): boolean {
  const request = state.requests.find((row) => row.id === requestId)
  const offer = offerById(request?.offerId)
  if (!request || !offer || request.status !== 'new') return false
  if (request.qty > offerStats(offer).available) return false
  request.status = 'reserved'
  logEvent({ area: 'sale', actor: 'Materialwart', text: `Reservierung bestätigt: ${request.qty}× ${offer.name}`, detail: request.requesterName, project: '' })
  return true
}

/** Übergabe vorbereiten: Code erzeugen, bei Transport den Bedarf an die Disposition melden (Mock). */
export function prepareHandover(requestId: string): boolean {
  const request = state.requests.find((row) => row.id === requestId)
  if (!request || request.status !== 'reserved') return false
  request.status = 'confirmed'
  request.handoverCode = `UE-${String(state.nextCode).padStart(4, '0')}`
  state.nextCode += 1
  if (request.handover === 'transport') request.transportSent = true
  return true
}

export function completeHandover(requestId: string): boolean {
  const request = state.requests.find((row) => row.id === requestId)
  if (!request || request.status !== 'confirmed') return false
  request.status = 'handed'
  logEvent({ area: 'sale', severity: 'success', actor: 'Materialwart', text: `Übergeben: ${request.qty}× ${offerById(request.offerId)?.name ?? ''}`, detail: request.requesterName, project: '' })
  return true
}

// ── öffentliche Seite ──────────────────────────────────────────

export function loginAsDepartment(department: string): void {
  state.viewer = { kind: 'department', department }
}
export function logoutViewer(): void {
  state.viewer = { kind: 'guest' }
}

export function addToCart(offerId: string, qty: number): boolean {
  const offer = offerById(offerId)
  if (!offer || !isPublicVisible(offer)) return false
  if (offer.visibility === 'departments' && state.viewer.kind !== 'department') return false
  const available = offerStats(offer).available
  const line = state.cart.find((row) => row.offerId === offerId)
  const wanted = Math.min(Math.max(1, Math.floor(qty)), available)
  if (available <= 0) return false
  if (line) line.qty = Math.min(available, line.qty + wanted)
  else state.cart.push({ offerId, qty: wanted })
  return true
}

export function setCartQty(offerId: string, qty: number): void {
  const offer = offerById(offerId)
  const line = state.cart.find((row) => row.offerId === offerId)
  if (!offer || !line) return
  line.qty = Math.min(Math.max(1, Math.floor(qty)), Math.max(1, offerStats(offer).available))
}

export function removeFromCart(offerId: string): void {
  state.cart = state.cart.filter((row) => row.offerId !== offerId)
}

export function cartTotal(): number {
  return state.cart.reduce((sum, line) => sum + line.qty * (offerById(line.offerId)?.priceChf ?? 0), 0)
}

export type GaCartSubmit = {
  name: string
  contact: string
  kind: GaRequesterKind
  handover: GaHandover
  organisation?: string
  phone?: string
  note?: string
}

/** Sendet den Warenkorb als Anfragen (Status «neu»). Reserviert wird erst durch den MW. */
export function submitCart(input: GaCartSubmit): GaOfferRequest[] {
  const created: GaOfferRequest[] = []
  for (const line of state.cart) {
    const request: GaOfferRequest = {
      id: `rq-${state.nextId}`,
      offerId: line.offerId,
      requesterName: input.name,
      requesterKind: input.kind,
      organisation: input.organisation ?? '',
      contact: input.contact,
      phone: input.phone ?? '',
      qty: line.qty,
      handover: input.handover,
      status: 'new',
      note: input.note ?? '',
      transportSent: false,
      handoverCode: '',
      onboarding: input.kind === 'onboarding' ? 'invited' : undefined,
    }
    state.nextId += 1
    state.requests.unshift(request)
    created.push(request)
  }
  state.cart = []
  return created
}

/**
 * Demo-Onboarding: legt die neue Abteilung lokal an, verknüpft bestehende Anfragen damit
 * und meldet den Betrachter als diese Abteilung an. Keine echte Registrierung.
 */
export function completeOnboarding(requestIds: string[], department: string): number {
  const name = department.trim()
  if (!name) return 0
  if (!state.departments.includes(name)) state.departments.push(name)
  let linked = 0
  for (const request of state.requests) {
    if (requestIds.includes(request.id) && request.requesterKind === 'onboarding') {
      request.requesterName = name
      request.organisation = name
      request.onboarding = 'linked'
      linked += 1
    }
  }
  state.viewer = { kind: 'department', department: name }
  return linked
}

/** Käufer-Abteilungen erhalten nach der Übergabe die Materialübernahme (nur Hinweis, noch nicht gebaut). */
export function followUpAfterHandover(request: GaOfferRequest): 'none' | 'takeover' {
  return request.requesterKind === 'external' ? 'none' : 'takeover'
}

// ── Verbleib nach dem Anlass (Offerte / Absprache / Kauf) ──────

export type GaAfterUseDecision = {
  afterUse: GaAfterUse
  reuseTarget?: string
  /** Weiterverkaufen: geplante Verkaufsmenge (darf der ganzen Kaufmenge entsprechen) */
  saleQty?: number
  availableFrom?: string
  expectedCondition?: GaExpectedCondition
  priceChf?: number
  pickup?: string
  earlyPublish?: boolean
}

export function purchaseById(id: string): GaPurchase | undefined {
  return state.purchases.find((row) => row.id === id)
}

const SOURCE_LABEL: Record<GaPurchaseSource, string> = { quote: 'Offerte', agreement: 'Absprache', purchase: 'Kauf' }

/**
 * Hält den geplanten Verbleib fest. Bei «Weiterverkaufen» entsteht bzw. aktualisiert sich das Angebot in der
 * bestehenden Weiterverkaufslogik (Phase «geplant», bei früher Veröffentlichung sofort sichtbar); bei einem
 * anderen Verbleib wird ein vorhandenes Angebot pausiert.
 */
export function setAfterUse(purchaseId: string, decision: GaAfterUseDecision): GaOffer | null {
  const purchase = purchaseById(purchaseId)
  if (!purchase) return null
  purchase.afterUse = decision.afterUse
  purchase.reuseTarget = decision.afterUse === 'reuse' ? (decision.reuseTarget ?? '') : ''
  const existing = purchase.offerId ? offerById(purchase.offerId) : undefined
  if (decision.afterUse !== 'sale') {
    if (existing && existing.phase !== 'afterUse') existing.status = 'paused'
    return null
  }
  const saleQty = Math.min(Math.max(1, Math.floor(decision.saleQty ?? purchase.qty)), purchase.qty)
  const draft: GaOfferDraft = {
    name: purchase.label,
    description: existing?.description ?? '',
    qty: saleQty,
    priceChf: decision.priceChf ?? 0,
    condition: 'used',
    images: existing?.images ?? 0,
    availableFrom: decision.availableFrom ?? isoOffset(14),
    pickup: decision.pickup || 'Zentrallager',
    visibility: existing?.visibility ?? 'departments',
    purchasedQty: purchase.qty,
    useQty: purchase.qty,
    expectedCondition: decision.expectedCondition ?? 'wear',
    earlyPublish: !!decision.earlyPublish,
    id: existing?.id,
    origin: `Beschaffung · ${SOURCE_LABEL[purchase.source]} (${purchase.project})`,
    rueckbauItemId: purchase.rueckbauItemId,
    phase: existing?.phase ?? 'planned',
  }
  const offer = saveOffer(draft)
  offer.phase = existing?.phase ?? 'planned'
  offer.status = decision.earlyPublish ? 'published' : existing?.status === 'published' ? 'draft' : (existing?.status ?? 'draft')
  purchase.offerId = offer.id
  return offer
}

/** Beim Rückbau: reservierte Mengen direkt für die Käufer bereitstellen statt wieder einzulagern. */
export function stageReserved(offerId: string): number {
  let staged = 0
  for (const request of state.requests) {
    if (request.offerId === offerId && (request.status === 'reserved' || request.status === 'confirmed') && !request.staged) {
      request.staged = true
      staged += 1
    }
  }
  if (staged) logEvent({ area: 'sale', actor: 'Rückbau', text: `${staged} Reservierung(en) direkt für Käufer bereitgestellt`, detail: offerById(offerId)?.name ?? '', project: '' })
  return staged
}
