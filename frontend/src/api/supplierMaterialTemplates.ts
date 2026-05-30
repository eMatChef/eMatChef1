import apiClient from './apiClient'

export type SupplierMaterialType = 'physical_combo' | 'virtual_combo'
export type SupplierTemplateVisibility = 'private' | 'departments' | 'global'
export type SupplierTemplateStatus = 'draft' | 'published' | 'pending_review'
export type SupplierTemplateTracking = 'bulk' | 'serialized'
export type SupplierComponentSource = 'stock' | 'self_provided'
export type SupplierOptionSelectionType = 'exclusive' | 'multi' | 'quantity'
export type SupplierOptionDisplayMode = 'toggle' | 'group'

export interface SupplierTemplateComponent {
  id?: string
  component_type: string
  name: string
  required_qty: number
  is_optional: boolean
  tracking: SupplierTemplateTracking
  component_source: SupplierComponentSource
  is_generic: boolean
  sort_order: number
}

export interface SupplierTemplateOptionDelta {
  id?: string
  component_type: string
  name: string
  qty_delta: number
  tracking: SupplierTemplateTracking
  component_source: SupplierComponentSource
  is_generic: boolean
  sort_order: number
}

export interface SupplierTemplateOption {
  id?: string
  option_group_id?: string | null
  name: string
  display_mode: SupplierOptionDisplayMode
  default_selected: boolean
  sort_order: number
  deltas: SupplierTemplateOptionDelta[]
}

export interface SupplierTemplateOptionGroup {
  id?: string
  name: string
  selection_type: SupplierOptionSelectionType
  min_select: number
  max_select: number | null
  sort_order: number
  options: SupplierTemplateOption[]
}

export interface SupplierMaterialTemplate {
  id: string
  supplier_company_id: string
  name: string
  description: string | null
  manufacturer: string | null
  model: string | null
  material_type: SupplierMaterialType
  tent_type: string | null
  capacity: number | null
  category_hint: string | null
  unit_price: number | null
  currency: string
  is_active: boolean
  visibility: SupplierTemplateVisibility
  status: SupplierTemplateStatus
  source: string | null
  legacy_material_template_id: string | null
  component_count: number
  created_at: string
  updated_at: string
  components?: SupplierTemplateComponent[]
  option_groups?: SupplierTemplateOptionGroup[]
  standalone_options?: SupplierTemplateOption[]
}

export interface SupplierMaterialTemplatePayload {
  name: string
  description?: string | null
  manufacturer?: string | null
  model?: string | null
  material_type?: SupplierMaterialType
  tent_type?: string | null
  capacity?: number | null
  category_hint?: string | null
  unit_price?: number | null
  currency?: string
  is_active?: boolean
  visibility?: SupplierTemplateVisibility
  status?: SupplierTemplateStatus
  components?: SupplierTemplateComponent[]
  option_groups?: SupplierTemplateOptionGroup[]
  standalone_options?: SupplierTemplateOption[]
}

export async function listSupplierMaterialTemplates(
  companyId: string,
): Promise<SupplierMaterialTemplate[]> {
  const { data } = await apiClient.get<{ material_templates: SupplierMaterialTemplate[] }>(
    `/api/supplier-companies/${companyId}/material-templates`,
  )
  return data.material_templates
}

export async function getSupplierMaterialTemplate(
  companyId: string,
  templateId: string,
): Promise<SupplierMaterialTemplate> {
  const { data } = await apiClient.get<{ material_template: SupplierMaterialTemplate }>(
    `/api/supplier-companies/${companyId}/material-templates/${templateId}`,
  )
  return data.material_template
}

export async function createSupplierMaterialTemplate(
  companyId: string,
  payload: SupplierMaterialTemplatePayload,
): Promise<SupplierMaterialTemplate> {
  const { data } = await apiClient.post<{ material_template: SupplierMaterialTemplate }>(
    `/api/supplier-companies/${companyId}/material-templates`,
    payload,
  )
  return data.material_template
}

export async function updateSupplierMaterialTemplate(
  companyId: string,
  templateId: string,
  payload: Partial<SupplierMaterialTemplatePayload>,
): Promise<SupplierMaterialTemplate> {
  const { data } = await apiClient.patch<{ material_template: SupplierMaterialTemplate }>(
    `/api/supplier-companies/${companyId}/material-templates/${templateId}`,
    payload,
  )
  return data.material_template
}

export async function deleteSupplierMaterialTemplate(
  companyId: string,
  templateId: string,
): Promise<void> {
  await apiClient.delete(`/api/supplier-companies/${companyId}/material-templates/${templateId}`)
}
