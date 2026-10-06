import { describe, expect, it } from 'vitest'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import {
  inboundState,
  inboundStatus,
  missingQty,
  originBadgeKey,
  receivedQty,
} from '@/views/grossanlass/gaCharge'

function charge(patch: Partial<GrossanlassCommitment> = {}): GrossanlassCommitment {
  return {
    id: 'zs0000000001',
    inquiry_id: null,
    name: 'Bauholz',
    family: 'material',
    origin: 'buy',
    source: 'Holz AG',
    plate: null,
    barcode: null,
    category_id: null,
    released: true,
    present_from: null,
    present_to: null,
    handover_from: null,
    handover_to: null,
    return_from: null,
    return_to: null,
    wish_label: null,
    wish_from: null,
    wish_to: null,
    services: [],
    quantity: 100,
    item_details: {},
    created_at: '2026-10-05T10:00:00+02:00',
    updated_at: '2026-10-05T10:00:00+02:00',
    ...patch,
  }
}

describe('gaCharge inbound', () => {
  it('uses server quantities for a partial receipt', () => {
    const row = charge({ received_quantity: 90, missing_quantity: 10, inbound_state: 'partial' })

    expect(receivedQty(row)).toBe(90)
    expect(missingQty(row)).toBe(10)
    expect(inboundState(row)).toBe('partial')
    expect(inboundStatus(row)).toBe('expected')
  })

  it('treats a complete receipt as here', () => {
    const row = charge({ received_quantity: 100, missing_quantity: 0, inbound_state: 'complete' })

    expect(inboundStatus(row)).toBe('here')
  })

  it('falls back to the legacy flag when the server sends no quantities', () => {
    const row = charge({ item_details: { inbound_status: 'here' } })

    expect(inboundState(row)).toBe('complete')
    expect(receivedQty(row)).toBe(100)
    expect(missingQty(row)).toBe(0)
  })

  it('maps new origins to their own badges', () => {
    expect(originBadgeKey('own')).toBe('own')
    expect(originBadgeKey('donation')).toBe('donation')
    expect(originBadgeKey('loan')).toBe('loan')
  })
})

describe('gaCharge origin labels', () => {
  it('labels buy as Kauf and own as GA-Bestand', async () => {
    const { originLabelKey } = await import('@/views/grossanlass/gaCharge')

    expect(originLabelKey('buy')).toBe('grossanlass.materials.originBadge.buy')
    expect(originLabelKey('own')).toBe('grossanlass.materials.lifecycle.own')
    expect(originLabelKey('loan')).toBe('grossanlass.materials.lifecycle.loan')
  })
})
