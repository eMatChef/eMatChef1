/** Verpackungseinheiten (Lager) — nicht Stk/m/m² als Bestandseinheit */
export const PACKAGING_UNIT_VALUES = [
  'Bund',
  'Kiste',
  'Karton',
  'Sack',
  'Rolle',
  'Palette',
  'Set',
  'Paket',
] as const

export type PackagingUnit = (typeof PACKAGING_UNIT_VALUES)[number]

export type StockUnitKind = 'piece' | 'length' | 'area'

export function getStockUnitKind(packUnit: string | null | undefined): StockUnitKind {
  const u = (packUnit || '').trim().toLowerCase()
  if (['m', 'meter', 'metre'].includes(u)) return 'length'
  if (['m2', 'm²', 'qm'].includes(u)) return 'area'
  return 'piece'
}

/** Anzeige-Einheit für Bestandsmengen (Stk., m, m²) */
export function getStockUnitLabel(packUnit: string | null | undefined): string {
  const kind = getStockUnitKind(packUnit)
  if (kind === 'length') return 'm'
  if (kind === 'area') return 'm²'
  return 'Stk.'
}

export function isPackagingUnit(packUnit: string | null | undefined): boolean {
  const raw = (packUnit || '').trim()
  return PACKAGING_UNIT_VALUES.includes(raw as PackagingUnit)
}

/** Stk. mit pack_size = Inhalt pro Stück (z. B. 500 m Garn pro Rolle) */
export function hasContentPerPiece(
  packUnit: string | null | undefined,
  packSize: number | null | undefined,
): boolean {
  if (!packSize || packSize < 2) return false
  return getStockUnitKind(packUnit) === 'piece' && !isPackagingUnit(packUnit)
}

export function formatStockQty(
  qty: number,
  packUnit?: string | null,
  packSize?: number | null,
): string {
  const n = Number(qty)
  if (!Number.isFinite(n)) return '—'
  const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2)
  const unit = getStockUnitLabel(packUnit)
  return `${formatted} ${unit}`
}

export function formatStockQtyWithPackHint(
  qty: number,
  packUnit?: string | null,
  packSize?: number | null,
  contentUnit = 'm',
): string {
  const base = formatStockQty(qty, packUnit, packSize)
  if (hasContentPerPiece(packUnit, packSize) && qty > 0) {
    const totalContent = qty * (packSize as number)
    return `${base} (${qty} × ${packSize} ${contentUnit})`
  }
  if (isPackagingUnit(packUnit) && packSize && packSize >= 2 && qty > 0) {
    const packs = Math.floor(qty / packSize)
    const rem = qty % packSize
    if (packs > 0) {
      const packPart = `${packs} ${packUnit}`
      return rem > 0 ? `${base} ≈ ${packPart} + ${rem} Stk.` : `${base} = ${packPart}`
    }
  }
  return base
}
