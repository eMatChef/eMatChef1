import type { ActivityIssueReportRow, ActivityItemRow } from '@/api/activities'
import { unitPriceFromPackSaleChf } from '@/utils/packPricing'

export interface ConsumableCostRow {
  material_item_id: string
  material_name: string
  linked_container_label?: string | null
  quantity_booked: number
  quantity_warehouse: number
  quantity_replenishment: number
  sale_price: number | null
}

export interface ReplenishmentPurchaseRow {
  id: string
  material_item_id: string
  material_name: string
  quantity: number
  unit_purchase: number | null
  line_total: number | null
  source_department_id?: string | null
  source_department_name?: string | null
  submitter_department_id?: string | null
  submitter_department_name?: string | null
  created_by_user_id?: string | null
  created_by_display_name?: string | null
  recorded_at?: string | null
}

export interface ReplenishmentDepartmentGroup {
  department_id: string
  department_name: string
  rows: ReplenishmentPurchaseRow[]
  total: number
  submitter_names: string[]
}

export interface RentalCostRow {
  material_item_id: string
  material_name: string
  quantity: number
  unit_price: number | null
  line_total: number | null
}

export function parseMoney(v: string | number | null | undefined): number | null {
  if (v == null || v === '') return null
  const n = typeof v === 'string' ? parseFloat(v) : v
  return Number.isFinite(n) ? n : null
}

/** Normaler Stück-Verkaufspreis: sale_price → pack_sale_price_chf ÷ pack_size. */
function baseConsumableUnitSalePrice(row: {
  sale_price?: string | number | null
  pack_sale_price_chf?: string | number | null
  pack_size?: number | null
}): number | null {
  const direct = parseMoney(row.sale_price)
  if (direct != null) return direct

  const packPrice = parseMoney(row.pack_sale_price_chf)
  const packSize = row.pack_size
  if (packPrice == null || packSize == null || packSize < 1 || packPrice <= 0) return null
  if (packSize === 1) return packPrice
  return unitPriceFromPackSaleChf(packPrice, packSize)
}

/** Intern: normaler Preis. Extern: normaler Preis + optionaler Zusatz (external_sale_price_chf). */
export function effectiveConsumableUnitSalePrice(
  row: {
    sale_price?: string | number | null
    external_sale_price_chf?: string | number | null
    pack_sale_price_chf?: string | number | null
    pack_size?: number | null
  },
  options?: { preferExternal?: boolean },
): number | null {
  const base = baseConsumableUnitSalePrice(row)
  if (!options?.preferExternal) return base

  const extra = parseMoney(row.external_sale_price_chf)
  if (extra == null || extra <= 0) return base
  if (base == null) return extra
  return Math.round((base + extra) * 100) / 100
}

export function formatChf(amount: number): string {
  return amount.toFixed(2)
}

export function formatChfLabel(amount: number | null | undefined): string {
  if (amount == null) return '–'
  return `CHF ${formatChf(amount)}`
}

export function consumableDisplayName(row: {
  material_name: string
  linked_container_label?: string | null
}): string {
  const l = row.linked_container_label?.trim()
  return l ? `${l} — ${row.material_name}` : row.material_name
}

export function aggregateConsumableRows(
  items: ActivityItemRow[],
  options?: { preferExternal?: boolean },
): ConsumableCostRow[] {
  const map = new Map<string, ConsumableCostRow>()
  for (const r of items.filter((x) => x.is_consumable === true)) {
    const salePrice = effectiveConsumableUnitSalePrice(r, options)
    const isReplen = r.is_replenishment === true
    const ex = map.get(r.material_item_id)
    if (ex) {
      ex.quantity_booked += r.quantity
      if (isReplen) ex.quantity_replenishment += r.quantity
      else ex.quantity_warehouse += r.quantity
      if (ex.sale_price == null && salePrice != null) ex.sale_price = salePrice
    } else {
      map.set(r.material_item_id, {
        material_item_id: r.material_item_id,
        material_name: r.material_name,
        linked_container_label: r.linked_container_label,
        quantity_booked: r.quantity,
        quantity_warehouse: isReplen ? 0 : r.quantity,
        quantity_replenishment: isReplen ? r.quantity : 0,
        sale_price: salePrice,
      })
    }
  }
  return [...map.values()]
}

export function replenishmentPurchaseRows(items: ActivityItemRow[]): ReplenishmentPurchaseRow[] {
  return items
    .filter((r) => r.is_consumable === true && r.is_replenishment === true)
    .map((r) => {
      const lineTotal = parseMoney(r.line_total)
      const unitFromLine = parseMoney(r.unit_price)
      const unit =
        unitFromLine ??
        (lineTotal != null && r.quantity > 0 ? lineTotal / r.quantity : null)
      return {
        id: r.id,
        material_item_id: r.material_item_id,
        material_name: r.material_name,
        quantity: r.quantity,
        unit_purchase: unit,
        line_total: lineTotal,
        source_department_id: r.source_department_id ?? null,
        source_department_name: r.source_department_name ?? null,
        submitter_department_id: r.submitter_department_id ?? null,
        submitter_department_name: r.submitter_department_name ?? null,
        created_by_user_id: r.created_by_user_id ?? null,
        created_by_display_name: r.created_by_display_name ?? null,
        recorded_at: r.recorded_at ?? null,
      }
    })
}

