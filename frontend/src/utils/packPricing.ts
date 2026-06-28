/**
 * Stückpreis aus Verkaufspreis pro Verpackungseinheit (CHF/VE ÷ Stück pro VE).
 */
export function unitPriceFromPackSaleChf(packSaleChf: number, piecesPerPack: number): number | null {
  if (!Number.isFinite(packSaleChf) || !Number.isFinite(piecesPerPack)) return null
  if (piecesPerPack < 2 || packSaleChf <= 0) return null
  return Math.round((packSaleChf / piecesPerPack) * 100) / 100
}

/** Verkaufspreis pro VE aus Stückpreis (Stück × Stück/VE). */
export function packSalePriceFromUnitChf(unitSaleChf: number, piecesPerPack: number): number | null {
  if (!Number.isFinite(unitSaleChf) || !Number.isFinite(piecesPerPack)) return null
  if (piecesPerPack < 2 || unitSaleChf <= 0) return null
  return Math.round(unitSaleChf * piecesPerPack * 100) / 100
}
