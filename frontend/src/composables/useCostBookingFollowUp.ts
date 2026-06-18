import { useAuthStore } from '@/stores/auth'
import {
  createAcquisitionFollowup,
  uploadAcquisitionFollowupReceipt,
} from '@/api/accountingAcquisitionFollowups'

/** Rollen mit Zugriff auf Buchhaltung (Router meta matwart/depchef → API-Kürzel mw/dc). */
const ACCOUNTING_ROLES = new Set(['mw', 'dc', 'matwart', 'depchef'])

export function departmentHasAccountingRole(departmentId: string): boolean {
  const auth = useAuthStore()
  const d = auth.departments.find((x) => x.department_id === departmentId)
  const role = (d?.role || '').toLowerCase()
  return ACCOUNTING_ROLES.has(role)
}

export type CostBookingFollowUpInput = {
  departmentId: string
  /** Gesamtbetrag Anschaffung (CHF), z. B. Stückpreis × Menge */
  totalChf: number
  purchaseDateIso?: string
  /** z. B. Materialname für Belegzeile */
  receiptHint?: string
  /** Optional: verknüpft mit Material-Batch in der DB */
  materialBatchId?: string | null
  /** Optional: Material aus Erstell-Wizard / Charge */
  materialItemId?: string | null
  /** Optional: Rechnung/Beleg — wird am Anschaffungs-Auftrag gespeichert und bei Erfassung in die Buchung übernommen */
  receiptFile?: File | null
}

/**
 * Nach Material/Batch-Erfassung: ausstehenden Anschaffungs-Auftrag in der DB anlegen (Status pending).
 * Nur für mw/dc; ohne positiven Betrag keine Aktion.
 * @returns true bei Erfolg
 */
export async function enqueuePendingCostBookingAfterPurchase(
  input: CostBookingFollowUpInput
): Promise<boolean> {
  const { departmentId, totalChf, purchaseDateIso, receiptHint, materialBatchId, materialItemId, receiptFile } = input
  if (!departmentId || !Number.isFinite(totalChf) || totalChf <= 0) {
    return false
  }
  if (!departmentHasAccountingRole(departmentId)) {
    return false
  }

  const amountStr = totalChf.toFixed(2)
  const dateStr =
    purchaseDateIso && /^\d{4}-\d{2}-\d{2}$/.test(purchaseDateIso.trim())
      ? purchaseDateIso.trim()
      : new Date().toISOString().slice(0, 10)

  try {
    const followUp = await createAcquisitionFollowup(departmentId, {
      amount: amountStr,
      suggested_date: dateStr,
      receipt_label: receiptHint?.trim() ? receiptHint.trim().slice(0, 255) : null,
      material_batch_id: materialBatchId || null,
      material_item_id: materialItemId || null,
    })
    if (receiptFile) {
      await uploadAcquisitionFollowupReceipt(departmentId, followUp.id, receiptFile)
    }
    return true
  } catch {
    return false
  }
}
