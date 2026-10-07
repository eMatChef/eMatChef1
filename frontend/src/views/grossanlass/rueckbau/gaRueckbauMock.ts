/**
 * UI-Prototyp «Rückbau» (Material): Demo-Daten und lokaler State, ohne API und ohne Persistenz.
 * Kreislauf: Beschaffung/Offerte → vorgesehener Verbleib → Einsatz → Rückbau → Verbleib
 * (Lager / Weiterverwenden / Verkauf / Firma / Entsorgung) → ggf. Disposition → abgeschlossen.
 */
import { computed, reactive } from 'vue'
import {
  confirmResaleFromRueckbau,
  type GaConfirmedSplit,
  type GaRueckbauResaleResult,
} from '@/views/grossanlass/weiterverkauf/gaVerkaufMock'

export type GaFate = 'lager' | 'reuse' | 'sale' | 'return' | 'dispose' | 'workshop'
export type GaCondition = 'good' | 'used' | 'damaged'
export type GaOriginKind = 'eigen' | 'kauf' | 'leihe' | 'verbrauch'
export type GaFateFilter = 'all' | Exclude<GaFate, 'workshop'>

export const GA_DISPOSE_ROUTES = ['kehricht', 'sperrgut', 'sondermuell', 'recycling'] as const
export type GaDisposeRoute = (typeof GA_DISPOSE_ROUTES)[number]
export const GA_PROJECT_TARGETS = ['Bühne & Gerüst', 'Crew-Zelt Backstage', 'Info-Pagode Eingang', 'Catering-Zelt']
export const GA_STORAGE_PLACES = ['Zentrallager', 'Lager A', 'Unterlager Nord']
export const GA_DEPARTMENTS = ['Pfadi Löwen', 'Cevi Basel', 'Jubla Aarau', 'Abteilung Falkenstein']

export type GaRueckbauItem = {
  id: string
  project: string
  ressort: string
  name: string
  qty: number
  /** bereits erledigte Menge */
  qtyDone: number
  origin: string
  originKind: GaOriginKind
  /** Firma, an die zurückzugeben ist (Leihe) */
  firm?: string
  condition: GaCondition
  /** aus Beschaffung/Offerte vorgesehen */
  plannedFate: GaFate | null
  plannedLabel: string
  chosenFate: GaFate | null
  /** Details zur Wahl (Ziel, Preis, Entsorgungsweg …) */
  detail: string
  /** Rückgabe: gesammelt/gescannt */
  collected: boolean
  transportRequested: boolean
  disposeRoute?: GaDisposeRoute
  disposed?: boolean
}

export const RETURN_STEPS = ['collect', 'check', 'pack', 'label', 'transport'] as const
export type GaReturnStep = (typeof RETURN_STEPS)[number]
export type GaReturnGroup = { firm: string; step: number; packCode: string; labelPrinted: boolean; transportSent: boolean }

export type GaRueckbauState = {
  items: GaRueckbauItem[]
  returnGroups: Record<string, GaReturnGroup>
  nextId: number
  nextPack: number
}

function item(partial: Partial<GaRueckbauItem> & Pick<GaRueckbauItem, 'id' | 'name' | 'qty' | 'project'>): GaRueckbauItem {
  return {
    ressort: 'Demo-Bauten',
    qtyDone: 0,
    origin: 'Eigen',
    originKind: 'eigen',
    condition: 'good',
    plannedFate: null,
    plannedLabel: '',
    chosenFate: null,
    detail: '',
    collected: false,
    transportRequested: false,
    ...partial,
  }
}

