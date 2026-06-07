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
