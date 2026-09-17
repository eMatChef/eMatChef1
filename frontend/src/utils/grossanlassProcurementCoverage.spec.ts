import { describe, expect, it } from 'vitest'
import type { GrossanlassProcurementLine } from '@/api/grossanlassProcurement'
import {
  procurementCoverageOpen,
  procurementOrderedQty,
} from '@/utils/grossanlassProcurementCoverage'

function line(partial: Partial<GrossanlassProcurementLine>): GrossanlassProcurementLine {
  return {
    id: 'l1',
    department_id: 'd1',
    group_id: 'g1',
    group_name: 'G',
    wish_kind: 'material',
    label: 'Wasserschlauch 32mm',
    quantity: 415,
    location: '',
    notes: null,
    category_id: null,
    category_name: null,
    category_parent_id: null,
    category_parent_name: null,
    status: 'bedarf',
    quantity_asked: 415,
    quantity_current: 415,
    quantity_delta: 0,
    merge_frozen: false,
    wish_line_ids: [],
    wish_count: 0,
    source_wishes: [],
    source_quantity_sum: 415,
    received_quantity_sum: 0,
    quotes: [],
    selected_quote_id: null,
    budget_chf: null,
    order: null,
    need_from: null,
    need_to: null,
    created_at: '',
    updated_at: '',
    ...partial,
  }
}

describe('grossanlassProcurementCoverage', () => {
  it('counts the full demand as ordered, not the remainder after loans', () => {
    const hose = line({
      status: 'bestellt',
      quantity_loaned: 150,
      quantity_ordered: 265,
      order: {
        id: 'o1',
        procurement_line_id: 'l1',
        ordered_at: '',
        delivery_at: null,
        cost_chf: 450,
        order_ref: null,
        notes: null,
        created_at: '',
        updated_at: '',
      },
    })
    expect(procurementOrderedQty(hose)).toBe(415)
    expect(procurementCoverageOpen(hose, 0, 150)).toBe(0)
  })

  it('falls back to the line quantity when the line is ordered without a charge qty', () => {
    const hose = line({ status: 'bestellt', quantity_loaned: 150 })
    expect(procurementOrderedQty(hose)).toBe(415)
    expect(procurementCoverageOpen(hose, 150, 0)).toBe(0)
  })

  it('does not treat a selected quote as ordered', () => {
    const hose = line({
      status: 'budgetiert',
      quantity_ordered: 265,
      selected_quote_id: 'q1',
    })
    expect(procurementOrderedQty(hose)).toBe(0)
    expect(procurementCoverageOpen(hose, 0, 150)).toBe(265)
  })

  it('does not count a buy charge while the line is still only a quote', () => {
    const hose = line({
      status: 'offerte_eingeholt',
      quantity_ordered: 265,
      order: {
        id: 'o1',
        procurement_line_id: 'l1',
        ordered_at: '',
        delivery_at: null,
        cost_chf: 450,
        order_ref: null,
        notes: null,
        created_at: '',
        updated_at: '',
      },
    })
    expect(procurementOrderedQty(hose)).toBe(0)
  })
})
