import apiClient from './apiClient'
import type { RentalCalcParams } from '@/utils/rentalPriceAmortization'

// ============== Types ==============

export interface MaterialCategory {
  id: string
  name: string
  parent_id: string | null
}

export interface MaterialStorageAddress {
  id: string
  name: string
  city?: string
}

export interface BatchStorageAllocation {
  id: string
  batch_id: string
  container_batch_id?: string | null
  rack_id: string
  slot_id?: string | null
  /** Lagerstandort (Adresse), zu dem das Gestell gehört */
  storage_address_name?: string | null
  /** Fallback, falls API flache Namen statt rack-Objekt liefert */
  rack_name?: string
  slot_name?: string
  qty: number
  container_batch?: {
    id: string
    material_id?: string | null
    serial_number?: string | null
    label?: string | null
    material_name?: string | null
    storage_address_name?: string | null
    rack?: { id: string; name: string }
    slot?: { id: string; name: string } | null
  } | null
  rack?: { id: string; name: string }
  slot?: { id: string; name: string } | null
}

export interface MaterialBatch {
  id: string
  qty: number
  unit_price: string | null
  acquired_on: string
  expiry_date: string | null
  status: string
  batch_type: string
  is_initial: boolean
  label: string | null
  notes: string | null
  serial_number: string | null
  /** Pro Instanz: Behälter (kann anderen Lagerinhalt aufnehmen) */
  is_container?: boolean
  rack_id?: string | null
  slot_id?: string | null
  rack?: { id: string; name: string } | null
  slot?: { id: string; name: string } | null
  /** Lagerstandort am Batch-Rack (wenn keine Allokationszeilen) */
  storage_address_name?: string | null
  allocations?: BatchStorageAllocation[]
  source_batch_id?: string | null
  conversion_group_id?: string | null
  public_code?: string | null
  public_url?: string | null
}

/** Eintrag in der Stückliste einer physischen/virtuellen Kombination */
export interface ComboComponent {
  id: string
  parent_material_id: string
  component_material: {
    id: string
    name: string
    material_type: string
    tracking_type: string | null
    total_stock: number
  }
  component_batch: {
    id: string
    serial_number: string | null
    label: string | null
    status: string
    qty: number
  } | null
  qty: number
  component_role: string | null
  assignment_mode: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  is_optional: boolean
  sort_order: number
  is_assigned: boolean
  is_awaiting: boolean
  created_at: string
}

export interface Material {
  id: string
  department_id: string
  name: string
  description: string | null
  category: MaterialCategory | null
  storage_address: MaterialStorageAddress | null
  location: string | null
  condition: 'ok' | 'defect' | 'repair' | 'lost'
  material_type: 'physical' | 'physical_combo' | 'virtual_combo'
  tracking_type: 'serialized' | 'bulk' | null
  /** Referenz-Kisten-Batch (phys. Combo aus Kiste); für Plan-vs.-Ist */
  linked_container_batch_id?: string | null
  linked_container_batch?: {
    id: string
    material_id: string
    label: string | null
    serial_number: string | null
    material_name: string
    display_label: string
  } | null
  total_stock: number
  defect_stock: number
  repair_stock: number
  combo_allocated: number
  free_stock: number
  issued_out: number
  reserved: number
  in_warehouse: number
  available: number
  open_loss_reports: number
  open_loss_qty: number
  batch_count: number
  is_container: boolean
  tent_type: string | null
  tent_capacity: number | null
  reservation_mode: string | null
  is_consumable: boolean
  is_food: boolean
  is_js_material?: boolean
  external_source?: string | null
  sale_price: string | null
  /** Referenz-EK/Stk.; bei Verbrauchsmaterial/Esswaren Pflicht */
  reference_purchase_unit_chf?: string | null
  min_stock: number | null
  pack_size: number | null
  pack_unit: string | null
  /** Optional: CHF pro Verpackungseinheit (Aufteilen auf Stückpreis) */
  pack_sale_price_chf?: string | null
  created_at: string
  updated_at: string
  public_code?: string | null
  public_url?: string | null
  
