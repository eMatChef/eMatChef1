import type { ActivityIssueReportRow } from '@/api/activities'

/** Gebuchte Meldungen «nicht mitgenommen» (Kistencheck / Ausgabe). */
export function notTakenToEventQtyForMaterial(
  materialItemId: string,
  issues: ActivityIssueReportRow[],
): number {
  return issues
    .filter((i) => i.type === 'not_taken' && i.material_item_id === materialItemId)
    .reduce((s, i) => s + (i.quantity ?? 0), 0)
}

/** Gebuchter Verbrauch (activity_issues, Typ consumption). */
export function consumedQtyForMaterial(
  materialItemId: string,
  issues: ActivityIssueReportRow[],
): number {
  return issues
    .filter((i) => i.type === 'consumption' && i.material_item_id === materialItemId)
    .reduce((s, i) => s + (i.quantity ?? 0), 0)
}

/** Gebuchter Verlust (activity_issues, Typ loss). */
export function lossQtyForMaterial(
  materialItemId: string,
  issues: ActivityIssueReportRow[],
): number {
  return issues
    .filter((i) => i.type === 'loss' && i.material_item_id === materialItemId)
    .reduce((s, i) => s + (i.quantity ?? 0), 0)
}

/** Gebuchte Reparatur (activity_issues, Typ repair — Material noch ausserhalb Lager). */
export function repairQtyForMaterial(
  materialItemId: string,
  issues: ActivityIssueReportRow[],
): number {
  return issues
    .filter((i) => i.type === 'repair' && i.material_item_id === materialItemId)
    .reduce((s, i) => s + (i.quantity ?? 0), 0)
}

/** Anteil auf eine Ausgabezeile (Pack- oder Kistenzeile) verteilen — z. B. Verbrauch / nicht mitgenommen. */
export function qtyAttributedToIssuedLine(
  lineIssued: number,
  totalIssuedAtEvent: number,
  totalQty: number,
): number {
  if (lineIssued <= 0 || totalIssuedAtEvent <= 0 || totalQty <= 0) return 0
  return Math.min(lineIssued, Math.round((totalQty * lineIssued) / totalIssuedAtEvent))
}

/** @deprecated Alias — gleiche Verteilungslogik */
export function notTakenAttributedToIssuedLine(
  lineIssued: number,
  totalIssuedAtEvent: number,
  totalNotTakenToEvent: number,
): number {
  return qtyAttributedToIssuedLine(lineIssued, totalIssuedAtEvent, totalNotTakenToEvent)
}

/** Erwartete Retour beim Einlagern: ausgegeben − nicht mitgenommen − Verbrauch. */
export function expectedReturnAfterNotTaken(
  issued: number,
  lineIssued: number,
  totalIssuedAtEvent: number,
  totalNotTakenToEvent: number,
  consumed = 0,
): number {
  if (issued <= 0) return 0
  const notTaken = qtyAttributedToIssuedLine(lineIssued, totalIssuedAtEvent, totalNotTakenToEvent)
  const lineConsumed = qtyAttributedToIssuedLine(lineIssued, totalIssuedAtEvent, consumed)
  return Math.max(0, issued - notTaken - lineConsumed)
}

export type PackRetourAccounting = {
  /** Aus dem Lager gepackt */
  packed: number
  /** Nachkauf / Dazukauf (nicht aus Lager) */
  replenishment: number
  /** Tatsächlich ans Event ausgegeben (`quantity_issued`) */
  issued: number
  /** Gepackt, aber nie ans Event bewegt — noch aus dem Lager unterwegs */
  neverIssued: number
  /** Ans Event ausgegeben, aber als «nicht mitgenommen» gemeldet */
  notTaken: number
  consumed: number
  loss: number
  repair: number
  /** Gebucht retourniert (`quantity_returned`) */
  returnedBooked: number
  /** Noch zurück ins Lager (Retour gesamt) */
  retourTotal: number
  /** Erwartete Retour vom Event (einlagern / retourniert buchen) */
  expectedReturn: number
}

/**
 * Bilanz: (Lager gepackt + Dazukauf) = Retour gesamt + Verbrauch + Verlust + Reparatur.
 * `quantity_packed` am Pack-Item enthält bei Verbrauch oft bereits Lager + Nachlieferung —
 * Dazukauf dann nur zur Anzeige, nicht nochmals aufaddieren.
 */
export function packRetourAccountingSnapshot(params: {
  quantityPacked: number
  /** Summe activity_item (Lager + Nachlieferung); falls grösser als quantity_packed. */
  quantityOrdered?: number
  quantityIssued: number
  returned: number
  neverIssuedLoose: number
  notTakenFromIssues: number
  consumed: number
  loss?: number
  repair?: number
  replenishment?: number
}): PackRetourAccounting {
  const {
    quantityPacked,
    quantityOrdered,
    quantityIssued,
    returned,
    neverIssuedLoose,
    notTakenFromIssues,
    consumed: consumedRaw,
    loss: lossRaw = 0,
    repair: repairRaw = 0,
    replenishment = 0,
  } = params
  const issued = Math.max(0, quantityIssued)
  const neverIssued = Math.max(0, neverIssuedLoose)
  const notTaken = Math.max(0, notTakenFromIssues)
  const packedTotal = Math.max(0, quantityPacked)
  const orderedTotal = Math.max(packedTotal, quantityOrdered ?? 0)
  const replenishmentQty = Math.max(0, replenishment)
  const outOfWarehouse = Math.max(packedTotal, orderedTotal)
  const packedFromWarehouse =
    replenishmentQty > 0 ? Math.max(0, outOfWarehouse - replenishmentQty) : outOfWarehouse
  const consumed = Math.max(0, Math.min(consumedRaw, outOfWarehouse))
  const loss = Math.max(0, Math.min(lossRaw, outOfWarehouse))
  const repair = Math.max(0, Math.min(repairRaw, outOfWarehouse))
  const returnedBooked = Math.max(0, returned)
  const retourTotal = Math.max(0, outOfWarehouse - consumed - loss - repair)
  const expectedReturn = Math.max(0, issued - notTaken - consumed - loss - repair)
  return {
    packed: packedFromWarehouse,
    replenishment: replenishmentQty,
    issued,
    neverIssued,
    notTaken,
    consumed,
    loss,
    repair,
    returnedBooked,
    retourTotal,
    expectedReturn,
  }
}
