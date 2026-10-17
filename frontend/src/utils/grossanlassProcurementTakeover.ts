import type { GrossanlassProcurementPoolWish } from '@/api/grossanlassProcurement'

export interface QuantityConfirmationInfo {
  current_quantity: number
  source_quantity: number
  added_quantity: number
  frozen: boolean
  /** null bei eingefrorener Position: die Menge bleibt */
  proposed_quantity: number | null
}

/** 409 vom Server: manuell festgelegte oder eingefrorene Menge braucht eine bewusste Bestätigung. */
export function quantityConfirmationFromError(error: unknown): QuantityConfirmationInfo | null {
  const response = (error as { response?: { status?: number; data?: Record<string, unknown> } })?.response
  if (response?.status !== 409 || response.data?.code !== 'quantity_confirmation_required') return null
  return response.data as unknown as QuantityConfirmationInfo
}

/** Zentral übernehmbar ist nur, was eindeutig der zentralen Beschaffung zugeordnet ist. */
export function wishesNotReadyForCentral(wishes: GrossanlassProcurementPoolWish[]): GrossanlassProcurementPoolWish[] {
  return wishes.filter((wish) => wish.procurement_responsibility !== 'central')
}
