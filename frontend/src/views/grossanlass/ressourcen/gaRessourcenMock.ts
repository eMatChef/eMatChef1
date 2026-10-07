/**
 * Gemeinsame Ressourcenanforderungen (Demo-State): werden von Planung (Aufträge/Bauaufträge)
 * erfasst und später von der Logistik verwendet. Planung: «Wann wäre die Ressource sinnvoll?»,
 * Logistik: «Wer/was übernimmt den konkreten Einsatz?». Keine zweite Ressourcen-/Transportstruktur.
 * Es gibt keine automatische Optimierung; Vorschläge sind simulierte Demo-Daten.
 */
import { computed, reactive } from 'vue'
import { anlassAt } from '@/views/grossanlass/aufgaben/gaAufgabenMock'

export type GaResourceType = 'crane' | 'forklift' | 'machine' | 'vehicle' | 'trailer' | 'tool' | 'helper'
export type GaResourceStatus = 'need' | 'proposal' | 'planned'
export const GA_RESOURCE_TYPES: GaResourceType[] = ['crane', 'forklift', 'machine', 'vehicle', 'trailer', 'tool', 'helper']
/** Typen, die die Logistik betreffen (Fahrzeuge, Maschinen, Anhänger …). */
export const GA_LOGISTICS_RESOURCE_TYPES: GaResourceType[] = ['crane', 'forklift', 'machine', 'vehicle', 'trailer']

export type GaTimeSlot = { from: Date; to: Date }

export type GaResourceNeed = {
  id: string
  orderId: string
  type: GaResourceType
  label: string
  durationH: number
  earliest: Date
  latest: Date
  wish: GaTimeSlot | null
  flexible: boolean
  qty: number
  note: string
  status: GaResourceStatus
  planned: GaTimeSlot | null
  proposal: (GaTimeSlot & { reason: string }) | null
  /** erst möglich nach dieser Aufgabe (Aufgaben-Demo) */
  dependsOnTaskId?: string
}

/** Bereits anderweitig verplante Nutzungen derselben Ressource (Demo). */
export type GaResourceBooking = { label: string; where: string; slot: GaTimeSlot }

export const GA_RESOURCE_BOOKINGS: GaResourceBooking[] = [
  { label: 'Mobilkran 20 t', where: 'Bühne Nord', slot: { from: anlassAt(4, 8), to: anlassAt(4, 10) } },
  { label: 'Stapler', where: 'Zentrallager', slot: { from: anlassAt(4, 7), to: anlassAt(4, 9) } },
  { label: 'Hebebühne', where: 'Bühne Süd', slot: { from: anlassAt(5, 8), to: anlassAt(5, 11) } },
]

type State = { needs: GaResourceNeed[]; nextId: number }

const slot = (day: number, h1: number, m1: number, h2: number, m2 = 0): GaTimeSlot => ({ from: anlassAt(day, h1, m1), to: anlassAt(day, h2, m2) })

function build(): State {
  return {
    nextId: 20,
    needs: [
      {
        id: 'rn-kran', orderId: 'ao-bar-west', type: 'crane', label: 'Mobilkran 20 t', durationH: 2,
        earliest: anlassAt(3, 13), latest: anlassAt(5, 12), wish: slot(4, 8, 0, 12), flexible: true, qty: 1,
        note: 'Dachelemente heben, erst nach der Holzkonstruktion.', status: 'proposal', planned: null,
        proposal: { ...slot(4, 10, 30, 12, 30), reason: 'Kran bereits bei Bühne Nord 04.11. 08:00–10:00' },
        dependsOnTaskId: 'ga-demo-bw-holz',
      },
      {
        id: 'rn-stapler', orderId: 'ao-bar-west', type: 'forklift', label: 'Stapler', durationH: 4,
        earliest: anlassAt(3, 8), latest: anlassAt(5, 17), wish: null, flexible: true, qty: 1,
        note: 'Paletten und Kantholz verteilen.', status: 'planned', planned: slot(3, 9, 0, 13), proposal: null,
      },
      {
        id: 'rn-anhaenger', orderId: 'ao-bar-west', type: 'trailer', label: 'Anhänger 2', durationH: 3,
        earliest: anlassAt(3, 8), latest: anlassAt(3, 16), wish: slot(3, 8, 0, 11), flexible: false, qty: 1,
        note: 'Holztransport vom Zentrallager.', status: 'need', planned: null, proposal: null,
      },
      {
        id: 'rn-hebebuehne', orderId: 'ao-bar-west', type: 'machine', label: 'Hebebühne', durationH: 3,
        earliest: anlassAt(4, 13), latest: anlassAt(5, 12), wish: null, flexible: true, qty: 1,
        note: 'Elektrik an der Decke.', status: 'need', planned: null, proposal: null, dependsOnTaskId: 'ga-demo-bw-dach',
      },
      {
        id: 'rn-holz-helfer', orderId: 'ao-bar-west', type: 'helper', label: 'Holzbau', durationH: 4,
        earliest: anlassAt(4, 8), latest: anlassAt(4, 12), wish: slot(4, 8, 0, 12), flexible: false, qty: 4,
        note: 'Siehe Helferpool.', status: 'need', planned: null, proposal: null,
      },
      {
        id: 'rn-pagode-sprinter', orderId: 'ao-pagode', type: 'vehicle', label: 'Sprinter', durationH: 2,
        earliest: anlassAt(2, 8), latest: anlassAt(2, 14), wish: null, flexible: true, qty: 1,
        note: 'Palette PK-0041 zum Eingang.', status: 'need', planned: null, proposal: null,
      },
      {
        id: 'rn-zelt-stapler', orderId: 'ao-crew-zelt', type: 'forklift', label: 'Stapler', durationH: 2,
        earliest: anlassAt(4, 8), latest: anlassAt(4, 12), wish: null, flexible: true, qty: 1,
        note: 'Bodenplatten abladen.', status: 'planned', planned: slot(4, 8, 0, 10), proposal: null,
      },
    ],
  }
}

