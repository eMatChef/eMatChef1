import apiClient from './apiClient'
import type {
  DepartmentRepairTemplate,
  RepairTemplatePricesJson,
  RepairTemplateStructure,
} from './repairTemplates'

export type SupplierRepairServiceType = 'cleaning' | 'repair'

export interface SupplierRepairServiceEntry {
  key: string
  label: string
  type: SupplierRepairServiceType
  unit_price_chf: string | null
  is_active: boolean
}

export interface SupplierRepairServicesJson {
  services: SupplierRepairServiceEntry[]
}

export interface SupplierRepairTemplate extends Omit<DepartmentRepairTemplate, 'department_id'> {
  supplier_company_id: string
  services_json: SupplierRepairServicesJson
}

export interface UpdateSupplierRepairTemplateRequest {
  prices_json?: RepairTemplatePricesJson
  services_json?: SupplierRepairServicesJson
  flat_rate_chf?: string | null
  is_active?: boolean
}

export async function getSupplierRepairTemplates(companyId: string): Promise<SupplierRepairTemplate[]> {
  const { data } = await apiClient.get<{ repair_templates: SupplierRepairTemplate[] }>(
    `/api/supplier-companies/${companyId}/repair-templates`,
  )
  return data.repair_templates ?? []
}

export async function importSupplierRepairTemplate(
  companyId: string,
  templateKey: string,
): Promise<SupplierRepairTemplate> {
  const { data } = await apiClient.post<SupplierRepairTemplate>(
    `/api/supplier-companies/${companyId}/repair-templates/import`,
    { template_key: templateKey },
  )
  return data
}

export async function updateSupplierRepairTemplate(
  companyId: string,
  templateKey: string,
  payload: UpdateSupplierRepairTemplateRequest,
): Promise<SupplierRepairTemplate> {
  const { data } = await apiClient.patch<SupplierRepairTemplate>(
    `/api/supplier-companies/${companyId}/repair-templates/${templateKey}`,
    payload,
  )
  return data
}

export async function deleteSupplierRepairTemplate(
  companyId: string,
  templateKey: string,
): Promise<void> {
  await apiClient.delete(`/api/supplier-companies/${companyId}/repair-templates/${templateKey}`)
}

/** MW: Supplier-Zeltblatt nach Lieferantenwahl laden */
export async function listDepartmentSupplierRepairTemplates(
  departmentId: string,
  supplierCompanyId: string,
): Promise<SupplierRepairTemplate[]> {
  const { data } = await apiClient.get<{ repair_templates: SupplierRepairTemplate[] }>(
    `/api/departments/${departmentId}/supplier-shop/repair-templates`,
    { params: { supplier_company_id: supplierCompanyId } },
  )
  return data.repair_templates ?? []
}

export function createEmptyServicesJson(): SupplierRepairServicesJson {
  return { services: [] }
}

export function supplierTemplateToSheetInput(template: SupplierRepairTemplate): {
  template_key: string
  name: string
  structure_json: RepairTemplateStructure
  diagram_json?: Record<string, unknown> | null
  prices_json: RepairTemplatePricesJson
  flat_rate_chf: string | null
} {
  return {
    template_key: template.template_key,
    name: template.name,
    structure_json: template.structure_json,
    diagram_json: template.diagram_json,
    prices_json: template.prices_json,
    flat_rate_chf: template.flat_rate_chf,
  }
}
