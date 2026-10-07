import type { GrossanlassCommitment, GrossanlassInboundState } from '@/api/grossanlassCommitments'

export type GaInboundStatus = 'expected' | 'here'
export type GaInboundMode = 'pickup' | 'delivery'

export function commitmentStemKey(row: GrossanlassCommitment): string {
  const from = row.item_details?.from_line_id?.trim()
  if (from) return `line:${from}`
  return `name:${row.name.trim().toLowerCase()}`
}

export function commitmentsOnStem(
  all: GrossanlassCommitment[],
  row: GrossanlassCommitment,
): GrossanlassCommitment[] {
  const key = commitmentStemKey(row)
  return all.filter((item) => commitmentStemKey(item) === key)
}

/** Eingangsstand aus den Charge-Bewegungen; Fallback für Antworten ohne `inbound_state`. */
export function inboundState(row: GrossanlassCommitment): GrossanlassInboundState {
  if (row.inbound_state) return row.inbound_state
  return row.item_details?.inbound_status === 'here' ? 'complete' : 'none'
}

export function receivedQty(row: GrossanlassCommitment): number {
  if (typeof row.received_quantity === 'number') return row.received_quantity
  return inboundState(row) === 'complete' ? row.quantity : 0
}

export function missingQty(row: GrossanlassCommitment): number {
  if (typeof row.missing_quantity === 'number') return row.missing_quantity
  return Math.max(0, row.quantity - receivedQty(row))
}

/** «here» erst bei vollständigem Eingang (oder Material schon gepackt / an Firma zurück). */
export function inboundStatus(row: GrossanlassCommitment): GaInboundStatus {
  if (inboundState(row) === 'complete' || row.packed || row.returned_to_firm) {
    return 'here'
  }
  return 'expected'
}

export function inboundMode(row: GrossanlassCommitment): GaInboundMode {
  const mode = row.item_details?.inbound_mode
  if (mode === 'pickup' || mode === 'delivery') return mode
  return row.origin === 'loan' ? 'pickup' : 'delivery'
}

export function expectedAtIso(row: GrossanlassCommitment): string | null {
  if (row.origin === 'loan') return row.handover_from || row.present_from
  return row.present_from
}

export type GaChargeFlag = 'ordered' | 'inTransit' | 'expected' | 'here' | 'packed' | 'returned'

export function chargeFlags(row: GrossanlassCommitment): GaChargeFlag[] {
  const flags: GaChargeFlag[] = []
  const status = inboundStatus(row)
  if (status === 'expected') {
    if (row.origin === 'buy' && inboundMode(row) === 'delivery') flags.push('inTransit')
    else if (row.origin === 'buy') flags.push('ordered')
    else flags.push('expected')
  } else {
    flags.push('here')
  }
  if (row.packed) flags.push('packed')
  if (row.returned_to_firm) flags.push('returned')
  return flags
}

/** i18n-Schlüssel für die Herkunft in Auswahl und Listen: Kauf ist «Kauf», nicht «Eigenbestand». */
export function originLabelKey(origin: GrossanlassCommitment['origin'] | string): string {
  if (origin === 'buy') return 'grossanlass.materials.originBadge.buy'
  if (origin === 'buy_resale') return 'grossanlass.materials.lifecycle.buy_resale'
  if (origin === 'own') return 'grossanlass.materials.lifecycle.own'
  if (origin === 'donation') return 'grossanlass.materials.lifecycle.donation'
  return 'grossanlass.materials.lifecycle.loan'
}

export function originBadgeKey(origin: GrossanlassCommitment['origin']): string {
  if (origin === 'own') return 'own'
  if (origin === 'donation') return 'donation'
  if (origin === 'buy_resale') return 'buy_resale'
  if (origin === 'buy') return 'buy'
  return 'loan'
}
