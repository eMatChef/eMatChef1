import type { ComposerTranslation } from 'vue-i18n'

export type ReturnCrateLineMetaCounts = {
  ordered: number
  consumed: number
  loss: number
  repair: number
}

type MetaTranslate = (key: string, values?: Record<string, unknown>) => string

/** Meta-Teile für Retour-Zeile — 0-Werte ausblenden, Artikel bleibt immer sichtbar. */
export function formatReturnCrateLineMeta(
  counts: ReturnCrateLineMetaCounts,
  t: MetaTranslate | ComposerTranslation,
): string {
  const tr = t as MetaTranslate
  const parts: string[] = []
  if (counts.ordered > 0) {
    parts.push(tr('activities.packList.returnCrateModalMetaOrdered', { n: counts.ordered }))
  }
  if (counts.consumed > 0) {
    parts.push(tr('activities.packList.returnCrateModalMetaConsumed', { n: counts.consumed }))
  }
  if (counts.repair > 0) {
    parts.push(tr('activities.packList.returnCrateModalMetaRepair', { n: counts.repair }))
  }
  if (counts.loss > 0) {
    parts.push(tr('activities.packList.returnCrateModalMetaLoss', { n: counts.loss }))
  }
  return parts.join(' · ')
}

export function returnCrateLineMissingQty(included: boolean, max: number, qty: number): number {
  if (!included) return 0
  return Math.max(0, max - qty)
}

/** Übermenge: gezählte Retour über erwartetem Max. */
export function returnCrateLineSurplusQty(included: boolean, max: number, qty: number): number {
  if (!included) return 0
  return Math.max(0, qty - max)
}

/** Soft-Cap für Retour-Input (Tippfehler), erlaubt Übermenge über max. */
export function returnCrateLineInputCap(ordered: number, max: number): number {
  const base = Math.max(0, ordered, max)
  return Math.max(base * 2, base + 50, max + 50)
}

