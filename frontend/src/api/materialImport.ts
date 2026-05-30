import apiClient from './apiClient'

export type MaterialImportDuplicateAction = 'add_batch' | 'skip' | 'create'

export interface MaterialImportApiRow {
  row_index: number
  name: string
  qty: number
  color?: string | null
  material?: string | null
  manufacturer?: string | null
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  supplier_name?: string | null
  supplier_id?: string | null
  storage_name?: string | null
  storage_address_id?: string | null
  stock_location_mode?: string | null
  rack_id?: string | null
  rack_name?: string | null
  slot_id?: string | null
  slot_name?: string | null
  container_name?: string | null
  container_batch_id?: string | null
  acquired_on?: string | null
  acquired_year?: string | null
  unit_price?: string | null
  notes?: string | null
  duplicate_action?: MaterialImportDuplicateAction
}

export interface MaterialImportResultRow {
  row_index: number
  status: string
  action: string | null
  errors: string[]
  warnings: string[]
  existing_material_id: string | null
  existing_material_name: string | null
  supplier_resolution: string | null
  supplier_id: string | null
  supplier_label: string | null
  material_public_code?: string | null
  batch_public_code?: string | null
  public_codes_planned?: boolean
}

export interface MaterialImportResponse {
  success: boolean
  dry_run: boolean
  rows: MaterialImportResultRow[]
  stats: {
    created: number
    batches_added: number
    skipped: number
    errors: number
    suppliers_copied: number
    suppliers_created: number
  }
  error?: string
}

export async function importMaterials(
  departmentId: string,
  rows: MaterialImportApiRow[],
  options: {
    dryRun?: boolean
    defaultDuplicateAction?: MaterialImportDuplicateAction
  } = {},
): Promise<MaterialImportResponse> {
  const { data } = await apiClient.post<MaterialImportResponse>('/api/materials/import', {
    department_id: departmentId,
    dry_run: options.dryRun ?? false,
    default_duplicate_action: options.defaultDuplicateAction ?? 'add_batch',
    rows,
  })
  return data
}
