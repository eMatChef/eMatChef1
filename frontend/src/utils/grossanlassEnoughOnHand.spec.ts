import { describe, expect, it } from 'vitest'
import {
  emptyEnoughOnHand,
  enoughOnHandBadgeFromValue,
  enoughOnHandBadgeLabel,
  enoughOnHandFromApi,
  enoughOnHandToPayload,
  validateEnoughOnHand,
} from '@/utils/grossanlassEnoughOnHand'

const t = (key: string, values?: Record<string, unknown>) => {
  if (key.endsWith('BadgeFrom')) return `Genug vorhanden · ${values?.source}`
  if (key.endsWith('BadgeStock')) return 'Genug vorhanden · Eigenbestand'
  if (key.endsWith('Badge')) return 'Genug vorhanden'
  if (key.endsWith('SourceRequired')) return 'Herkunft'
  if (key.endsWith('ZusageRequired')) return 'Zusage'
  return key
}

describe('grossanlassEnoughOnHand', () => {
  it('requires source when enough is checked', () => {
    expect(validateEnoughOnHand(emptyEnoughOnHand(), t)).toBeNull()
    expect(validateEnoughOnHand({ enough: true, source: null, detail: '', refId: null }, t)).toBe('Herkunft')
    expect(validateEnoughOnHand({ enough: true, source: 'commitment', detail: '', refId: null }, t)).toBe('Zusage')
    expect(validateEnoughOnHand({
      enough: true,
      source: 'commitment',
      detail: 'Kehkehwa · Schlauch',
      refId: 'zs018b85432b',
    }, t)).toBeNull()
    expect(validateEnoughOnHand({ enough: true, source: 'stock', detail: '', refId: null }, t)).toBeNull()
  })

  it('clears source fields when enough is off', () => {
    expect(enoughOnHandToPayload({
      enough: false,
      source: 'stock',
      detail: 'Magazin',
      refId: 'x',
    })).toEqual({
      enough_on_hand: false,
      enough_on_hand_source: null,
      enough_on_hand_detail: null,
      enough_on_hand_ref_id: null,
    })
  })

  it('shows Eigenbestand or partner on the badge', () => {
    expect(enoughOnHandBadgeLabel({ enough_on_hand: false }, t)).toBe('')
    expect(enoughOnHandBadgeLabel({ enough_on_hand: true, enough_on_hand_source: 'stock' }, t))
      .toBe('Genug vorhanden · Eigenbestand')
    expect(enoughOnHandBadgeLabel({
      enough_on_hand: true,
      enough_on_hand_source: 'stock',
      enough_on_hand_detail: 'Magazin Nord',
    }, t)).toBe('Genug vorhanden · Magazin Nord')
    expect(enoughOnHandBadgeLabel({
      enough_on_hand: true,
      enough_on_hand_source: 'commitment',
      enough_on_hand_detail: 'Kehkehwa · Wasserschlauch',
    }, t)).toBe('Genug vorhanden · Kehkehwa · Wasserschlauch')
    expect(enoughOnHandBadgeFromValue(enoughOnHandFromApi({
      enough_on_hand: true,
      enough_on_hand_source: 'commitment',
      enough_on_hand_detail: 'Kehkehwa · Wasserschlauch',
      enough_on_hand_ref_id: 'zs018b85432b',
    }), t)).toBe('Genug vorhanden · Kehkehwa · Wasserschlauch')
  })
})