  // Details (nur bei get mit Details)
  color?: string | null
  material?: string | null
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  weight?: string | null
  ean?: string | null
  barcode_tag?: string | null
  manufacturer?: string | null
  model?: string | null
  warranty_until?: string | null
  
  // Verleih
  rental_external_allowed?: boolean
  rental_scope?: string | null
  rental_requires_approval?: boolean
  rental_price_day?: string | null
  rental_price_week?: string | null
  rental_price_month?: string | null
  rental_deposit?: string | null
  rental_lead_days?: number | null
  rental_max_days?: number | null
  rental_notes?: string | null
  /** Eingaben Amortisationsrechner (optional, JSON) */
  rental_calc_params?: RentalCalcParams | null
  
  // Batches
  batches?: MaterialBatch[]

  /** Nur Kombos (physical_combo / virtual_combo), Detail-GET */
  combo_components?: ComboComponent[]
  combo_component_count?: number
}

// Seriennummer-Eintrag für serialisierte Materialien
export interface SerialNumberEntry {
  serial_number: string
  label?: string
  notes?: string
  is_container?: boolean
  rack_id?: string
  slot_id?: string
  container_batch_id?: string
}

export interface CreateMaterialRequest {
  department_id: string
  name: string
  description?: string | null
  category_id?: string | null
  storage_address_id?: string | null
  location?: string | null
  
  // Material- und Tracking-Typ
  material_type?: 'physical' | 'physical_combo' | 'virtual_combo'
  tracking_type?: 'serialized' | 'bulk' | null
  reservation_mode?: string | null
  
  // Initialer Batch (für Massenartikel)
  initial_qty?: number
  initial_acquired_on?: string
  initial_expiry_date?: string
  initial_unit_price?: string | null
  initial_supplier_id?: string | null
  initial_rack_id?: string | null
  initial_slot_id?: string | null
  initial_container_batch_id?: string | null
  initial_allocations?: { rack_id?: string; slot_id?: string; container_batch_id?: string; qty: number }[]
  
  // Seriennummern (für serialisierte Artikel)
  serial_numbers?: SerialNumberEntry[]
  
  // Details
  is_container?: boolean
  is_consumable?: boolean
  is_food?: boolean
  sale_price?: string | null
  reference_purchase_unit_chf?: string | null
  min_stock?: number | null
  pack_size?: number | null
  pack_unit?: string | null
  pack_sale_price_chf?: string | null
  color?: string | null
  material?: string | null
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  weight?: string | null
  ean?: string | null
  barcode_tag?: string | null
  manufacturer?: string | null
  model?: string | null
  warranty_until?: string | null
  
  // Verleih
  rental_external_allowed?: boolean
  rental_scope?: string | null
  rental_requires_approval?: boolean
  rental_price_day?: string | null
  rental_price_week?: string | null
  rental_price_month?: string | null
  rental_deposit?: string | null
  rental_lead_days?: number | null
  rental_max_days?: number | null
  rental_notes?: string | null
  rental_calc_params?: RentalCalcParams | null
  is_js_material?: boolean
  external_source?: string | null
}

export interface UpdateMaterialRequest {
  name?: string
  description?: string | null
  category_id?: string | null
  storage_address_id?: string | null
  location?: string | null
  condition?: string
  
  // Details
  is_container?: boolean
  is_consumable?: boolean
  is_food?: boolean
  reservation_mode?: string | null
  sale_price?: string | null
  reference_purchase_unit_chf?: string | null
  min_stock?: number | null
  pack_size?: number | null
  pack_unit?: string | null
  pack_sale_price_chf?: string | null
  color?: string | null
  material?: string | null
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  weight?: string | null
  ean?: string | null
  barcode_tag?: string | null
  manufacturer?: string | null
  model?: string | null
  warranty_until?: string | null
  
  // Verleih
  rental_external_allowed?: boolean
  rental_scope?: string | null
  rental_requires_approval?: boolean
  rental_price_day?: string | null
  rental_price_week?: string | null
  rental_price_month?: string | null
  rental_deposit?: string | null
  rental_lead_days?: number | null
  rental_max_days?: number | null
  rental_notes?: string | null
  rental_calc_params?: RentalCalcParams | null
  is_js_material?: boolean
  external_source?: string | null
}

