/**
 * Stückliste Nicht-Zelt (workshop_ticket.parts_used).
 * Zelt-Material nutzt repair_checklist statt parts_used.
 */
export type RepairPartSource = 'stock' | 'purchase' | 'rest'

export type RepairPartStatus = 'planned' | 'ordered' | 'received' | 'consumed'

export interface RepairPartLine {
  id: string
  material_item_id: string
  material_name: string | null
  quantity: number
  source: RepairPartSource
  status: RepairPartStatus
  unit_cost: string | null
  supplier_id?: string | null
  purchase_location?: string | null
  purchase_total?: string | null
  document_date?: string | null
  ordered_at?: string | null
  received_at?: string | null
  material_batch_id?: string | null
  receipt_url?: string | null
  surplus_qty?: number | null
  /** Vorrat MW (Rest/Abschreibung), z. B. 5 Flicken oder 20 m Garn */
  available_qty?: number | null
  /** Anzeige-Einheit: Stk, m, … */
  quantity_unit?: string | null
}

export const REPAIR_PART_SOURCES: RepairPartSource[] = ['stock', 'purchase', 'rest']

export const REPAIR_PART_STATUSES: RepairPartStatus[] = [
  'planned',
  'ordered',
  'received',
  'consumed',
]

export function createRepairPartLine(partial?: Partial<RepairPartLine>): RepairPartLine {
  return {
    id: partial?.id || crypto.randomUUID(),
    material_item_id: partial?.material_item_id || '',
    material_name: partial?.material_name ?? null,
    quantity: partial?.quantity ?? 1,
    source: partial?.source ?? 'stock',
    status: partial?.status ?? 'planned',
    unit_cost: partial?.unit_cost ?? null,
    supplier_id: partial?.supplier_id ?? null,
    purchase_location: partial?.purchase_location ?? null,
    purchase_total: partial?.purchase_total ?? null,
    document_date: partial?.document_date ?? null,
    ordered_at: partial?.ordered_at ?? null,
    received_at: partial?.received_at ?? null,
    material_batch_id: partial?.material_batch_id ?? null,
    receipt_url: partial?.receipt_url ?? null,
    surplus_qty: partial?.surplus_qty ?? null,
    available_qty: partial?.available_qty ?? null,
    quantity_unit: partial?.quantity_unit ?? null,
  }
}

function optionalString(value: unknown): string | null {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed || null
}

export function normalizeRepairPartsList(raw: unknown): RepairPartLine[] {
  if (!Array.isArray(raw)) return []

  return raw
    .filter((line): line is Record<string, unknown> => !!line && typeof line === 'object')
    .map((line) =>
      createRepairPartLine({
        id: typeof line.id === 'string' ? line.id : undefined,
        material_item_id: String(line.material_item_id || '').trim(),
        material_name: optionalString(line.material_name),
        quantity: Number(line.quantity) > 0 ? Number(line.quantity) : 1,
        source: REPAIR_PART_SOURCES.includes(line.source as RepairPartSource)
          ? (line.source as RepairPartSource)
          : 'stock',
        status: REPAIR_PART_STATUSES.includes(line.status as RepairPartStatus)
          ? (line.status as RepairPartStatus)
          : 'planned',
        unit_cost:
          line.unit_cost != null && String(line.unit_cost).trim() !== ''
            ? String(line.unit_cost)
            : null,
        supplier_id: optionalString(line.supplier_id),
        purchase_location: optionalString(line.purchase_location),
        purchase_total:
          line.purchase_total != null && String(line.purchase_total).trim() !== ''
            ? String(line.purchase_total)
            : null,
        document_date: optionalString(line.document_date),
        ordered_at: optionalString(line.ordered_at),
        received_at: optionalString(line.received_at),
        material_batch_id: optionalString(line.material_batch_id),
        receipt_url: optionalString(line.receipt_url),
        surplus_qty:
          line.surplus_qty != null && Number(line.surplus_qty) >= 0 ? Number(line.surplus_qty) : null,
        available_qty:
          line.available_qty != null && Number(line.available_qty) >= 0 ? Number(line.available_qty) : null,
        quantity_unit: optionalString(line.quantity_unit),
      }),
    )
    .filter((line) => line.material_item_id !== '')
}

export function repairPartsListToPayload(lines: RepairPartLine[]): RepairPartLine[] {
  return lines.map((line) => ({ ...line }))
}
