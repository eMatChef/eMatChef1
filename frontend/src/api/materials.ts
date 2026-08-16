import apiClient from './apiClient'
import type { MediaPhoto } from './media'
import { uploadMediaFile } from './media'
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
  ean?: string | null
  barcode_tag?: string | null
  /** Länge dieser Charge in cm (Meterware; kann vom Stamm abweichen) */
  size_length?: string | null
  /** VE dieser Charge: bei Meterware = Meter pro VE */
  pack_size?: number | null
  /** VE-Bezeichnung dieser Charge (z. B. Rolle) */
  pack_unit?: string | null
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
    public_code?: string | null
    public_url?: string | null
  } | null
  qty: number
  component_role: string | null
  assignment_mode: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  is_optional: boolean
  /** Komponenten-Quelle (Weg B): aus Lager (reserviert) vs. vom Leiter selbst mitgebracht. */
  component_source: ComponentSource
  sort_order: number
  is_assigned: boolean
  is_awaiting: boolean
  created_at: string
}

/**
 * Komponenten-Quelle einer Stücklisten-/Delta-Zeile (Weg B):
 * - stock: aus dem Lager, wird reserviert und zählt im Flaschenhals.
 * - self_provided: vom Leiter selbst zu organisieren (z. B. Mast) – nicht reserviert, nur Checkliste.
 */
export type ComponentSource = 'stock' | 'self_provided'

/** Anzeige-Modus einer Option (entkoppelt von den Deltas). */
export type OptionDisplayMode = 'toggle' | 'group'

/** Auswahlregel einer Options-Gruppe. */
export type OptionSelectionType = 'exclusive' | 'multi' | 'quantity'

/** ±Stücklisten-Zeile einer Option (Weg B, README Abschnitt 6). */
export interface ComboOptionDelta {
  id: string
  option_id: string
  component_material: {
    id: string
    name: string
    material_type: string
    tracking_type: string | null
    total_stock: number
  }
  /** ±Menge (signed), z. B. +1, −12 */
  qty_delta: number
  assignment_mode: 'on_issue' | 'bulk'
  tracking: string | null
  component_source: ComponentSource
  sort_order: number
}

/** Eine wählbare Option einer virtuellen Kombo. */
export interface ComboOption {
  id: string
  material_item_id: string
  option_group_id: string | null
  name: string
  display_mode: OptionDisplayMode
  default_selected: boolean
  sort_order: number
  deltas: ComboOptionDelta[]
}

/** Auswahl-Gruppe (Paket 6-UI; Schema bereits in Paket 5). */
export interface ComboOptionGroup {
  id: string
  material_item_id: string
  name: string
  selection_type: OptionSelectionType
  min_select: number
  max_select: number | null
  sort_order: number
}

