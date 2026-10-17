import { describe, expect, it } from 'vitest'
import type { GrossanlassProcurementPoolWish } from '@/api/grossanlassProcurement'
import { quantityConfirmationFromError, wishesNotReadyForCentral } from './grossanlassProcurementTakeover'

const wish = (id: string, r: GrossanlassProcurementPoolWish['procurement_responsibility']) =>
  ({ id, procurement_responsibility: r }) as GrossanlassProcurementPoolWish

describe('grossanlassProcurementTakeover', () => {
  it('flags undecided and department wishes as not ready for central takeover', () => {
    const notReady = wishesNotReadyForCentral([wish('a', 'central'), wish('b', null), wish('c', 'department'), wish('d', undefined)])
    expect(notReady.map((w) => w.id)).toEqual(['b', 'c', 'd'])
  })

  it('recognises only the quantity confirmation conflict', () => {
    const conflict = {
      response: { status: 409, data: { code: 'quantity_confirmation_required', current_quantity: 12, source_quantity: 10, added_quantity: 2, frozen: false } },
    }
    expect(quantityConfirmationFromError(conflict)?.current_quantity).toBe(12)
    expect(quantityConfirmationFromError({ response: { status: 409, data: { code: 'other' } } })).toBeNull()
    expect(quantityConfirmationFromError({ response: { status: 400, data: {} } })).toBeNull()
    expect(quantityConfirmationFromError(new Error('x'))).toBeNull()
  })
})
