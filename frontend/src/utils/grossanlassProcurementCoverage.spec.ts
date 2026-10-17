import { describe, expect, it } from 'vitest'
import type { GrossanlassProcurementLine } from '@/api/grossanlassProcurement'
import {
  procurementCoverageOpen,
  procurementCoveredQty,
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
    source: 'from_wish',
    self_organized: false,
    created_by_user_id: null,
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

const order = {
  id: 'o1',
  procurement_line_id: 'l1',
  ordered_at: '',
  delivery_at: null,
  cost_chf: 450,
  order_ref: null,
  notes: null,
  created_at: '',
  updated_at: '',
}

describe('grossanlassProcurementCoverage', () => {
  it('adds ordered and loaned quantities instead of letting an order cover the full demand', () => {
    const hose = line({
      status: 'bestellt',
      quantity_loaned: 150,
      quantity_ordered: 265,
      order,
    })
    expect(procurementOrderedQty(hose)).toBe(265)
    expect(procurementCoveredQty(hose, 150, 0)).toBe(415)
    expect(procurementCoverageOpen(hose, 150, 0)).toBe(0)
  })

  it('shows an over-coverage as a negative remainder when a purchase repeats the loaned quantity', () => {
    const hose = line({ status: 'bestellt', quantity_loaned: 150, quantity_ordered: 415, order })
    expect(procurementCoverageOpen(hose, 150, 0)).toBe(-150)
  })

  it('covers the remainder across several partial orders', () => {
    const hose = line({ status: 'teilweise_erhalten', quantity_ordered: 300 })
    expect(procurementOrderedQty(hose)).toBe(300)
    expect(procurementCoverageOpen(hose, 0, 0)).toBe(115)
  })

  it('does not count a quote as cover: only confirmed orders and commitments do', () => {
    const hose = line({
      status: 'budgetiert',
      quantity_ordered: 0,
      selected_quote_id: 'q1',
    })
    expect(procurementOrderedQty(hose)).toBe(0)
    expect(procurementCoverageOpen(hose, 0, 150)).toBe(265)
  })

  it('falls back to the line quantity for older payloads without a charge quantity', () => {
    const hose = line({ status: 'bestellt', quantity_loaned: 150 })
    expect(procurementOrderedQty(hose)).toBe(415)
  })

  it('does not treat an unordered line without charge quantity as ordered', () => {
    expect(procurementOrderedQty(line({ status: 'offerte_eingeholt' }))).toBe(0)
  })
})