function build(): GaRueckbauState {
  return {
    items: [
      item({ id: 'rb-1', project: 'Bar West', name: 'Akkuschrauber', qty: 4, qtyDone: 4, origin: 'Eigen', originKind: 'eigen', condition: 'good', plannedFate: 'lager', plannedLabel: 'Eigen → Lager', chosenFate: 'lager', detail: 'Zentrallager' }),
      item({ id: 'rb-2', project: 'Bar West', name: 'Kantholz 6×12 cm', qty: 14, qtyDone: 14, origin: 'Kauf, Häberli Holz', originKind: 'kauf', condition: 'used', plannedFate: 'reuse', plannedLabel: 'Offerte: Kauf → Weiterverwenden', chosenFate: 'reuse', detail: 'Projekt Bühne & Gerüst', transportRequested: true }),
      item({ id: 'rb-3', project: 'Bar West', name: 'Bretter 24×200', qty: 40, qtyDone: 0, origin: 'Kauf, Sägerei Roth', originKind: 'kauf', condition: 'used', plannedFate: 'sale', plannedLabel: 'Kauf → Weiterverkauf vorgesehen (komplette Menge, wird im Bau verwendet)' }),
      item({ id: 'rb-4', project: 'Bar West', name: 'Absperrgitter', qty: 12, qtyDone: 0, origin: 'Leihe, Eventtechnik AG', originKind: 'leihe', firm: 'Eventtechnik AG', condition: 'used', plannedFate: 'return', plannedLabel: 'Leihe → Rückgabe Firma' }),
      item({ id: 'rb-5', project: 'Bar West', name: 'Absperrgitter-Füsse', qty: 12, qtyDone: 0, origin: 'Leihe, Eventtechnik AG', originKind: 'leihe', firm: 'Eventtechnik AG', condition: 'good', plannedFate: 'return', plannedLabel: 'Leihe → Rückgabe Firma' }),
      item({ id: 'rb-6', project: 'Bar West', name: 'Kabelrolle 50 m', qty: 2, qtyDone: 0, origin: 'Eigen', originKind: 'eigen', condition: 'damaged', plannedFate: 'lager', plannedLabel: 'Eigen → Lager' }),
      item({ id: 'rb-7', project: 'Bar West', name: 'Verpackungsfolie', qty: 6, qtyDone: 0, origin: 'Verbrauch', originKind: 'verbrauch', condition: 'used', plannedFate: 'dispose', plannedLabel: 'Verbrauch → Entsorgung' }),
      item({ id: 'rb-11', project: 'Bar West', name: 'Akkuschrauber (neu gekauft)', qty: 4, origin: 'Kauf, Werkzeug AG', originKind: 'kauf', condition: 'used', plannedFate: 'sale', plannedLabel: 'Kauf → Weiterverkauf vorgesehen (alle 4 nach Aufbau/Rückbau)' }),
      item({ id: 'rb-8', project: 'Crew-Zelt Backstage', name: 'Bodenplatten 1×2 m', qty: 24, qtyDone: 0, ressort: 'Demo-Bauten', origin: 'Kauf, Zeltbau AG', originKind: 'kauf', condition: 'good', plannedFate: 'sale', plannedLabel: 'Offerte: Kauf → Weiterverkauf vorgesehen' }),
      item({ id: 'rb-9', project: 'Crew-Zelt Backstage', name: 'Festzelt 6×6 m', qty: 1, qtyDone: 0, origin: 'Leihe, Zeltbau AG', originKind: 'leihe', firm: 'Zeltbau AG', condition: 'good', plannedFate: 'return', plannedLabel: 'Leihe → Rückgabe Firma' }),
      item({ id: 'rb-10', project: 'Info-Pagode Eingang', name: 'Heringe', qty: 24, qtyDone: 24, ressort: 'Demo-Bauten', origin: 'Eigen', originKind: 'eigen', condition: 'used', plannedFate: 'lager', plannedLabel: 'Eigen → Lager', chosenFate: 'lager', detail: 'Lager A' }),
    ],
    returnGroups: {},
    nextId: 12,
    nextPack: 1,
  }
}

const state = reactive<GaRueckbauState>(build())

export function resetGaRueckbauDemo(): void {
  const fresh = build()
  state.items = fresh.items
  state.returnGroups = fresh.returnGroups
  state.nextId = fresh.nextId
  state.nextPack = fresh.nextPack
}

