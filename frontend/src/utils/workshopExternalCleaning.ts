import type { SupplierRepairTemplate } from '@/api/supplierRepairTemplates'
import type { WorkshopTicket } from '@/api/workshop'
import {
  calcLineTotal,
  createEmptyRepairChecklist,
  formatChfAmount,
  normalizeRepairChecklist,
  parseUnitPrice,
  type RepairChecklist,
  type RepairSheetTemplateInput,
} from '@/types/repairChecklist'
import { supplierTemplateToSheetInput } from '@/api/supplierRepairTemplates'

export const TENT_CLEANING_ITEM_KEY = 'waschen_impraegnieren'
export const TENT_CLEANING_ITEM_LABEL = 'Waschen & imprägnieren'
export const TENT_CLEANING_SECTION_KEY = 'sonderposten'

export interface CleaningServiceOption {
  key: string
  label: string
  unit_price_chf: string | null
  template_key: string
  template_name: string
}

export function collectCleaningServices(templates: SupplierRepairTemplate[]): CleaningServiceOption[] {
  const options: CleaningServiceOption[] = []
  const seen = new Set<string>()

  for (const template of templates) {
    if (!template.is_active) continue
    for (const service of template.services_json?.services ?? []) {
      if (!service.is_active || service.type !== 'cleaning') continue
      const dedupeKey = `${template.template_key}:${service.key}`
      if (seen.has(dedupeKey)) continue
      seen.add(dedupeKey)
      options.push({
        key: service.key,
        label: service.label,
        unit_price_chf: service.unit_price_chf,
        template_key: template.template_key,
        template_name: template.name,
      })
    }
  }

  return options.sort((a, b) => a.label.localeCompare(b.label, 'de'))
}

export function ensureTentCleaningItemInTemplate(
  template: RepairSheetTemplateInput,
): RepairSheetTemplateInput {
  const structure = template.structure_json
  const sections = [...(structure.sections ?? [])]
  const sectionIndex = sections.findIndex((s) => s.key === TENT_CLEANING_SECTION_KEY)
  const cleaningItem = {
    key: TENT_CLEANING_ITEM_KEY,
    label: TENT_CLEANING_ITEM_LABEL,
  }

  if (sectionIndex === -1) {
    sections.push({
      key: TENT_CLEANING_SECTION_KEY,
      label: 'Sonderposten',
      items: [cleaningItem],
    })
  } else {
    const section = { ...sections[sectionIndex], items: [...(sections[sectionIndex].items ?? [])] }
    if (!section.items.some((item) => item.key === TENT_CLEANING_ITEM_KEY)) {
      section.items.push(cleaningItem)
    }
    sections[sectionIndex] = section
  }

  const prices = { ...template.prices_json }
  if (!prices[TENT_CLEANING_ITEM_KEY]) {
    prices[TENT_CLEANING_ITEM_KEY] = { unit_price_chf: null, is_active: true }
  }

  return {
    ...template,
    structure_json: { ...structure, sections },
    prices_json: prices,
  }
}

export function buildExternalCleaningChecklist(
  ticket: WorkshopTicket,
  serviceKey: string,
  templateKey?: string,
): RepairChecklist {
  const base = normalizeRepairChecklist(
    ticket.repair_checklist,
    null,
  )
  const checklist: RepairChecklist = {
    ...base,
    template_key: templateKey ?? ticket.material_item.repair_template_key ?? base.template_key,
    cleaning_service_key: serviceKey,
    scope: 'partial',
    active_section_key: TENT_CLEANING_SECTION_KEY,
  }

  if (ticket.material_item.repair_template_key) {
    checklist.items = {
      ...checklist.items,
      [TENT_CLEANING_ITEM_KEY]: { quantity: 1 },
    }
  }

  return checklist
}

export function getCleaningServiceKey(checklist: RepairChecklist | Record<string, unknown> | null | undefined): string | null {
  if (!checklist || typeof checklist !== 'object') return null
  const key = (checklist as RepairChecklist).cleaning_service_key
  return typeof key === 'string' && key.trim() !== '' ? key : null
}

export function resolveCleaningServiceOption(
  templates: SupplierRepairTemplate[],
  serviceKey: string | null,
): CleaningServiceOption | null {
  if (!serviceKey) return null
  return collectCleaningServices(templates).find((opt) => opt.key === serviceKey) ?? null
}

export function estimateExternalCleaningCost(
  service: CleaningServiceOption | null,
  sheetTemplate: RepairSheetTemplateInput | null,
  checklist: RepairChecklist | null,
): number {
  let total = service ? parseUnitPrice(service.unit_price_chf) : 0

  if (sheetTemplate && checklist) {
    const normalized = normalizeRepairChecklist(checklist, sheetTemplate)
    const qty = normalized.items[TENT_CLEANING_ITEM_KEY]?.quantity ?? 0
    const unitPrice = sheetTemplate.prices_json[TENT_CLEANING_ITEM_KEY]?.unit_price_chf
    total += calcLineTotal(qty, unitPrice)
  }

  return total
}

export function formatCleaningCostSuggestion(total: number): string {
  return total > 0 ? formatChfAmount(total) : ''
}

export function supplierTemplateToCleaningSheetInput(template: SupplierRepairTemplate): RepairSheetTemplateInput {
  return ensureTentCleaningItemInTemplate(supplierTemplateToSheetInput(template))
}

export function createTentCleaningChecklist(templateKey: string, serviceKey: string): RepairChecklist {
  const checklist = createEmptyRepairChecklist(templateKey)
  checklist.cleaning_service_key = serviceKey
  checklist.scope = 'partial'
  checklist.active_section_key = TENT_CLEANING_SECTION_KEY
  checklist.items[TENT_CLEANING_ITEM_KEY] = { quantity: 1 }
  return checklist
}