// ============== API Functions ==============

/**
 * Lädt alle Materialien für ein Department
 */
export async function getMaterials(
  departmentId: string,
  options?: {
    search?: string
    category_id?: string
    material_source?: 'internal' | 'js' | 'all'
    include_global_js?: boolean
  }
): Promise<Material[]> {
  const params = new URLSearchParams({ department_id: departmentId })
  
  if (options?.search) {
    params.append('search', options.search)
  }
  if (options?.category_id) {
    params.append('category_id', options.category_id)
  }
  if (options?.material_source) {
    params.append('material_source', options.material_source)
  }
  if (typeof options?.include_global_js === 'boolean') {
    params.append('include_global_js', options.include_global_js ? '1' : '0')
  }
  
  const response = await apiClient.get<Material[]>(`/api/materials?${params.toString()}`)
  return response.data
}

/**
 * Lädt ein einzelnes Material mit Details
 */
export async function getMaterial(id: string): Promise<Material> {
  const response = await apiClient.get<Material>(`/api/materials/${id}`)
  return response.data
}

/**
 * Erstellt ein neues Material
 */
export async function createMaterial(data: CreateMaterialRequest): Promise<Material> {
  const response = await apiClient.post<Material>('/api/materials', data)
  return response.data
}

export interface CreateComboFromRackRequest {
  rack_id: string
  name: string
  department_id: string
  material_type?: 'physical_combo' | 'virtual_combo'
  category_id?: string | null
  storage_address_id?: string | null
  reservation_mode?: string
  purchase_date?: string
}

/**
 * Erstellt eine Combo aus dem Inhalt eines Lagerplatzes
 */
export async function createComboFromRack(data: CreateComboFromRackRequest): Promise<Material> {
  const response = await apiClient.post<Material>('/api/materials/create-combo-from-rack', data)
  return response.data
}

export interface CreateComboFromContainerBatchRequest {
  container_batch_id: string
  name: string
  department_id: string
  material_type?: 'physical_combo' | 'virtual_combo'
  category_id?: string | null
  storage_address_id?: string | null
  reservation_mode?: string
  purchase_date?: string
  /** Physische Kombi: Lagerung des Sets – Gestell/Fach … */
  initial_rack_id?: string
  initial_slot_id?: string
  /** … oder Kiste/Tasche */
  initial_container_batch_id?: string
}

/** Erstellt eine Combo aus dem Inhalt einer Kiste (Container-Batch). */
export async function createComboFromContainerBatch(
  data: CreateComboFromContainerBatchRequest
): Promise<Material> {
  const response = await apiClient.post<Material>('/api/materials/create-combo-from-container-batch', data)
  return response.data
}

/**
 * Aktualisiert ein Material
 */
export async function updateMaterial(id: string, data: UpdateMaterialRequest): Promise<Material> {
  const response = await apiClient.patch<Material>(`/api/materials/${id}`, data)
  return response.data
}

/**
 * Backfill: erzeugt einen Public-Code für ein Material, falls keiner existiert.
 */
export async function ensureMaterialPublicCode(id: string): Promise<Material> {
  const response = await apiClient.post<Material>(`/api/materials/${id}/public-code`)
  return response.data
}

/** Physische Kombi, die diese Kisten-Charge als Referenz nutzt (für Warnung beim Befüllen). */
export async function getLinkedPhysicalComboForContainerBatch(
  containerBatchId: string
): Promise<{ id: string; name: string } | null> {
  const response = await apiClient.get<{ physical_combo: { id: string; name: string } | null }>(
    `/api/materials/container-batch/${encodeURIComponent(containerBatchId)}/linked-physical-combo`
  )
  return response.data.physical_combo ?? null
}

/**
 * Löscht ein Material (Soft-Delete)
 */
export async function deleteMaterial(id: string): Promise<void> {
  await apiClient.delete(`/api/materials/${id}`)
}

// ============== History Functions ==============

export interface MaterialHistoryChange {
  old: any
  new: any
}

export interface MaterialHistoryEntry {
  id: string
  action: 'created' | 'updated' | 'deleted'
  snapshot: Record<string, any>
  changes: Record<string, MaterialHistoryChange>
  created_at: string
  user: {
    id: string
    name: string
  } | null
}