export function useGaRueckbauMock() {
  return {
    items: computed(() => state.items),
    returnGroups: computed(() => state.returnGroups),
  }
}

// ── Ableitungen ────────────────────────────────────────────────

export function openQty(row: GaRueckbauItem): number {
  return Math.max(0, row.qty - row.qtyDone)
}
export function isDone(row: GaRueckbauItem): boolean {
  return openQty(row) === 0
}
export function itemStatus(row: GaRueckbauItem): 'done' | 'open' | 'warning' {
  if (isDone(row)) return 'done'
  if (row.condition === 'damaged') return 'warning'
  return 'open'
}
export function effectiveFate(row: GaRueckbauItem): GaFate | null {
  if (row.condition === 'damaged' && !row.chosenFate) return 'workshop'
  return row.chosenFate ?? row.plannedFate
}

export function matchesFateFilter(row: GaRueckbauItem, filter: GaFateFilter): boolean {
  return filter === 'all' || effectiveFate(row) === filter
}

export function projects(list: GaRueckbauItem[]): string[] {
  return [...new Set(list.map((row) => row.project))]
}

export function projectProgress(list: GaRueckbauItem[], project: string) {
  const rows = list.filter((row) => row.project === project)
  const total = rows.reduce((sum, row) => sum + row.qty, 0)
  const done = rows.reduce((sum, row) => sum + row.qtyDone, 0)
  return { total, done, percent: total ? Math.round((done / total) * 100) : 0, positions: rows.length, doneRows: rows.filter(isDone).length }
}

export function fateCounts(list: GaRueckbauItem[]): Record<GaFateFilter, number> {
  const counts: Record<GaFateFilter, number> = { all: list.length, lager: 0, reuse: 0, sale: 0, return: 0, dispose: 0 }
  for (const row of list) {
    const fate = effectiveFate(row)
    if (fate && fate !== 'workshop') counts[fate] += 1
  }
  return counts
}

/** Abweichung zwischen vorgesehenem und gewähltem Verbleib. */
export function deviatesFromPlan(row: GaRueckbauItem): boolean {
  return !!row.chosenFate && !!row.plannedFate && row.chosenFate !== row.plannedFate
}

// ── lokale Aktionen ────────────────────────────────────────────

export type GaDecision = {
  fate: GaFate
  qty: number
  detail?: string
  priceChf?: number
  pickup?: string
  createTransport?: boolean
  disposeRoute?: GaDisposeRoute
  /** Weiterverkauf: tatsächlicher Zustand je Menge (Summe = Menge) */
  split?: GaConfirmedSplit
}

let lastResale: GaRueckbauResaleResult | null = null

/** Ergebnis der letzten Weiterverkauf-Bestätigung (Abgleich geplant/verkaufbar, Warnungen). */
export function lastResaleResult(): GaRueckbauResaleResult | null {
  return lastResale
}

export function decide(itemId: string, decision: GaDecision): boolean {
  const row = state.items.find((entry) => entry.id === itemId)
  if (!row) return false
  const qty = Math.min(Math.max(1, Math.floor(decision.qty)), openQty(row))
  if (!qty) return false
  row.chosenFate = decision.fate
  row.detail = decision.detail ?? row.detail
  if (decision.fate === 'lager' || decision.fate === 'reuse' || decision.fate === 'workshop') {
    row.qtyDone += qty
    if (decision.fate === 'reuse') row.transportRequested = !!decision.createTransport
  }
  if (decision.fate === 'sale') {
    row.qtyDone += qty
    // Weiterverkauf: Zustand je Menge bestätigen und mit dem geplanten Angebot abgleichen.
    const split: GaConfirmedSplit = decision.split ?? { good: qty, wear: 0, damaged: 0, notSellable: 0, workshop: 0 }
    lastResale = confirmResaleFromRueckbau({
      itemId: row.id,
      name: row.name,
      project: row.project,
      split,
      priceChf: decision.priceChf ?? 0,
      pickup: decision.pickup || 'Zentrallager',
    })
  }
  if (decision.fate === 'return') {
    row.collected = false
    row.disposeRoute = undefined
  }
  if (decision.fate === 'dispose') {
    row.disposeRoute = decision.disposeRoute ?? 'kehricht'
  }
  return true
}

