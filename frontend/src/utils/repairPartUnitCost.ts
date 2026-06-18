import type { Material } from '@/api/materials'

function parseMaterialChf(value: string | number | null | undefined): number {
  const n = Number(String(value ?? '').replace(',', '.'))
  return Number.isFinite(n) && n > 0 ? n : 0
}

/**
 * EK pro Verbrauchseinheit für Reparatur-Stückliste (Stk, m, …).
 *
 * Primär: reference_purchase_unit_chf am Material — dort gehört der bereits
 * aufgeteilte EK hin (z. B. Rollenpreis ÷ 500 m = CHF/m).
 * Fallback: Durchschnitt der Chargen-EKs (unit_price), falls Referenz leer.
 */
export function resolveRepairPartUnitCost(material: Material): string | null {
  const reference = parseMaterialChf(material.reference_purchase_unit_chf)
  if (reference > 0) return reference.toFixed(2)

  const batchPrices = (material.batches ?? [])
    .map((batch) => parseMaterialChf(batch.unit_price))
    .filter((price) => price > 0)
  if (batchPrices.length > 0) {
    const average = batchPrices.reduce((sum, price) => sum + price, 0) / batchPrices.length
    return average.toFixed(2)
  }

  return null
}
