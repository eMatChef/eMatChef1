import type { WorkshopTicket } from '@/api/workshop'
import type { RepairPartLine } from '@/types/repairPartsList'

export function getStockPartsForCompletion(ticket: WorkshopTicket): RepairPartLine[] {
  const lines = ticket.parts_used ?? []
  return lines.filter(
    (line) => ['stock', 'rest'].includes(line.source) && line.status !== 'consumed',
  )
}

export function getReceivedPurchasePartsForCompletion(ticket: WorkshopTicket): RepairPartLine[] {
  const lines = ticket.parts_used ?? []
  return lines.filter(
    (line) => line.source === 'purchase' && line.status === 'received',
  )
}

export function getCompletionPartsLines(ticket: WorkshopTicket): RepairPartLine[] {
  return [
    ...getStockPartsForCompletion(ticket),
    ...getReceivedPurchasePartsForCompletion(ticket),
  ]
}

export function estimatePartsMaterialCost(lines: RepairPartLine[]): number {
  return lines.reduce((sum, line) => {
    const unit = Number(line.unit_cost ?? 0)
    if (!Number.isFinite(unit)) return sum
    return sum + line.quantity * unit
  }, 0)
}

export function formatChfAmount(amount: number): string {
  return amount.toFixed(2)
}

function formatQtyValue(value: number): string {
  if (!Number.isFinite(value)) return '0'
  return Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/\.?0+$/, '')
}

/** Verbrauch mit Einheit: «30 m», «1×» bei Stück. */
export function formatRepairPartQuantity(line: RepairPartLine): string {
  const unit = (line.quantity_unit || 'Stk').trim()
  const qty = formatQtyValue(line.quantity)
  if (!unit || unit.toLowerCase() === 'stk') return `${qty}×`
  return `${qty} ${unit}`
}
