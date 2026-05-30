import apiClient from './apiClient'

// ============== Types ==============

/** Komponenten-Quelle (Weg B): aus Lager vs. vom Leiter mitgebracht. */
export type ComponentSource = 'stock' | 'self_provided'

/** Anzeige-Modus einer Vorlagen-Option. */
export type OptionDisplayMode = 'toggle' | 'group'

/** Auswahlregel einer Vorlagen-Options-Gruppe. */
export type OptionSelectionType = 'exclusive' | 'multi' | 'quantity'

export interface TemplateComponent {
  id: string
  component_type: string
  name: string
  required_qty: number
  is_optional: boolean
  is_generic: boolean
  tracking: 'serialized' | 'bulk'
  component_source: ComponentSource
  repair_types: string[] | null
  sort_order: number
}

/** ±Stücklisten-Zeile einer Vorlagen-Option (abstrakt über component_type). */
export interface TemplateOptionDelta {
  id: string
  option_id: string
  component_type: string
  name: string
  qty_delta: number
  tracking: 'serialized' | 'bulk'
  component_source: ComponentSource
  is_generic: boolean
  sort_order: number
}

export interface TemplateOption {
  id: string
  template_id: string
  option_group_id: string | null
  name: string
  display_mode: OptionDisplayMode
  default_selected: boolean
  sort_order: number
  deltas: TemplateOptionDelta[]
}

export interface TemplateOptionGroup {
  id: string
  template_id: string
  name: string
  selection_type: OptionSelectionType
  min_select: number
  max_select: number | null
  sort_order: number
}

export interface TemplateRelatedAccessory {
  id: string
  name: string
  component_type: string | null
  is_generic: boolean
  sort_order: number
}

export interface CreateTemplateRelatedAccessoryRequest {
  name: string
  component_type?: string | null
  is_generic?: boolean
  sort_order?: number
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
  is_active: boolean
  source: string | null
  component_count: number
  can_edit: boolean
  created_at: string
  updated_at: string
  // Nur bei Einzelabfrage
  components?: TemplateComponent[]
  related_accessories?: TemplateRelatedAccessory[]
  options?: TemplateOption[]
  option_groups?: TemplateOptionGroup[]
}

export interface CreateTemplateComponentRequest {
  component_type: string
  name: string
  required_qty?: number
  is_optional?: boolean
  is_generic?: boolean
  tracking?: 'serialized' | 'bulk'
  component_source?: ComponentSource
  repair_types?: string[]
  sort_order?: number
}

/** Options-Gruppe einer Vorlage (Weg B, Paket 6). `id` ist client-seitig (temp) oder real (Bezug für Optionen). */
export interface UpsertTemplateOptionGroupRequest {
  id?: string
  name: string
  selection_type?: OptionSelectionType
  min_select?: number
  max_select?: number | null
  sort_order?: number
}

export interface UpsertTemplateOptionDeltaRequest {
  component_type: string
  name: string
  qty_delta: number
  tracking?: 'serialized' | 'bulk'
  component_source?: ComponentSource
  is_generic?: boolean
  sort_order?: number
}

export interface UpsertTemplateOptionRequest {
  name: string
  display_mode?: OptionDisplayMode
  default_selected?: boolean
  /** Verweist auf die `id` einer UpsertTemplateOptionGroupRequest. */
  option_group_id?: string | null
  sort_order?: number
  deltas?: UpsertTemplateOptionDeltaRequest[]
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
  is_active?: boolean
  source?: string | null
  components?: CreateTemplateComponentRequest[]
  related_accessories?: CreateTemplateRelatedAccessoryRequest[]
  option_groups?: UpsertTemplateOptionGroupRequest[]
  options?: UpsertTemplateOptionRequest[]
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
  is_active?: boolean
  source?: string | null
  components?: CreateTemplateComponentRequest[]
  related_accessories?: CreateTemplateRelatedAccessoryRequest[]
  option_groups?: UpsertTemplateOptionGroupRequest[]
  options?: UpsertTemplateOptionRequest[]
}

export type TemplateImportDuplicateAction = 'skip' | 'update' | 'create'

export interface TemplateImportResultRow {
  template_index: number
  name: string | null
  status: string
  action: string | null
  errors: string[]
  existing_template_id?: string | null
}

export interface TemplateImportResponse {
  success: boolean
  dry_run: boolean
  manufacturer: string
  rows: TemplateImportResultRow[]
  stats: {
    created: number
    updated: number
    skipped: number
    errors: number
  }
  total: number
  created: number
  updated: number
  skipped: number
  error?: string
}

export interface TemplateExportJson {
  format_version: number
  manufacturer: string
  templates: unknown[]
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
 * Lädt alle globalen Vorlagen (Admin, ohne Department-Kontext)
 */
export async function getGlobalTemplates(activeOnly = false): Promise<Template[]> {
  const params = new URLSearchParams({ scope: 'global' })
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
 * Importiert Vorlagen aus v4/v5-JSON (Department)
 */
export async function importTemplates(
  departmentId: string,
  templatesJson: unknown,
  options: {
    dryRun?: boolean
    duplicateAction?: TemplateImportDuplicateAction
    force?: boolean
  } = {},
): Promise<TemplateImportResponse> {
  const response = await apiClient.post<TemplateImportResponse>('/api/templates/import', {
    department_id: departmentId,
    templates_json: templatesJson,
    dry_run: options.dryRun ?? false,
    duplicate_action: options.duplicateAction ?? 'skip',
    force: options.force ?? false,
  })
  return response.data
}

/**
 * Importiert globale Vorlagen aus v4/v5-JSON (Admin)
 */
export async function importGlobalTemplates(
  templatesJson: unknown,
  options: {
    dryRun?: boolean
    duplicateAction?: TemplateImportDuplicateAction
    force?: boolean
  } = {},
): Promise<TemplateImportResponse> {
  const response = await apiClient.post<TemplateImportResponse>('/api/templates/import', {
    scope: 'global',
    templates_json: templatesJson,
    dry_run: options.dryRun ?? false,
    duplicate_action: options.duplicateAction ?? 'skip',
    force: options.force ?? false,
  })
  return response.data
}

/**
 * Exportiert Vorlagen als v5-JSON (Department)
 */
export async function exportTemplates(
  departmentId: string,
  manufacturer?: string,
): Promise<TemplateExportJson> {
  const params = new URLSearchParams({ scope: 'department', department_id: departmentId })
  if (manufacturer) params.append('manufacturer', manufacturer)
  const response = await apiClient.get<TemplateExportJson>(`/api/templates/export?${params.toString()}`)
  return response.data
}

/**
 * Exportiert globale Vorlagen als v5-JSON (Admin)
 */
export async function exportGlobalTemplates(manufacturer?: string): Promise<TemplateExportJson> {
  const params = new URLSearchParams({ scope: 'global' })
  if (manufacturer) params.append('manufacturer', manufacturer)
  const response = await apiClient.get<TemplateExportJson>(`/api/templates/export?${params.toString()}`)
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
    is_container: boolean
    tent_type: string | null
    tent_capacity: number | null
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