const state = reactive<State>(build())

export function resetGaRessourcenDemo(): void {
  const fresh = build()
  state.needs = fresh.needs
  state.nextId = fresh.nextId
}

export function useGaRessourcenMock() {
  return { needs: computed(() => state.needs) }
}

export function needsOfOrder(orderId: string): GaResourceNeed[] {
  return state.needs.filter((row) => row.orderId === orderId)
}
export function needById(id: string): GaResourceNeed | undefined {
  return state.needs.find((row) => row.id === id)
}

// ── Prüfungen (keine Optimierung) ──────────────────────────────

const overlaps = (a: GaTimeSlot, b: GaTimeSlot) => a.from < b.to && b.from < a.to

export type GaSlotIssue = 'outsideWindow' | 'tooShort' | 'conflict' | 'beforeDependency'

/** Prüft ein gewünschtes Zeitfenster gegen das mögliche Fenster, die Dauer und bekannte Belegungen. */
export function slotIssues(need: GaResourceNeed, slotToCheck: GaTimeSlot, dependencyEnd?: Date | null): Array<{ issue: GaSlotIssue; where?: string }> {
  const out: Array<{ issue: GaSlotIssue; where?: string }> = []
  if (slotToCheck.from < need.earliest || slotToCheck.to > need.latest) out.push({ issue: 'outsideWindow' })
  if ((slotToCheck.to.getTime() - slotToCheck.from.getTime()) / 3_600_000 < need.durationH) out.push({ issue: 'tooShort' })
  if (dependencyEnd && slotToCheck.from < dependencyEnd) out.push({ issue: 'beforeDependency' })
  for (const booking of GA_RESOURCE_BOOKINGS) {
    if (booking.label === need.label && overlaps(booking.slot, slotToCheck)) out.push({ issue: 'conflict', where: booking.where })
  }
  for (const other of state.needs) {
    if (other.id !== need.id && other.label === need.label && other.planned && overlaps(other.planned, slotToCheck)) {
      out.push({ issue: 'conflict', where: other.orderId })
    }
  }
  return out
}

export function acceptProposal(needId: string): boolean {
  const need = needById(needId)
  if (!need?.proposal || need.status !== 'proposal') return false
  need.planned = { from: need.proposal.from, to: need.proposal.to }
  need.proposal = null
  need.status = 'planned'
  return true
}

/** «Andere Zeit»: gewähltes Fenster übernehmen, wenn es im möglichen Fenster liegt und lang genug ist. */
export function planAtOtherTime(needId: string, from: Date, dependencyEnd?: Date | null): { ok: boolean; issues: Array<{ issue: GaSlotIssue; where?: string }> } {
  const need = needById(needId)
  if (!need) return { ok: false, issues: [] }
  const to = new Date(from.getTime() + need.durationH * 3_600_000)
  const issues = slotIssues(need, { from, to }, dependencyEnd)
  if (issues.length) return { ok: false, issues }
  need.planned = { from, to }
  need.proposal = null
  need.status = 'planned'
  return { ok: true, issues: [] }
}

export function leaveOpen(needId: string): boolean {
  const need = needById(needId)
  if (!need || need.status === 'need') return false
  need.planned = null
  need.proposal = null
  need.status = 'need'
  return true
}

export type GaResourceDraft = Omit<GaResourceNeed, 'id' | 'orderId' | 'status' | 'planned' | 'proposal'>

export function addNeed(orderId: string, draft: GaResourceDraft): GaResourceNeed {
  const need: GaResourceNeed = { ...draft, id: `rn-${state.nextId}`, orderId, status: 'need', planned: null, proposal: null }
  state.nextId += 1
  state.needs.push(need)
  return need
}

export function emptyResourceDraft(from: Date, to: Date): GaResourceDraft {
  return { type: 'machine', label: '', durationH: 2, earliest: from, latest: to, wish: null, flexible: true, qty: 1, note: '' }
}

export function statusCountsFor(orderId: string): Record<GaResourceStatus, number> {
  const counts: Record<GaResourceStatus, number> = { need: 0, proposal: 0, planned: 0 }
  for (const need of needsOfOrder(orderId)) counts[need.status] += 1
  return counts
}
