import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'

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

export function inboundStatus(row: GrossanlassCommitment): GaInboundStatus {
  if (row.item_details?.inbound_status === 'here' || row.packed || row.returned_to_firm) {
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

export function originBadgeKey(origin: GrossanlassCommitment['origin']): string {
  if (origin === 'buy_resale') return 'buy_resale'
  if (origin === 'buy') return 'buy'
  return 'loan'
}