/**
 * Lädt die Änderungshistorie eines Materials
 */
export async function getMaterialHistory(materialId: string): Promise<MaterialHistoryEntry[]> {
  const response = await apiClient.get<MaterialHistoryEntry[]>(`/api/materials/${materialId}/history`)
  return response.data
}

// ============== Batch Functions ==============

export interface AddBatchRequest {
  qty: number
  acquired_on?: string
  expiry_date?: string | null
  unit_price?: string | null
  supplier_id?: string | null
  notes?: string | null
  label?: string | null
  rack_id?: string | null
  slot_id?: string | null
  is_container?: boolean
  allocations?: { rack_id?: string; slot_id?: string; container_batch_id?: string; qty: number }[]
  // Serialisiert + qty > 1:
  serial_numbers?: string[]
  serial_entries?: { serial_number: string; label?: string; is_container?: boolean }[]
  serial_prefix?: string
  start_number?: number
  pad_length?: number
  serial_allocations?: { serial_number: string; rack_id?: string; slot_id?: string; container_batch_id?: string }[]
  create_slot_per_serial?: boolean
  container_batch_id?: string
}

export interface UpdateBatchRequest {
  qty?: number
  unit_price?: string | null
  status?: string
  notes?: string | null
  label?: string | null
  serial_number?: string | null
  supplier_id?: string | null
  rack_id?: string | null
  slot_id?: string | null
  is_container?: boolean
}

export interface SplitToSerializedRequest {
  quantity: number
  source_batch_id?: string
  serial_numbers?: string[]
  serial_entries?: { serial_number: string; label?: string; create_slot?: boolean }[]
  serial_prefix?: string
  start_number?: number
  pad_length?: number
  rack_id?: string | null
  slot_id?: string | null
  serial_allocations?: { serial_number: string; rack_id?: string; slot_id?: string; container_batch_id?: string }[]
  create_slot_per_serial?: boolean
  container_batch_id?: string
}

export interface SplitToSerializedResponse {
  success: boolean
  material_id: string
  source_batch_id: string
  source_batch_qty_remaining: number
  created_count: number
  conversion_group_id: string
  created_batches: Array<{ id: string; serial_number: string }>
}

export interface AddBatchMultiResponse {
  created_count: number
  created_batches: Array<{
    id: string
    qty: number
    serial_number: string
    label?: string | null
    rack_id?: string | null
    slot_id?: string | null
    container_batch_id?: string | null
    public_code?: string | null
    public_url?: string | null
  }>
}

/**
 * Fügt einen neuen Batch zu einem existierenden Material hinzu.
 * Bei serialisiert + qty > 1 mit serial_entries/serial_prefix: liefert AddBatchMultiResponse.
 */
export async function addBatch(
  materialId: string,
  data: AddBatchRequest
): Promise<MaterialBatch | AddBatchMultiResponse> {
  const response = await apiClient.post<MaterialBatch | AddBatchMultiResponse>(
    `/api/materials/${materialId}/batches`,
    data
  )
  return response.data
}

/**
 * Aktualisiert einen Batch (acquired_on ist NICHT änderbar!)
 */
export async function updateBatch(materialId: string, batchId: string, data: UpdateBatchRequest): Promise<MaterialBatch> {
  const response = await apiClient.patch<MaterialBatch>(`/api/materials/${materialId}/batches/${batchId}`, data)
  return response.data
}

export interface MoveBatchRequest {
  from_allocation_id?: string | null
  to_rack_id?: string | null
  to_slot_id?: string | null
  to_container_batch_id?: string | null
  qty: number
}

export interface MoveBatchResponse {
  id: string
  qty: number
  rack_id: string | null
  slot_id: string | null
  allocations?: Array<{
    id: string
    container_batch_id?: string | null
    rack_id: string | null
    slot_id: string | null
    qty: number
    container_batch?: {
      id: string
      serial_number?: string | null
      label?: string | null
      material_name?: string | null
      rack?: { id: string; name: string }
      slot?: { id: string; name: string } | null
    } | null
  }>
}

