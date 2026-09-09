import apiClient from './apiClient'
import type { PrintLayout } from './printLayouts'

export type PrintFamilyId = 'brother_ql' | 'office_a4' | 'tsc_desktop'

export type PrintCatalogStatus = 'pending' | 'published' | 'rejected'
export type PrintCatalogScope = 'global' | 'organisation'

export type PrintFamilyOption = { id: PrintFamilyId | string; label: string }

export type PrintDeviceModel = {
  id: string
  catalog_key: string
  family: string
  brand: string
  name: string
  compatible_media_keys: string[]
  status: PrintCatalogStatus
  scope: PrintCatalogScope
  organisation_id: string | null
  created_by_user_id: string | null
  global_requested: boolean
  reviewed_at: string | null
}

export type PrintMedia = {
  id: string
  catalog_key: string
  family: string
  brand: string
  sku: string
  name: string
  width_mm: number
  height_mm: number | null
  cols: number
  rows: number
  is_continuous: boolean
  default_cut_length_mm: number | null
  shape?: 'rect' | 'round' | string
  sheet_width_mm?: number | null
  sheet_height_mm?: number | null
  margin_top_mm?: number | null
  margin_left_mm?: number | null
  gap_x_mm?: number | null
  gap_y_mm?: number | null
  status: PrintCatalogStatus
  scope: PrintCatalogScope
  organisation_id: string | null
  created_by_user_id: string | null
  global_requested: boolean
  reviewed_at: string | null
}

export type DepartmentPrintPreset = {
  id: string
  department_id: string
  name: string
  device_model_id: string
  media_id: string
  cut_length_mm: number | null
  is_default: boolean
  device_model: PrintDeviceModel
  media: PrintMedia
}

export type DepartmentPrintCatalog = {
  families: PrintFamilyOption[]
  can_manage_presets: boolean
  can_propose: boolean
  models: PrintDeviceModel[]
  media: PrintMedia[]
  published_models: PrintDeviceModel[]
  published_media: PrintMedia[]
}

export type AdminPrintCatalog = {
  is_superadmin: boolean
  can_review: boolean
  families: PrintFamilyOption[]
  models: PrintDeviceModel[]
  media: PrintMedia[]
  layouts?: PrintLayout[]
}

export type ProposeModelPayload = {
  family: string
  brand: string
  name: string
  compatible_media_keys?: string[]
  request_global?: boolean
}

export type ProposeMediaPayload = {
  family: string
  brand: string
  sku: string
  name: string
  width_mm: number
  height_mm?: number | null
  cols?: number
  rows?: number
  is_continuous?: boolean
  default_cut_length_mm?: number | null
  request_global?: boolean
}

export type CreatePresetPayload = {
  name: string
  device_model_id: string
  media_id: string
  cut_length_mm?: number | null
  is_default?: boolean
}

export async function getDepartmentPrintCatalog(departmentId: string): Promise<DepartmentPrintCatalog> {
  const response = await apiClient.get<DepartmentPrintCatalog>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/catalog`,
  )
  return response.data
}

export async function proposeDepartmentPrintModel(
  departmentId: string,
  payload: ProposeModelPayload,
): Promise<PrintDeviceModel> {
  const response = await apiClient.post<PrintDeviceModel>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/catalog/models`,
    payload,
  )
  return response.data
}

export async function proposeDepartmentPrintMedia(
  departmentId: string,
  payload: ProposeMediaPayload,
): Promise<PrintMedia> {
  const response = await apiClient.post<PrintMedia>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/catalog/media`,
    payload,
  )
  return response.data
}

export async function requestDepartmentPrintModelGlobal(
  departmentId: string,
  id: string,
): Promise<PrintDeviceModel> {
  const response = await apiClient.post<PrintDeviceModel>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/catalog/models/${encodeURIComponent(id)}/request-global`,
  )
  return response.data
}

export async function requestDepartmentPrintMediaGlobal(
  departmentId: string,
  id: string,
): Promise<PrintMedia> {
  const response = await apiClient.post<PrintMedia>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/catalog/media/${encodeURIComponent(id)}/request-global`,
  )
  return response.data
}

export async function getDepartmentPrintPresets(departmentId: string): Promise<DepartmentPrintPreset[]> {
  const response = await apiClient.get<DepartmentPrintPreset[]>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/presets`,
  )
  return response.data
}

export async function createDepartmentPrintPreset(
  departmentId: string,
  payload: CreatePresetPayload,
): Promise<DepartmentPrintPreset> {
  const response = await apiClient.post<DepartmentPrintPreset>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/presets`,
    payload,
  )
  return response.data
}

export async function updateDepartmentPrintPreset(
  departmentId: string,
  presetId: string,
  payload: Partial<CreatePresetPayload>,
): Promise<DepartmentPrintPreset> {
  const response = await apiClient.patch<DepartmentPrintPreset>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/presets/${encodeURIComponent(presetId)}`,
    payload,
  )
  return response.data
}

export async function deleteDepartmentPrintPreset(departmentId: string, presetId: string): Promise<void> {
  await apiClient.delete(
    `/api/departments/${encodeURIComponent(departmentId)}/print/presets/${encodeURIComponent(presetId)}`,
  )
}

export async function getAdminPrintCatalog(): Promise<AdminPrintCatalog> {
  const response = await apiClient.get<AdminPrintCatalog>('/api/admin/print-catalog')
  return response.data
}

export async function createAdminPrintModel(payload: ProposeModelPayload): Promise<PrintDeviceModel> {
  const response = await apiClient.post<PrintDeviceModel>('/api/admin/print-catalog/models', payload)
  return response.data
}

export async function createAdminPrintMedia(payload: ProposeMediaPayload): Promise<PrintMedia> {
  const response = await apiClient.post<PrintMedia>('/api/admin/print-catalog/media', payload)
  return response.data
}

export async function reviewAdminPrintModel(
  id: string,
  action: 'approve' | 'reject' | 'promote_global',
): Promise<PrintDeviceModel> {
  const response = await apiClient.post<PrintDeviceModel>(
    `/api/admin/print-catalog/models/${encodeURIComponent(id)}/review`,
    { action },
  )
  return response.data
}

export async function reviewAdminPrintMedia(
  id: string,
  action: 'approve' | 'reject' | 'promote_global',
): Promise<PrintMedia> {
  const response = await apiClient.post<PrintMedia>(
    `/api/admin/print-catalog/media/${encodeURIComponent(id)}/review`,
    { action },
  )
  return response.data
}