/** «Wie vorgesehen»: übernimmt den vorgesehenen Verbleib aus Beschaffung/Offerte. */
export function applyPlanned(itemId: string): boolean {
  const row = state.items.find((entry) => entry.id === itemId)
  if (!row?.plannedFate) return false
  return decide(itemId, {
    fate: row.plannedFate,
    qty: openQty(row),
    detail: row.plannedFate === 'lager' ? 'Zentrallager' : row.firm ?? '',
    priceChf: 1,
    pickup: 'Zentrallager',
  })
}

// Rückgabe Firma
export function returnItems(list = state.items): GaRueckbauItem[] {
  return list.filter((row) => effectiveFate(row) === 'return' && !isDone(row))
}
export function returnFirms(): string[] {
  return [...new Set(returnItems().map((row) => row.firm ?? row.origin))]
}
export function returnGroup(firm: string): GaReturnGroup {
  if (!state.returnGroups[firm]) {
    state.returnGroups[firm] = { firm, step: 0, packCode: '', labelPrinted: false, transportSent: false }
  }
  return state.returnGroups[firm]!
}
export function firmItems(firm: string): GaRueckbauItem[] {
  return returnItems().filter((row) => (row.firm ?? row.origin) === firm)
}
export function toggleCollected(itemId: string): void {
  const row = state.items.find((entry) => entry.id === itemId)
  if (row) row.collected = !row.collected
}
/** Scan (Demo): Treffer über Name, markiert gesammelt. */
export function scanReturnItem(firm: string, query: string): GaRueckbauItem | null {
  const q = query.trim().toLowerCase()
  if (!q) return null
  const hit = firmItems(firm).find((row) => row.name.toLowerCase().includes(q) || row.id === q)
  if (hit) hit.collected = true
  return hit ?? null
}
export function canAdvanceReturn(firm: string): boolean {
  const group = returnGroup(firm)
  const rows = firmItems(firm)
  if (group.step === 0) return rows.length > 0 && rows.every((row) => row.collected)
  if (group.step === 3) return group.labelPrinted
  return group.step < RETURN_STEPS.length - 1
}
export function advanceReturn(firm: string): boolean {
  const group = returnGroup(firm)
  if (!canAdvanceReturn(firm)) return false
  group.step += 1
  if (RETURN_STEPS[group.step] === 'pack' && !group.packCode) {
    group.packCode = `RP-${String(state.nextPack).padStart(4, '0')}`
    state.nextPack += 1
  }
  return true
}
export function markReturnLabel(firm: string): void {
  returnGroup(firm).labelPrinted = true
}
/** Sendet den Rückgabe-Transportbedarf an die Disposition (nur UI) und schliesst die Positionen ab. */
export function sendReturnTransport(firm: string): boolean {
  const group = returnGroup(firm)
  if (RETURN_STEPS[group.step] !== 'transport' || group.transportSent) return false
  group.transportSent = true
  for (const row of firmItems(firm)) {
    row.qtyDone = row.qty
    row.transportRequested = true
  }
  return true
}

// Entsorgung
export function disposeItems(): GaRueckbauItem[] {
  return state.items.filter((row) => effectiveFate(row) === 'dispose')
}
export function setDisposeRoute(itemId: string, route: GaDisposeRoute): void {
  const row = state.items.find((entry) => entry.id === itemId)
  if (row) row.disposeRoute = route
}
export function markDisposed(itemId: string): boolean {
  const row = state.items.find((entry) => entry.id === itemId)
  if (!row || effectiveFate(row) !== 'dispose' || row.disposed) return false
  row.chosenFate = 'dispose'
  row.disposeRoute ??= 'kehricht'
  row.disposed = true
  row.qtyDone = row.qty
  return true
}