/** Verwandtes Zubehör: Empfehlungs-Verknüpfung (kein Stücklisten-Teil) */
export interface RelatedAccessory {
  id: string
  material_id: string
  accessory_material: {
    id: string
    name: string
    material_type: string
    tracking_type: string | null
    total_stock: number
  }
  sort_order: number
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
    public_code?: string | null
    public_url?: string | null
  } | null
  total_stock: number
  defect_stock: number
  repair_stock: number
  combo_allocated: number
  /** Aufteilung pro Kombi-Elternartikel (Stückliste) */
  combo_allocations?: ComboAllocationBreakdown[]
  free_stock: number
  /** Aktiver Bestand am Regal/Fach (ohne Kisten-Allokation) */
  stock_outside_containers?: number
  /** Aktiver Bestand in Kisten/Taschen gebucht */
  stock_in_containers?: number
  issued_out: number
  reserved: number
  in_warehouse: number
  available: number
  open_loss_reports: number
  open_loss_qty: number
  batch_count: number
  /** Frühestes Ablaufdatum aktiver Chargen (Y-m-d), null wenn keines */
  nearest_expiry_date?: string | null
  is_container: boolean
  tent_type: string | null
  repair_template_key?: string | null
  tent_capacity: number | null
  /** Entwurfs-Status für Kombos: 'draft' (in Bearbeitung, nicht buchbar) | 'ready' */
  combo_status: 'draft' | 'ready'
  is_consumable: boolean
  is_food: boolean
  is_js_material?: boolean
  external_source?: string | null
  sale_price: string | null
  external_sale_price_chf?: string | null
  /** Referenz-EK/Stk.; bei Verbrauchsmaterial/Esswaren Pflicht */
  reference_purchase_unit_chf?: string | null
  min_stock: number | null
  pack_size: number | null
  pack_unit: string | null
  /** VE-Bezeichnung bei Meterware (pack_unit=m), z. B. Rolle */
  packaging_unit?: string | null
  /** Optional: CHF pro Verpackungseinheit (Aufteilen auf Stückpreis) */
  pack_sale_price_chf?: string | null
  pack_weight?: string | null
  pack_size_length?: string | null
  pack_size_width?: string | null
  pack_size_height?: string | null
  created_at: string
  updated_at: string
  public_code?: string | null
  public_url?: string | null
  image_url?: string | null
  photos?: MediaPhoto[] | null
  
  // Details (nur bei get mit Details)
  color?: string | null
  material?: string | null
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  weight?: string | null
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

  /** Optionen einer virtuellen Kombo (Weg B); Toggle in Paket 5, Gruppen in Paket 6. */
  combo_options?: ComboOption[]
  combo_option_groups?: ComboOptionGroup[]

  /** Verwandtes Zubehör (Empfehlung, separate Position), Detail-GET */
  related_accessories?: RelatedAccessory[]
}

// Seriennummer-Eintrag für serialisierte Materialien
export interface SerialNumberEntry {
  serial_number: string
  label?: string
  notes?: string
  ean?: string | null
  barcode_tag?: string | null
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
  
