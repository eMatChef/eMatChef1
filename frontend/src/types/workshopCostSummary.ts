import {
  calcPositionsSubtotal,
  calcRepairSheetTotal,
  formatChfAmount,
  normalizeRepairChecklist,
  parseUnitPrice,
  type RepairChecklist,
  type RepairSheetTemplateInput,
} from '@/types/repairChecklist'

export interface WorkshopCostBreakdown {
  labor_enabled: boolean
  labor_hours: number
  labor_rate_chf: string
  labor_total_chf: string
  flat_rate_enabled: boolean
  flat_rate_chf: string
  material_enabled: boolean
  material_parts_chf: string
  material_sheet_chf: string
  material_total_chf: string
  total_chf: string
}

export interface WorkshopCostSummaryInput {
  hourlyRateChf: string
  partsMaterialCost: number
  sheetTemplate: RepairSheetTemplateInput | null
  repairChecklist: RepairChecklist | null
  includeLabor: boolean
  includeFlatRate: boolean
  includeMaterial: boolean
  laborHours: number
  flatRateOverride: string
}

export function createEmptyCostBreakdown(hourlyRateChf: string): WorkshopCostBreakdown {
  return {
    labor_enabled: false,
    labor_hours: 0,
    labor_rate_chf: hourlyRateChf,
    labor_total_chf: '0.00',
    flat_rate_enabled: false,
    flat_rate_chf: '0.00',
    material_enabled: false,
    material_parts_chf: '0.00',
    material_sheet_chf: '0.00',
    material_total_chf: '0.00',
    total_chf: '0.00',
  }
}

export function resolveSheetMaterialCosts(
  sheetTemplate: RepairSheetTemplateInput | null,
  repairChecklist: RepairChecklist | null,
): { sheetPositionsCost: number; suggestedFlatRate: number; wholeUnitFlatRate: number } {
  if (!sheetTemplate || !repairChecklist) {
    return { sheetPositionsCost: 0, suggestedFlatRate: 0, wholeUnitFlatRate: 0 }
  }

  const checklist = normalizeRepairChecklist(repairChecklist, sheetTemplate)
  const totals = calcRepairSheetTotal(checklist, sheetTemplate)
  const wholeUnitOption = sheetTemplate.structure_json.whole_unit_option === true

  if (checklist.scope === 'whole_unit' && wholeUnitOption) {
    return {
      sheetPositionsCost: 0,
      suggestedFlatRate: totals.grandTotal,
      wholeUnitFlatRate: totals.grandTotal,
    }
  }

  return {
    sheetPositionsCost: calcPositionsSubtotal(checklist, sheetTemplate),
    suggestedFlatRate: parseUnitPrice(sheetTemplate.flat_rate_chf),
    wholeUnitFlatRate: 0,
  }
}

export function buildCostBreakdown(input: WorkshopCostSummaryInput): WorkshopCostBreakdown {
  const { sheetPositionsCost, suggestedFlatRate, wholeUnitFlatRate } = resolveSheetMaterialCosts(
    input.sheetTemplate,
    input.repairChecklist,
  )

  const partsCost = Math.max(0, input.partsMaterialCost)
  const sheetMaterialCost = input.includeMaterial ? sheetPositionsCost : 0
  const materialTotal = partsCost + sheetMaterialCost

  const flatRateValue = input.includeFlatRate
    ? parseUnitPrice(input.flatRateOverride) || suggestedFlatRate || wholeUnitFlatRate
    : 0

  const laborRate = parseUnitPrice(input.hourlyRateChf)
  const laborHours = input.includeLabor && input.laborHours > 0 ? input.laborHours : 0
  const laborTotal = laborHours * laborRate

  const total = (input.includeLabor ? laborTotal : 0)
    + (input.includeFlatRate ? flatRateValue : 0)
    + (input.includeMaterial ? materialTotal : 0)

  return {
    labor_enabled: input.includeLabor,
    labor_hours: laborHours,
    labor_rate_chf: formatChfAmount(laborRate),
    labor_total_chf: formatChfAmount(laborTotal),
    flat_rate_enabled: input.includeFlatRate,
    flat_rate_chf: formatChfAmount(flatRateValue),
    material_enabled: input.includeMaterial,
    material_parts_chf: formatChfAmount(partsCost),
    material_sheet_chf: formatChfAmount(sheetMaterialCost),
    material_total_chf: formatChfAmount(materialTotal),
    total_chf: formatChfAmount(total),
  }
}

export function suggestDefaultCostFlags(
  partsMaterialCost: number,
  sheetTemplate: RepairSheetTemplateInput | null,
  repairChecklist: RepairChecklist | null,
): { includeLabor: boolean; includeFlatRate: boolean; includeMaterial: boolean; flatRateOverride: string } {
  const { sheetPositionsCost, suggestedFlatRate, wholeUnitFlatRate } = resolveSheetMaterialCosts(
    sheetTemplate,
    repairChecklist,
  )

  const flatRate = wholeUnitFlatRate > 0 ? wholeUnitFlatRate : suggestedFlatRate

  return {
    includeLabor: false,
    includeFlatRate: flatRate > 0,
    includeMaterial: partsMaterialCost > 0 || sheetPositionsCost > 0,
    flatRateOverride: flatRate > 0 ? formatChfAmount(flatRate) : '',
  }
}
