/** Verpackungseinheiten (Lager) — nicht Stk/m/m² als Bestandseinheit */
export const PACKAGING_UNIT_VALUES = [
  'Bündel',
  'Kiste',
  'Karton',
  'Sack',
  'Rolle',
  'Palette',
  'Set',
  'Paket',
] as const

/** Legacy-Schreibweise ohne Umlaut (Import/API) */
const PACKAGING_UNIT_LEGACY_ALIASES = ['Bund'] as const

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
  if (!raw) return false
  if (PACKAGING_UNIT_VALUES.includes(raw as PackagingUnit)) return true
  return (PACKAGING_UNIT_LEGACY_ALIASES as readonly string[]).includes(raw)
}

/** Stk. mit pack_size = Inhalt pro Stück (z. B. 500 m Garn pro Rolle) — nicht Verpackung (Bündel, Kiste …). */
export function hasContentPerPiece(
  packUnit: string | null | undefined,
  packSize: number | null | undefined,
): boolean {
  if (!packSize || packSize < 2) return false
  const raw = (packUnit || '').trim()
  if (isPackagingUnit(raw)) return false
  if (raw !== '' && raw !== 'Stk') return false
  return true
}

export function formatStockQty(
  qty: number,
  packUnit?: string | null,
  packSize?: number | null,
  sizeLengthCm?: string | number | null,
  formatPiecesAtLength?: (count: number, per: string, total: string) => string,
  materialName?: string | null,
): string {
  const n = Number(qty)
  if (!Number.isFinite(n)) return '—'
  if (isMeterStockUnit(packUnit)) {
    const parts = getMeterStockQtyParts(n, sizeLengthCm, materialName)
    if (parts && formatPiecesAtLength) {
      return formatPiecesAtLength(
        parts.count,
        formatMetersForDisplay(parts.perM),
        Number.isInteger(parts.totalM) ? String(parts.totalM) : parts.totalM.toFixed(2),
      )
    }
    const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2)
    return `${formatted} m`
  }
  const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2)
  const unit = getStockUnitLabel(packUnit)
  return `${formatted} ${unit}`
}

/** Länge (cm) aus Material-Details als positive Zahl. */
export function parseSizeLengthCm(value: string | number | null | undefined): number | null {
  if (value == null || value === '') return null
  const n = Number(String(value).replace(',', '.'))
  if (!Number.isFinite(n) || n <= 0) return null
  return n
}

/** cm → m für Anzeige (z. B. 50000 cm → 500 m). */
export function sizeLengthCmToMeters(cm: string | number | null | undefined): number | null {
  const n = parseSizeLengthCm(cm)
  if (n == null) return null
  return n / 100
}

const NAME_LENGTH_SUFFIX_RE = /\((\d+(?:[.,]\d+)?)\s*m\)\s*$/i

/** Fallback: Länge in m aus Namens-Suffix «(5 m)» — wenn API size_length nicht liefert. */
export function parseLengthMetersFromMaterialName(name?: string | null): number | null {
  const match = (name || '').trim().match(NAME_LENGTH_SUFFIX_RE)
  if (!match) return null
  const n = Number(match[1].replace(',', '.'))
  if (!Number.isFinite(n) || n <= 0) return null
  return n
}

/** size_length (cm) mit Fallback aus Materialname. */
export function resolveMaterialSizeLengthCm(
  sizeLengthCm?: string | number | null,
  materialName?: string | null,
): number | null {
  const fromField = parseSizeLengthCm(sizeLengthCm)
  if (fromField != null) return fromField
  const meters = parseLengthMetersFromMaterialName(materialName)
  if (meters == null) return null
  return Math.round(meters * 100)
}

export function formatMetersForDisplay(meters: number): string {
  const rounded = Math.round(meters * 100) / 100
  return Number.isInteger(rounded) ? String(rounded) : String(rounded).replace(/\.?0+$/, '')
}

/** Gesamt-m → Anzahl ganzer Stücke à Standardlänge (für Anzeige). */
export function getMeterStockQtyParts(
  qty: number,
  sizeLengthCm?: string | number | null,
  materialName?: string | null,
): { count: number; perM: number; totalM: number } | null {
  const perM = sizeLengthCmToMeters(resolveMaterialSizeLengthCm(sizeLengthCm, materialName))
  if (perM == null || perM <= 0) return null
  const totalM = Number(qty)
  if (!Number.isFinite(totalM) || totalM <= 0) return null
  const count = Math.round(totalM / perM)
  if (count < 1) return null
  return { count, perM, totalM }
}

export function canDisplayMeterStockAsPieces(
  packUnit?: string | null,
  sizeLengthCm?: string | number | null,
  materialName?: string | null,
): boolean {
  return (
    isMeterStockUnit(packUnit) &&
    sizeLengthCmToMeters(resolveMaterialSizeLengthCm(sizeLengthCm, materialName)) != null
  )
}

/** Kurz-Suffix für Anzeigenamen, z. B. «500 m». */
export function getMaterialUnitSuffix(
  packUnit?: string | null,
  packSize?: number | null,
  sizeLengthCm?: string | number | null,
  materialName?: string | null,
): string | null {
  const kind = getStockUnitKind(packUnit)
  if (kind === 'length') {
    const m = sizeLengthCmToMeters(resolveMaterialSizeLengthCm(sizeLengthCm, materialName))
    if (m != null) return `${formatMetersForDisplay(m)} m`
    return null
  }
  if (kind === 'area') return 'm²'
  if (hasContentPerPiece(packUnit, packSize)) return `${packSize} m`
  return null
}