  // Initialer Batch (für Massenartikel)
  initial_qty?: number
  initial_acquired_on?: string
  initial_expiry_date?: string
  initial_unit_price?: string | null
  initial_supplier_id?: string | null
  initial_ean?: string | null
  initial_barcode_tag?: string | null
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
  external_sale_price_chf?: string | null
  reference_purchase_unit_chf?: string | null
  min_stock?: number | null
  pack_size?: number | null
  pack_unit?: string | null
  packaging_unit?: string | null
  pack_sale_price_chf?: string | null
  pack_weight?: string | null
  pack_size_length?: string | null
  pack_size_width?: string | null
  pack_size_height?: string | null
  color?: string | null
  material?: string | null
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  weight?: string | null
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
  sale_price?: string | null
  external_sale_price_chf?: string | null
  reference_purchase_unit_chf?: string | null
  min_stock?: number | null
  pack_size?: number | null
  pack_unit?: string | null
  packaging_unit?: string | null
  pack_sale_price_chf?: string | null
  pack_weight?: string | null
  pack_size_length?: string | null
  pack_size_width?: string | null
  pack_size_height?: string | null
  color?: string | null
  material?: string | null
  size_length?: string | null
  size_width?: string | null
  size_height?: string | null
  weight?: string | null
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

export interface CreateComboManualComponentInput {
  material_id?: string
  name?: string
  tracking_type?: 'serialized' | 'bulk'
  mode?: 'new' | 'existing'
  qty?: number
  serial_number?: string
  unit_price?: string
  batch_id?: string
  /** Referenz-Sack/Kiste dieser physischen Kombination (neu oder aus Lager). */
  is_linked_container?: boolean
  /** stock = aus Lager; self_provided = Leiter organisiert selbst (nur virtuelle Kombo). */
  component_source?: 'stock' | 'self_provided'
}

export interface CreateComboManualRequest {
  department_id: string
  name: string
  material_type?: 'physical_combo' | 'virtual_combo'
  category_id?: string | null
  storage_address_id?: string | null
  purchase_date?: string
  supplier_id?: string | null
  initial_rack_id?: string
  initial_slot_id?: string
  initial_container_batch_id?: string
  components: CreateComboManualComponentInput[]
}

/** Manuelle Combo (Stückliste ohne Vorlage/Kiste). */
export async function createComboManual(data: CreateComboManualRequest): Promise<Material> {
  const response = await apiClient.post<Material>('/api/materials/create-combo-manual', data)
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

/** POST /api/materials/{materialId}/photos — Primary-Abbildung (ersetzt bestehendes Foto) */
export async function uploadMaterialPhoto(
  materialId: string,
  file: File,
): Promise<{ photos: MediaPhoto[]; image_url: string | null }> {
  const { data } = await uploadMediaFile<{
    photos: MediaPhoto[]
    image_url: string | null
  }>(`/api/materials/${materialId}/photos`, file)
  return { photos: data.photos, image_url: data.image_url }
}

/** POST /api/materials/{materialId}/photos/from-url — Bild von URL importieren (lokal speichern) */
export async function importMaterialPhotoFromUrl(
  materialId: string,
  url: string,
): Promise<{ photos: MediaPhoto[]; image_url: string | null }> {
  const { data } = await apiClient.post<{
    photos: MediaPhoto[]
    image_url: string | null
  }>(`/api/materials/${materialId}/photos/from-url`, { url })
  return { photos: data.photos, image_url: data.image_url }
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


export interface LookupBatchByCodeResponse {
  match_type: 'ean' | 'barcode_tag'
  entity_type: 'batch'
  material_code?: string | null
  batch_code?: string | null
  public_url?: string | null
  batch: {
    id: string
    serial_number?: string | null
    label?: string | null
    status?: string | null
    is_container?: boolean
    ean?: string | null
    barcode_tag?: string | null
  }
  material: {
    id: string
    name: string
    description?: string | null
    manufacturer?: string | null
    model?: string | null
    is_container?: boolean
  }
  department: {
    id: string
    name?: string | null
  }
}

export async function lookupMaterialByScanCode(
  departmentId: string,
  code: string,
): Promise<LookupBatchByCodeResponse> {
  const response = await apiClient.get<LookupBatchByCodeResponse>('/api/materials/lookup-by-code', {
    params: { department_id: departmentId, code },
  })
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
  ean?: string | null
  barcode_tag?: string | null
  /** Länge dieser Charge in cm (Meterware) */
  size_length?: string | null
  pack_size?: number | null
  /** VE-Bezeichnung der Charge (Rolle …); Alias packaging_unit */
  packaging_unit?: string | null
  batch_pack_unit?: string | null
  batch_pack_size?: number | null
  rack_id?: string | null
  slot_id?: string | null
  is_container?: boolean
  allocations?: { rack_id?: string; slot_id?: string; container_batch_id?: string; qty: number }[]
  // Serialisiert + qty > 1:
  serial_numbers?: string[]
  serial_entries?: { serial_number: string; label?: string; ean?: string; barcode_tag?: string; is_container?: boolean }[]
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
  ean?: string | null
  barcode_tag?: string | null
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
 * Lädt Chargen eines Materials (für Listen-Expand).
 */
export async function getMaterialBatches(materialId: string): Promise<MaterialBatch[]> {
  const response = await apiClient.get<MaterialBatch[]>(`/api/materials/${materialId}/batches`)
  return Array.isArray(response.data) ? response.data : []
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

export interface MaterialActivityBookingRow {
  activity_id: string
  activity_no: number | null
  activity_name: string
  activity_status: string
  activity_type: string
  usage_start: string | null
  usage_end: string | null
  qty: number
  booking_kind: 'issued' | 'reserved' | 'draft'
  /** Komma-getrennte Kombi-Namen, wenn die Menge über eine gebuchte Kombo entsteht */
  via_combo_material_names?: string | null
}

export async function getMaterialActivityBookings(
  materialId: string,
  departmentId: string,
): Promise<MaterialActivityBookingRow[]> {
  const response = await apiClient.get<MaterialActivityBookingRow[]>(
    `/api/materials/${encodeURIComponent(materialId)}/activity-bookings`,
    { params: { department_id: departmentId } },
  )
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
  serial_number?: string | null
  batch_label?: string | null
  container_batch_id?: string | null
  container_caption: string | null
  container_material_name?: string | null
}

export interface MaterialStorageViaComboBlock {
  /** Stücklisten-Zeile (material_combo_component.id) */
  combo_component_id?: string
  parent_material_id: string
  parent_name: string
  /** Referenz-Kiste der physischen Kombi (material_item.linked_container_batch_id). */
  parent_linked_container_batch_id?: string | null
  /** Wenn gesetzt: gilt nur für diese konkrete Serien-Charge (Komponente fest zugewiesen). */
  component_batch_id: string | null
  /** Soll-Menge laut Stückliste (Tab Zusammensetzung). */
  component_qty: number
  /** Physisch in der verknüpften Kiste gebucht (Summe Allokationen). */
  stored_qty_in_container?: number
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

export interface ComboAllocationBreakdown {
  parent_material_id: string
  parent_name: string
  qty: number
}

export interface AddComboComponentRequest {
  component_material_id: string
  component_batch_id?: string | null
  qty?: number
  component_role?: string | null
  assignment_mode?: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  is_optional?: boolean
  component_source?: ComponentSource
  sort_order?: number
  /** Phys. Kombi: Bestand in verknüpfte Kiste buchen (Standard: true) */
  allocate_to_linked_container?: boolean
}

export interface UpdateComboComponentRequest {
  component_batch_id?: string | null
  qty?: number
  assignment_mode?: string
  component_role?: string | null
  is_optional?: boolean
  component_source?: ComponentSource
  sort_order?: number
  /** Phys. Kombi: Mehr-Menge in verknüpfte Kiste buchen (Standard: true) */
  allocate_to_linked_container?: boolean
  /** Phys. Kombi: diese Komponenten-Charge als Referenz-Sack/Kiste der Kombination setzen */
  set_as_linked_container?: boolean
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

export interface DeleteComboComponentRequest {
  release_to_rack_id?: string | null
  release_to_slot_id?: string | null
  release_to_container_batch_id?: string | null
}

/**
 * Entfernt eine Combo-Komponente (optional mit Ziel für Bestand aus verknüpfter Kiste)
 */
export async function deleteComboComponent(
  materialId: string,
  componentId: string,
  data?: DeleteComboComponentRequest,
): Promise<void> {
  await apiClient.delete(`/api/materials/${materialId}/components/${componentId}`, { data })
}

/**
 * Kombo fertigstellen: Status draft → ready (Mindest-Validierung ≥ 1 Pflichtteil im Backend).
 */
export async function finalizeCombo(materialId: string): Promise<Material> {
  const response = await apiClient.post<Material>(`/api/materials/${materialId}/finalize-combo`)
  return response.data
}

/**
 * Kombo zurück auf Entwurf: Status ready → draft (nicht neu buchbar).
 */
export async function unfinalizeCombo(materialId: string): Promise<Material> {
  const response = await apiClient.post<Material>(`/api/materials/${materialId}/unfinalize-combo`)
  return response.data
}

// ============== Related-Accessory API Functions ==============

/**
 * Lädt das verwandte Zubehör eines Materials (Empfehlung, kein Stücklisten-Teil).
 */
export async function getRelatedAccessories(materialId: string): Promise<RelatedAccessory[]> {
  const response = await apiClient.get<RelatedAccessory[]>(`/api/materials/${materialId}/related-accessories`)
  return response.data
}

/**
 * Verknüpft ein verwandtes Zubehör mit einem Material.
 */
export async function addRelatedAccessory(
  materialId: string,
  data: { accessory_material_id: string; sort_order?: number },
): Promise<RelatedAccessory> {
  const response = await apiClient.post<RelatedAccessory>(`/api/materials/${materialId}/related-accessories`, data)
  return response.data
}

/**
 * Entfernt eine verwandtes-Zubehör-Verknüpfung.
 */
export async function deleteRelatedAccessory(materialId: string, accessoryId: string): Promise<void> {
  await apiClient.delete(`/api/materials/${materialId}/related-accessories/${accessoryId}`)
}

// ============== Konfigurator: Options-Gruppen & Optionen (Weg B, Paket 6) ==============

export interface UpsertOptionGroupRequest {
  name?: string
  selection_type?: OptionSelectionType
  min_select?: number
  max_select?: number | null
  sort_order?: number
}

export interface UpsertOptionDeltaRequest {
  component_material_id: string
  qty_delta: number
  assignment_mode?: 'on_issue' | 'bulk'
  tracking?: string | null
  component_source?: ComponentSource
  sort_order?: number
}

export interface UpsertOptionRequest {
  name?: string
  display_mode?: OptionDisplayMode
  default_selected?: boolean
  option_group_id?: string | null
  sort_order?: number
  deltas?: UpsertOptionDeltaRequest[]
}

export async function addComboOptionGroup(materialId: string, data: UpsertOptionGroupRequest): Promise<ComboOptionGroup> {
  const response = await apiClient.post<ComboOptionGroup>(`/api/materials/${materialId}/option-groups`, data)
  return response.data
}

export async function updateComboOptionGroup(materialId: string, groupId: string, data: UpsertOptionGroupRequest): Promise<ComboOptionGroup> {
  const response = await apiClient.patch<ComboOptionGroup>(`/api/materials/${materialId}/option-groups/${groupId}`, data)
  return response.data
}

export async function deleteComboOptionGroup(materialId: string, groupId: string): Promise<void> {
  await apiClient.delete(`/api/materials/${materialId}/option-groups/${groupId}`)
}

export async function addComboOption(materialId: string, data: UpsertOptionRequest): Promise<ComboOption> {
  const response = await apiClient.post<ComboOption>(`/api/materials/${materialId}/options`, data)
  return response.data
}

export async function updateComboOption(materialId: string, optionId: string, data: UpsertOptionRequest): Promise<ComboOption> {
  const response = await apiClient.patch<ComboOption>(`/api/materials/${materialId}/options/${optionId}`, data)
  return response.data
}

export async function deleteComboOption(materialId: string, optionId: string): Promise<void> {
  await apiClient.delete(`/api/materials/${materialId}/options/${optionId}`)
}

/** 3-Zustands-Modell pro Option (README Abschnitt 6). */
export type OptionAvailabilityState = 'available' | 'blocked' | 'missing'

export interface ConfiguratorComponentAvailability {
  materialItemId: string
  name: string
  qtyPerCombo?: number
  qtyDelta?: number
  availableForPeriod: number
  inStock: boolean
}

export interface ConfiguratorOptionAvailability {
  optionId: string
  name: string
  displayMode: OptionDisplayMode
  optionGroupId: string | null
  defaultSelected: boolean
  state: OptionAvailabilityState
  buildable: number | null
  addedStockComponents: ConfiguratorComponentAvailability[]
}

export interface ConfiguratorAvailabilityGroup {
  id: string
  name: string
  selectionType: OptionSelectionType
  minSelect: number
  maxSelect: number | null
  sortOrder: number
}

export interface ConfiguratorAvailability {
  comboId: string
  quantity: number
  groups: ConfiguratorAvailabilityGroup[]
  base: {
    components: ConfiguratorComponentAvailability[]
    buildable: number | null
    blocked: boolean
  }
  options: ConfiguratorOptionAvailability[]
  selected: {
    selectedOptionIds: string[]
    components: ConfiguratorComponentAvailability[]
    buildable: number | null
    blocked: boolean
    selfProvided: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  }
}

export interface ConfiguratorAvailabilityParams {
  startDate?: string | null
  endDate?: string | null
  quantity?: number
  excludeActivityId?: string | null
  selectedOptionIds?: string[]
}

export async function getConfiguratorAvailability(
  comboId: string,
  params: ConfiguratorAvailabilityParams = {},
): Promise<ConfiguratorAvailability> {
  const query: Record<string, string> = {}
  if (params.startDate) query.startDate = params.startDate
  if (params.endDate) query.endDate = params.endDate
  if (params.quantity != null) query.quantity = String(params.quantity)
  if (params.excludeActivityId) query.excludeActivityId = params.excludeActivityId
  if (params.selectedOptionIds && params.selectedOptionIds.length > 0) {
    query.selectedOptionIds = params.selectedOptionIds.join(',')
  }
  const response = await apiClient.get<ConfiguratorAvailability>(
    `/api/materials/${comboId}/configurator-availability`,
    { params: query },
  )
  return response.data
}
