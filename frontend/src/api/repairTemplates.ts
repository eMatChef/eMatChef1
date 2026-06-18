import apiClient from './apiClient'

export interface RepairTemplateStructureItem {
  key: string
  label: string
  diagram_marker?: string | null
}

export interface RepairTemplateStructureSection {
  key: string
  label: string
  items: RepairTemplateStructureItem[]
}

export interface RepairTemplateStructure {
  sections: RepairTemplateStructureSection[]
  whole_unit_option?: boolean
}

export interface RepairTemplatePriceEntry {
  unit_price_chf: string | null
  is_active: boolean
}

export type RepairTemplatePricesJson = Record<string, RepairTemplatePriceEntry>

export interface PlatformRepairTemplate {
  id: string
  template_key: string
  name: string
  material_class: string
  is_active: boolean
  created_at: string
  updated_at: string
  structure_json?: RepairTemplateStructure
  diagram_json?: Record<string, unknown> | null
}

export interface DepartmentRepairTemplate {
  id: string
  department_id: string
  template_key: string
  name: string
  material_class: string
  flat_rate_chf: string | null
  is_active: boolean
  prices_json: RepairTemplatePricesJson
  structure_json: RepairTemplateStructure
  diagram_json?: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface UpdateDepartmentRepairTemplateRequest {
  prices_json?: RepairTemplatePricesJson
  flat_rate_chf?: string | null
  is_active?: boolean
}

export async function getPlatformRepairTemplates(): Promise<PlatformRepairTemplate[]> {
  const { data } = await apiClient.get<PlatformRepairTemplate[]>('/api/repair-templates')
  return data
}

export async function getDepartmentRepairTemplates(departmentId: string): Promise<DepartmentRepairTemplate[]> {
  const { data } = await apiClient.get<DepartmentRepairTemplate[]>(
    `/api/departments/${departmentId}/repair-templates`
  )
  return data
}

export async function importDepartmentRepairTemplate(
  departmentId: string,
  templateKey: string
): Promise<DepartmentRepairTemplate> {
  const { data } = await apiClient.post<DepartmentRepairTemplate>(
    `/api/departments/${departmentId}/repair-templates/import`,
    { template_key: templateKey }
  )
  return data
}

export async function updateDepartmentRepairTemplate(
  departmentId: string,
  templateKey: string,
  payload: UpdateDepartmentRepairTemplateRequest
): Promise<DepartmentRepairTemplate> {
  const { data } = await apiClient.patch<DepartmentRepairTemplate>(
    `/api/departments/${departmentId}/repair-templates/${templateKey}`,
    payload
  )
  return data
}
