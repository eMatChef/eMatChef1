import apiClient from './apiClient'

// ============== Types ==============

export interface TemplateComponent {
  id: string
  component_type: string
  name: string
  required_qty: number
  is_optional: boolean
  is_generic: boolean
  tracking: 'serialized' | 'bulk'
  repair_types: string[] | null
  sort_order: number
}

export interface Template {
  id: string
  department_id: string | null
  scope: 'global' | 'department'
  is_global: boolean
  name: string
  description: string | null
  manufacturer: string | null
  model: string | null
  category: { id: string; name: string } | null
  material_type: 'physical_combo' | 'virtual_combo'
  tent_type: string | null
  capacity: number | null
  reservation_mode: 'complete_only' | 'individual' | 'flexible' | null
  is_active: boolean
  source: string | null
  component_count: number
  can_edit: boolean
  created_at: string
  updated_at: string
  // Nur bei Einzelabfrage
  components?: TemplateComponent[]
}

export interface CreateTemplateComponentRequest {
  component_type: string
  name: string
  required_qty?: number
  is_optional?: boolean
  is_generic?: boolean
  tracking?: 'serialized' | 'bulk'
  repair_types?: string[]
  sort_order?: number
}

export interface CreateTemplateRequest {
  department_id?: string
  scope?: 'global' | 'department'
  name: string
  description?: string | null
  manufacturer?: string | null
  model?: string | null
  category_id?: string | null
  material_type?: 'physical_combo' | 'virtual_combo'
  tent_type?: string | null
  capacity?: number | null
  reservation_mode?: string | null
  is_active?: boolean
  source?: string | null
  components?: CreateTemplateComponentRequest[]
}

export interface UpdateTemplateRequest {
  name?: string
  description?: string | null
  manufacturer?: string | null
  model?: string | null
  category_id?: string | null
  material_type?: 'physical_combo' | 'virtual_combo'
  tent_type?: string | null
  capacity?: number | null
  reservation_mode?: string | null
  is_active?: boolean
  source?: string | null
  components?: CreateTemplateComponentRequest[]
}

// ============== API Functions ==============

/**
 * Lädt alle Vorlagen für ein Department
 */
export async function getTemplates(departmentId: string, activeOnly = false): Promise<Template[]> {
  const params = new URLSearchParams({ department_id: departmentId })
  if (activeOnly) params.append('active_only', '1')
  const response = await apiClient.get<Template[]>(`/api/templates?${params.toString()}`)
  return response.data
}

/**
 * Lädt eine einzelne Vorlage mit Komponenten
 */
export async function getTemplate(id: string): Promise<Template> {
  const response = await apiClient.get<Template>(`/api/templates/${id}`)
  return response.data
}

/**
 * Erstellt eine neue Vorlage
 */
export async function createTemplate(data: CreateTemplateRequest): Promise<Template> {
  const response = await apiClient.post<Template>('/api/templates', data)
  return response.data
}

/**
 * Aktualisiert eine Vorlage
 */
export async function updateTemplate(id: string, data: UpdateTemplateRequest): Promise<Template> {
  const response = await apiClient.patch<Template>(`/api/templates/${id}`, data)
  return response.data
}

/**
 * Löscht eine Vorlage
 */
export async function deleteTemplate(id: string): Promise<void> {
  await apiClient.delete(`/api/templates/${id}`)
}

/**
 * Importiert Vorlagen aus v4-JSON-Format
 */
export async function importTemplates(departmentId: string, templatesJson: any): Promise<{
  success: boolean
  manufacturer: string
  created: number
  skipped: number
  total: number
}> {
  const response = await apiClient.post('/api/templates/import', {
    department_id: departmentId,
    templates_json: templatesJson
  })
  return response.data
}

// ============== Create Material from Template ==============

export interface CreateMaterialComponentInput {
  component_type: string
  mode: 'new' | 'existing'
  /** Für mode=new, nur bei serialisierten Komponenten */
  serial_number?: string
  /** Für mode=new, bulk: Stückzahl */
  qty?: number
  /** Für mode=new */
  unit_price?: string
  /** Für mode=existing */
  material_id?: string
  /** Für mode=existing */
  batch_id?: string
  /** Für virtual_combo: assigned, on_issue */
  assignment_mode?: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
}

export interface CreateMaterialFromTemplateRequest {
  department_id?: string
  creation_mode: 'individual' | 'physical_combo' | 'virtual_combo'
  /** Name der Kombo (Pflicht bei physical_combo und virtual_combo) */
  name?: string
  description?: string
  serial_number?: string
  storage_address_id?: string
  category_id?: string
  purchase_date?: string
  supplier_id?: string
  manufacturer?: string
  model?: string
  tent_type?: string
  tent_capacity?: number
  /** Nur bei virtual_combo relevant */
  reservation_mode?: string
  components?: CreateMaterialComponentInput[]
  /** Nur physical_combo: Lagerung des Kombi-Sets */
  initial_rack_id?: string
  initial_slot_id?: string
  initial_container_batch_id?: string
}

export interface CreatedArticleInfo {
  id: string
  name: string
  is_new: boolean
  tracking: string
  batch_id: string | null
  serial_number: string | null
  qty: number
}

export interface CreateMaterialFromTemplateResponse {
  success: boolean
  creation_mode: string
  /** Nur bei Kombo-Modi */
  material?: {
    id: string
    name: string
    material_type: string
    is_tent: boolean
    tent_type: string | null
    tent_capacity: number | null
    reservation_mode: string | null
    manufacturer: string | null
    serial_number: string | null
  }
  /** Nur bei individual */
  articles?: CreatedArticleInfo[]
  /** Bei Kombo-Modi */
  components?: CreatedArticleInfo[]
  template_id: string
  template_name: string
}

/**
 * Erstellt ein Material (Zelt/Combo) aus einer Vorlage – in einer Transaktion
 */
export async function createMaterialFromTemplate(
  templateId: string,
  data: CreateMaterialFromTemplateRequest
): Promise<CreateMaterialFromTemplateResponse> {
  const response = await apiClient.post<CreateMaterialFromTemplateResponse>(
    `/api/templates/${templateId}/create-material`,
    data
  )
  return response.data
}
