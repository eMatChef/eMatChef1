import apiClient from '@/api/apiClient'

/** Sichtbarkeit auf der öffentlichen QR-Seite (Department-Einstellungen). */
export interface PublicQrPageUi {
  show_contact_form: boolean
  show_contact_email: boolean
  show_contact_note: boolean
  /** Ob eine Nachricht zugestellt werden kann (E-Mail/ Fallback), unabhängig von der Anzeige der Adresse */
  can_deliver_message?: boolean
}

export interface PublicLookupMaterialResponse {
  code: string
  entity_type: 'material'
  material: {
    id: string
    name: string
    description?: string | null
    manufacturer?: string | null
    model?: string | null
  }
  department: {
    id: string
    name: string
  }
  contact?: {
    email?: string | null
  } | null
  contact_note?: string | null
  public_ui?: PublicQrPageUi
}

export async function getPublicMaterialByCode(code: string): Promise<PublicLookupMaterialResponse> {
  const response = await apiClient.get<PublicLookupMaterialResponse>(
    `/api/public/lookup/m/${encodeURIComponent(code)}`
  )
  return response.data
}

export interface PublicLookupBatchResponse {
  code: string
  entity_type: 'batch'
  material_code?: string
  batch_code?: string
  public_url?: string
  batch: {
    id: string
    serial_number?: string | null
    label?: string | null
    status?: string | null
  }
  material: {
    id: string
    name: string
    description?: string | null
    manufacturer?: string | null
    model?: string | null
  }
  department: {
    id: string
    name: string
  }
  contact?: {
    email?: string | null
  } | null
  contact_note?: string | null
  public_ui?: PublicQrPageUi
}

export async function getPublicBatchByCode(code: string): Promise<PublicLookupBatchResponse> {
  const response = await apiClient.get<PublicLookupBatchResponse>(
    `/api/public/lookup/b/${encodeURIComponent(code)}`
  )
  return response.data
}

export interface PublicLookupActivityResponse {
  code: string
  entity_type: 'activity'
  public_url?: string
  activity: {
    id: string
    name: string
    type: string
    usage_start?: string | null
    usage_end?: string | null
    planning_start?: string | null
    planning_end?: string | null
  }
  department: {
    id: string
    name: string
  }
  contact?: {
    email?: string | null
  } | null
  contact_note?: string | null
  public_ui?: PublicQrPageUi
}

export async function getPublicActivityByCode(code: string): Promise<PublicLookupActivityResponse> {
  const response = await apiClient.get<PublicLookupActivityResponse>(
    `/api/public/lookup/a/${encodeURIComponent(code)}`
  )
  return response.data
}

export interface PublicLookupWorkshopResponse {
  code: string
  entity_type: 'workshop'
  public_url?: string
  workshop: {
    id: string
    title: string
    type: string
    status: string
    material_name: string
  }
  department: {
    id: string
    name: string
  }
  contact?: {
    email?: string | null
  } | null
  contact_note?: string | null
  public_ui?: PublicQrPageUi
}

export async function getPublicWorkshopByCode(code: string): Promise<PublicLookupWorkshopResponse> {
  const response = await apiClient.get<PublicLookupWorkshopResponse>(
    `/api/public/lookup/w/${encodeURIComponent(code)}`
  )
  return response.data
}

export async function getPublicMaterialBatchByCodes(
  materialCode: string,
  batchCode: string
): Promise<PublicLookupBatchResponse> {
  const response = await apiClient.get<PublicLookupBatchResponse>(
    `/api/public/lookup/m/${encodeURIComponent(materialCode)}/b/${encodeURIComponent(batchCode)}`
  )
  return response.data
}

/** Öffentliches Kontaktformular „Artikel gefunden“ (ohne Login). */
export interface PublicFoundItemContactPayload {
  entity_type: 'material' | 'batch' | 'activity' | 'workshop'
  public_code: string
  message: string
  sender_name?: string
  sender_email?: string
  /** Honeypot – leer lassen */
  website?: string
}

export async function submitPublicFoundItemContact(
  payload: PublicFoundItemContactPayload
): Promise<void> {
  await apiClient.post('/api/public/contact/found-item', payload)
}