/** Nur Einheits-Suffixe am Ende — nicht beliebige Klammern wie «(Netz + 6 Schläger)». */
const UNIT_SUFFIX_RE = /\s*(?:\(\d+(?:[.,]\d+)?\s*m\)|\(m²\))\s*$/iu

/** Entfernt ein bestehendes Einheits-Suffix am Namensende. */
export function stripMaterialUnitSuffix(name: string): string {
  return (name || '').trim().replace(UNIT_SUFFIX_RE, '').trim()
}

/** Anzeigename mit Einheits-Suffix — DB-Name bleibt separat, wird aber oft gleich gehalten. */
export function formatMaterialDisplayName(
  name: string,
  packUnit?: string | null,
  packSize?: number | null,
  sizeLengthCm?: string | number | null,
): string {
  const base = stripMaterialUnitSuffix(name)
  if (!base) return ''
  const suffix = getMaterialUnitSuffix(packUnit, packSize, sizeLengthCm, name)
  if (!suffix) return base
  return `${base} (${suffix})`
}

/** Setzt/ersetzt Einheits-Suffix im Materialnamen (z. B. «Statikseil» → «Statikseil (500 m)»). */
export function applyMaterialUnitSuffixToName(
  name: string,
  packUnit?: string | null,
  packSize?: number | null,
  sizeLengthCm?: string | number | null,
): string {
  return formatMaterialDisplayName(name, packUnit, packSize, sizeLengthCm)
}

export const STOCK_UNIT_OPTIONS = ['Stk', 'm'] as const
export type StockUnitOption = (typeof STOCK_UNIT_OPTIONS)[number]

export function isMeterStockUnit(packUnit?: string | null): boolean {
  return getStockUnitKind(packUnit) === 'length'
}

export function formatStockUnitSettingLabel(
  packUnit?: string | null,
  packSize?: number | null,
): string {
  const suffix = getMaterialUnitSuffix(packUnit, packSize)
  if (suffix) return suffix
  if (isPackagingUnit(packUnit)) return 'Stk.'
  return getStockUnitLabel(packUnit)
}

export function parseMaterialChfInput(value: string | number | null | undefined): number {
  const n = Number(String(value ?? '').replace(/\s/g, '').replace(',', '.'))
  return Number.isFinite(n) && n > 0 ? n : 0
}

/** UI: CHF pro Stück (à lengthM) → Lager/API: CHF pro m */
export function meterUnitPricePerMeterFromPerPiece(
  pricePerPiece: number,
  lengthM: number,
): number | null {
  if (!Number.isFinite(pricePerPiece) || pricePerPiece <= 0) return null
  if (!Number.isFinite(lengthM) || lengthM <= 0) return null
  return Math.round((pricePerPiece / lengthM) * 100) / 100
}

/** Lager/API: CHF pro m → UI: CHF pro Stück (à lengthM) */
export function meterUnitPricePerPieceFromPerMeter(
  pricePerMeter: number,
  lengthM: number,
): number | null {
  if (!Number.isFinite(pricePerMeter) || pricePerMeter <= 0) return null
  if (!Number.isFinite(lengthM) || lengthM <= 0) return null
  return Math.round(pricePerMeter * lengthM * 100) / 100
}

/** Anzeige-/Eingabepreis aus gespeichertem CHF/m (oder unverändert). */
export function displayMeterStockUnitPrice(
  storedUnitPrice: string | number | null | undefined,
  useQtyByCount: boolean,
  lengthM: number | null,
): string {
  const stored = parseMaterialChfInput(storedUnitPrice)
  if (stored <= 0) return ''
  if (useQtyByCount && lengthM != null && lengthM > 0) {
    const perPiece = meterUnitPricePerPieceFromPerMeter(stored, lengthM)
    return perPiece != null ? perPiece.toFixed(2) : stored.toFixed(2)
  }
  return stored.toFixed(2)
}

/** Gespeicherter CHF/m für API aus UI-Eingabe (Stück- oder m-Preis). */
export function resolveStoredMeterStockUnitPrice(
  enteredUnitPrice: string | number | null | undefined,
  useQtyByCount: boolean,
  lengthM: number | null,
): string | null {
  const entered = parseMaterialChfInput(enteredUnitPrice)
  if (entered <= 0) return null
  if (useQtyByCount && lengthM != null && lengthM > 0) {
    const perM = meterUnitPricePerMeterFromPerPiece(entered, lengthM)
    return perM != null ? perM.toFixed(2) : entered.toFixed(2)
  }
  return entered.toFixed(2)
}

export function formatStockQtyWithPackHint(
  qty: number,
  packUnit?: string | null,
  packSize?: number | null,
  contentUnit = 'm',
  sizeLengthCm?: string | number | null,
  formatPiecesAtLength?: (count: number, per: string, total: string) => string,
  materialName?: string | null,
): string {
  const base = formatStockQty(qty, packUnit, packSize, sizeLengthCm, formatPiecesAtLength, materialName)
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
