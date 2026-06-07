import type {
  DepartmentRepairTemplate,
  PlatformRepairTemplate,
  RepairTemplatePricesJson,
  RepairTemplateStructure,
} from '@/api/repairTemplates'

export type RepairChecklistScope = 'whole_unit' | 'partial'

export interface RepairChecklistItemEntry {
  quantity: number
}

export interface RepairChecklist {
  template_key?: string
  scope: RepairChecklistScope
  active_section_key: string | null
  marker_ids: string[]
  items: Record<string, RepairChecklistItemEntry>
  notes: string
  /** Externe Reinigung: gewählter Supplier-Dienst (services_json) */
  cleaning_service_key?: string
}

export interface RepairDiagramMarker {
  id: string
  section_key: string
  x: number
  y: number
  label?: string
  color?: string
}

export interface RepairDiagramJson {
  viewBox?: string
  markers?: RepairDiagramMarker[]
}

export type RepairSheetEditorMode = 'edit' | 'readonly' | 'supplier' | 'report'
export type RepairSheetPriceSource = 'department' | 'supplier'

export interface RepairSheetTemplateInput {
  template_key: string
  name: string
  structure_json: RepairTemplateStructure
  diagram_json?: RepairDiagramJson | Record<string, unknown> | null
  prices_json: RepairTemplatePricesJson
  flat_rate_chf: string | null
}

function buildPricesFromStructure(
  structure: RepairTemplateStructure | undefined,
  source?: RepairTemplatePricesJson,
): RepairTemplatePricesJson {
  const prices: RepairTemplatePricesJson = { ...(source ?? {}) }
  for (const section of structure?.sections ?? []) {
    for (const item of section.items ?? []) {
      if (!prices[item.key]) {
        prices[item.key] = { unit_price_chf: null, is_active: true }
      }
    }
  }

  return prices
}

export function platformTemplateToSheetInput(template: PlatformRepairTemplate): RepairSheetTemplateInput {
  const structure = template.structure_json ?? { sections: [] }

  return {
    template_key: template.template_key,
    name: template.name,
    structure_json: structure,
    diagram_json: template.diagram_json as RepairDiagramJson | null | undefined,
    prices_json: buildPricesFromStructure(structure),
    flat_rate_chf: null,
  }
}

export function departmentTemplateToSheetInput(
  template: DepartmentRepairTemplate
): RepairSheetTemplateInput {
  return {
    template_key: template.template_key,
    name: template.name,
    structure_json: template.structure_json,
    diagram_json: template.diagram_json as RepairDiagramJson | null | undefined,
    prices_json: template.prices_json,
    flat_rate_chf: template.flat_rate_chf,
  }
}

export function createEmptyRepairChecklist(templateKey?: string): RepairChecklist {
  return {
    template_key: templateKey,
    scope: 'partial',
    active_section_key: null,
    marker_ids: [],
    items: {},
    notes: '',
  }
}

export function normalizeRepairChecklist(
  raw: RepairChecklist | Record<string, unknown> | null | undefined,
  template?: RepairSheetTemplateInput | null
): RepairChecklist {
  const base = createEmptyRepairChecklist(template?.template_key)
  if (!raw || typeof raw !== 'object') {
    return hydrateChecklistItems(base, template)
  }

  const input = raw as Partial<RepairChecklist>
  const merged: RepairChecklist = {
    template_key: typeof input.template_key === 'string' ? input.template_key : base.template_key,
    scope: input.scope === 'whole_unit' ? 'whole_unit' : 'partial',
    active_section_key:
      typeof input.active_section_key === 'string' ? input.active_section_key : null,
    marker_ids: Array.isArray(input.marker_ids)
      ? input.marker_ids.filter((id): id is string => typeof id === 'string')
      : [],
    items: {},
    notes: typeof input.notes === 'string' ? input.notes : '',
    cleaning_service_key:
      typeof input.cleaning_service_key === 'string' ? input.cleaning_service_key : undefined,
  }

  if (input.items && typeof input.items === 'object') {
    for (const [key, entry] of Object.entries(input.items)) {
      if (!entry || typeof entry !== 'object') continue
      const qty = Number((entry as RepairChecklistItemEntry).quantity)
      merged.items[key] = { quantity: Number.isFinite(qty) && qty >= 0 ? Math.floor(qty) : 0 }
    }
  }

  return hydrateChecklistItems(merged, template)
}

export function hydrateChecklistItems(
  checklist: RepairChecklist,
  template?: RepairSheetTemplateInput | null
): RepairChecklist {
  if (!template) return checklist

  for (const section of template.structure_json.sections ?? []) {
    for (const item of section.items ?? []) {
      if (!checklist.items[item.key]) {
        checklist.items[item.key] = { quantity: 0 }
      }
    }
  }

  return checklist
}

export function parseUnitPrice(value: string | null | undefined): number {
  if (value === null || value === undefined || String(value).trim() === '') return 0
  const n = Number(String(value).replace(',', '.'))
  return Number.isFinite(n) && n >= 0 ? n : 0
}

export function formatChfAmount(value: number): string {
  return value.toFixed(2)
}

export function calcLineTotal(quantity: number, unitPrice: string | null | undefined): number {
  return quantity * parseUnitPrice(unitPrice)
}

export function calcPositionsSubtotal(
  checklist: RepairChecklist,
  template: RepairSheetTemplateInput
): number {
  let total = 0
  for (const section of template.structure_json.sections ?? []) {
    for (const item of section.items ?? []) {
      const priceEntry = template.prices_json[item.key]
      if (!priceEntry?.is_active) continue
      const qty = checklist.items[item.key]?.quantity ?? 0
      total += calcLineTotal(qty, priceEntry.unit_price_chf)
    }
  }
  return total
}

export function calcRepairSheetTotal(
  checklist: RepairChecklist,
  template: RepairSheetTemplateInput
): { positionsSubtotal: number; flatRate: number; grandTotal: number } {
  const wholeUnitOption = template.structure_json.whole_unit_option === true
  const flatRate = parseUnitPrice(template.flat_rate_chf)

  if (checklist.scope === 'whole_unit' && wholeUnitOption) {
    return { positionsSubtotal: 0, flatRate, grandTotal: flatRate }
  }

  const positionsSubtotal = calcPositionsSubtotal(checklist, template)
  const grandTotal = positionsSubtotal + (flatRate > 0 ? flatRate : 0)
  return { positionsSubtotal, flatRate, grandTotal }
}

export function parseDiagramJson(
  raw: RepairDiagramJson | Record<string, unknown> | null | undefined
): RepairDiagramJson | null {
  if (!raw || typeof raw !== 'object') return null
  const markers = Array.isArray((raw as RepairDiagramJson).markers)
    ? (raw as RepairDiagramJson).markers
    : []
  return {
    viewBox: typeof (raw as RepairDiagramJson).viewBox === 'string'
      ? (raw as RepairDiagramJson).viewBox
      : '0 0 400 260',
    markers: markers.filter(
      (m): m is RepairDiagramMarker =>
        !!m &&
        typeof m === 'object' &&
        typeof (m as RepairDiagramMarker).id === 'string' &&
        typeof (m as RepairDiagramMarker).section_key === 'string'
    ),
  }
}
