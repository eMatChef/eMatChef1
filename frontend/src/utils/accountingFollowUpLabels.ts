import type { AccountingAcquisitionFollowUp } from '@/api/accountingAcquisitionFollowups'

/** Anzeige-Labels für source_kind (Buchhaltung / Kosten-Tab). */
export function accountingFollowUpKindKey(sourceKind: string | null | undefined): string {
  const sk = sourceKind || ''
  const map: Record<string, string> = {
    batch: 'accounting.followUpKind.batch',
    activity_consumption: 'accounting.followUpKind.consumption',
    activity_replenishment: 'accounting.followUpKind.replenishment',
    activity_rental: 'accounting.followUpKind.rental',
    activity_workshop: 'accounting.followUpKind.workshop',
    activity_loss: 'accounting.followUpKind.loss',
    activity_final: 'accounting.followUpKind.final',
  }
  return map[sk] || 'accounting.followUpKind.other'
}

export function sortFollowUpsForDisplay(
  items: AccountingAcquisitionFollowUp[],
): AccountingAcquisitionFollowUp[] {
  const order: Record<string, number> = {
    activity_consumption: 10,
    activity_replenishment: 20,
    activity_rental: 30,
    activity_workshop: 40,
    activity_loss: 50,
    activity_final: 90,
    batch: 100,
  }
  return [...items].sort((a, b) => {
    const oa = order[a.source_kind || ''] ?? 50
    const ob = order[b.source_kind || ''] ?? 50
    if (oa !== ob) return oa - ob
    return (a.receipt_label || '').localeCompare(b.receipt_label || '', 'de')
  })
}