export async function moveBatchQuantity(
  materialId: string,
  batchId: string,
  data: MoveBatchRequest
): Promise<MoveBatchResponse> {
  const response = await apiClient.post<MoveBatchResponse>(
    `/api/materials/${materialId}/batches/${batchId}/move`,
    data
  )
  return response.data
}

export async function splitToSerialized(materialId: string, data: SplitToSerializedRequest): Promise<SplitToSerializedResponse> {
  const response = await apiClient.post<SplitToSerializedResponse>(`/api/materials/${materialId}/split-to-serialized`, data)
  return response.data
}

// ============== Used-In (Reverse Combo Lookup) ==============

export interface UsedInEntry {
  combo_id: string
  combo_name: string
  material_type: 'physical_combo' | 'virtual_combo'
  assignment_mode: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  component_role: string | null
  batch_id: string | null
  batch_serial: string | null
  qty: number
  is_optional: boolean
}

/**
 * Lädt alle Kombos, in denen ein Material als Komponente verwendet wird
 */
export async function getMaterialUsedIn(materialId: string): Promise<UsedInEntry[]> {
  const response = await apiClient.get<UsedInEntry[]>(`/api/materials/${materialId}/used-in`)
  return response.data
}

/** Ein Lagerort (Gestell/Fach/Kiste) für GET /materials/:id/storage-locations */
export interface MaterialStorageLocationRow {
  rack_id: string | null
  slot_id: string | null
  rack_name: string | null
  slot_name: string | null
  storage_address_name: string | null
  location_label: string | null
  qty: number
  batch_id: string
  container_caption: string | null
}

export interface MaterialStorageViaComboBlock {
  /** Stücklisten-Zeile (material_combo_component.id) */
  combo_component_id?: string
  parent_material_id: string
  parent_name: string
  /** Wenn gesetzt: gilt nur für diese konkrete Serien-Charge (Komponente fest zugewiesen). */
  component_batch_id: string | null
  component_qty: number
  assignment_mode: string
  locations: MaterialStorageLocationRow[]
}

export interface MaterialStorageLocationsResponse {
  direct: MaterialStorageLocationRow[]
  via_physical_combo: MaterialStorageViaComboBlock[]
}

export async function getMaterialStorageLocations(
  materialId: string,
  departmentId: string
): Promise<MaterialStorageLocationsResponse> {
  const response = await apiClient.get<MaterialStorageLocationsResponse>(
    `/api/materials/${encodeURIComponent(materialId)}/storage-locations`,
    { params: { department_id: departmentId } }
  )
  return response.data
}

// ============== Combo-Component Types ==============

export interface AddComboComponentRequest {
  component_material_id: string
  component_batch_id?: string | null
  qty?: number
  component_role?: string
  assignment_mode?: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  is_optional?: boolean
  sort_order?: number
}

export interface UpdateComboComponentRequest {
  component_batch_id?: string | null
  qty?: number
  assignment_mode?: string
  component_role?: string
  is_optional?: boolean
  sort_order?: number
}

// ============== Combo-Component API Functions ==============

/**
 * Lädt die Combo-Komponenten eines Materials
 */
export async function getComboComponents(materialId: string): Promise<ComboComponent[]> {
  const response = await apiClient.get<ComboComponent[]>(`/api/materials/${materialId}/components`)
  return response.data
}

/**
 * Fügt eine Komponente zu einem Combo-Material hinzu
 */
export async function addComboComponent(materialId: string, data: AddComboComponentRequest): Promise<ComboComponent> {
  const response = await apiClient.post<ComboComponent>(`/api/materials/${materialId}/components`, data)
  return response.data
}

/**
 * Aktualisiert eine Combo-Komponente (z.B. Batch zuweisen)
 */
export async function updateComboComponent(
  materialId: string,
  componentId: string,
  data: UpdateComboComponentRequest
): Promise<ComboComponent> {
  const response = await apiClient.patch<ComboComponent>(
    `/api/materials/${materialId}/components/${componentId}`,
    data
  )
  return response.data
}

/**
 * Entfernt eine Combo-Komponente
 */
export async function deleteComboComponent(materialId: string, componentId: string): Promise<void> {
  await apiClient.delete(`/api/materials/${materialId}/components/${componentId}`)
}