/** Nachlieferungen nach einreichendem Department gruppieren (departmentübergreifende Aktivitäten). */
export function replenishmentGroupsBySubmitterDepartment(
  rows: ReplenishmentPurchaseRow[],
): ReplenishmentDepartmentGroup[] {
  const map = new Map<string, ReplenishmentDepartmentGroup>()
  for (const row of rows) {
    const deptId = row.submitter_department_id || '_unknown'
    const deptName = row.submitter_department_name || '–'
    let group = map.get(deptId)
    if (!group) {
      group = {
        department_id: deptId,
        department_name: deptName,
        rows: [],
        total: 0,
        submitter_names: [],
      }
      map.set(deptId, group)
    }
    group.rows.push(row)
    const line = row.line_total ?? (row.unit_purchase ?? 0) * row.quantity
    group.total += line
    const who = row.created_by_display_name?.trim()
    if (who && !group.submitter_names.includes(who)) {
      group.submitter_names.push(who)
    }
  }
  return [...map.values()].sort((a, b) => a.department_name.localeCompare(b.department_name, 'de'))
}

export function consumableUsedQty(materialItemId: string, issues: ActivityIssueReportRow[]): number {
  return issues
    .filter((i) => i.type === 'consumption' && i.material_item_id === materialItemId)
    .reduce((s, i) => s + i.quantity, 0)
}

/** Verrechneter Betrag: Verbrauch aus Lager zu Verkaufspreis, aus Nachkauf zu Einkaufspreis. */
export function consumableChargeableCost(
  materialItemId: string,
  items: ActivityItemRow[],
  issues: ActivityIssueReportRow[],
  options?: { preferExternal?: boolean },
): number {
  const lines = items.filter((i) => i.is_consumable && i.material_item_id === materialItemId)
  if (lines.length === 0) return 0

  const used = consumableUsedQty(materialItemId, issues)
  if (used <= 0) return 0

  let warehouseQty = 0
  let salePrice: number | null = null
  const replenLines: { qty: number; unit: number | null }[] = []

  for (const r of lines) {
    const unit = effectiveConsumableUnitSalePrice(r, options)
    if (salePrice == null && unit != null) salePrice = unit
    if (r.is_replenishment) {
      const lineTotal = parseMoney(r.line_total)
      const unit =
        parseMoney(r.unit_price) ??
        (lineTotal != null && r.quantity > 0 ? lineTotal / r.quantity : null)
      replenLines.push({ qty: r.quantity, unit })
    } else {
      warehouseQty += r.quantity
    }
  }

  const fromWarehouse = Math.min(used, warehouseQty)
  let fromReplen = used - fromWarehouse
  let cost = fromWarehouse * (salePrice ?? 0)

  for (const line of replenLines) {
    if (fromReplen <= 0) break
    const take = Math.min(fromReplen, line.qty)
    fromReplen -= take
    if (line.unit != null) {
      cost += take * line.unit
    } else if (salePrice != null) {
      cost += take * salePrice
    }
  }

  return cost
}

export function consumableLineCost(
  row: ConsumableCostRow,
  usedQty: number,
  items: ActivityItemRow[],
  issues: ActivityIssueReportRow[],
  options?: { preferExternal?: boolean },
): number {
  return consumableChargeableCost(row.material_item_id, items, issues, options)
}

export function consumableCostTotal(
  items: ActivityItemRow[],
  issues: ActivityIssueReportRow[],
  options?: { preferExternal?: boolean },
): number {
  const ids = new Set(
    items.filter((i) => i.is_consumable).map((i) => i.material_item_id),
  )
  let sum = 0
  for (const id of ids) {
    sum += consumableChargeableCost(id, items, issues, options)
  }
  return sum
}

export function aggregateRentalRows(items: ActivityItemRow[]): RentalCostRow[] {
  return items
    .filter((x) => !x.is_consumable)
    .map((r) => ({
      material_item_id: r.material_item_id,
      material_name: r.material_name,
      quantity: r.quantity,
      unit_price: parseMoney(r.unit_price),
      line_total: parseMoney(r.line_total),
    }))
}

export function rentalCostTotal(rows: RentalCostRow[]): number {
  return rows.reduce((sum, r) => sum + (r.line_total ?? 0), 0)
}

export function replenishmentPurchaseTotal(rows: ReplenishmentPurchaseRow[]): number {
  return rows.reduce((sum, r) => sum + (r.line_total ?? (r.unit_purchase ?? 0) * r.quantity), 0)
}

export function lossIssueUnitPrice(
  materialItemId: string | null | undefined,
  items: ActivityItemRow[],
): number | null {
  if (!materialItemId) return null
  for (const r of items) {
    if (r.material_item_id === materialItemId) {
      const p = effectiveConsumableUnitSalePrice(r)
      if (p != null) return p
    }
  }
  return null
}

export function lossIssueCost(
  issue: { material_item_id?: string | null; quantity: number },
  items: ActivityItemRow[],
): number {
  const unit = lossIssueUnitPrice(issue.material_item_id, items)
  if (unit == null) return 0
  return unit * issue.quantity
}

export function lossCostTotal(
  lossIssues: { material_item_id?: string | null; quantity: number }[],
  items: ActivityItemRow[],
): number {
  return lossIssues.reduce((sum, i) => sum + lossIssueCost(i, items), 0)
}
