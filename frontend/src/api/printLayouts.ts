import apiClient from './apiClient'
import { uploadMediaFile } from './media'
import type { PrintCatalogScope, PrintCatalogStatus, PrintMedia } from './printCatalog'

export type PrintLayoutField = {
  id: string
  type: 'qr' | 'text'
  key: 'label' | 'public_url' | 'public_code'
  x: number
  y: number
  w: number
  h: number
}

export type PrintSheetCell = {
  x: number
  y: number
  w: number
  h: number
  col: number
  row: number
  index: number
}

export type PrintSheetSpec = {
  sheet_width_mm: number
  sheet_height_mm: number
  margin_top_mm: number
  margin_left_mm: number
  gap_x_mm: number
  gap_y_mm: number
  shape: string
  cols: number
  rows: number
  label_width_mm: number
  label_height_mm: number
}

export type PrintLayout = {
  id: string
  name: string
  media_id: string
  department_id: string | null
  organisation_id: string | null
  fields: PrintLayoutField[]
  template_filename: string | null
  template_sha256: string | null
  has_template: boolean
  include_template_on_print: boolean
  status: PrintCatalogStatus
  scope: PrintCatalogScope
  global_requested: boolean
  created_by_user_id: string | null
  reviewed_at: string | null
  media: PrintMedia
  sheet: PrintSheetSpec
  cells: PrintSheetCell[]
}

export type PrintLayoutDuplicate = {
  id: string
  name: string
  media_name: string
  scope: PrintCatalogScope
  global_requested: boolean
  department_id: string | null
  created_by_user_id: string | null
  has_template: boolean
}

export type PrintLayoutUploadResult = PrintLayout & {
  duplicate_templates?: PrintLayoutDuplicate[]
}

export type PrintLayoutPayload = {
  name: string
  media_id: string
  fields?: PrintLayoutField[]
  include_template_on_print?: boolean
  request_global?: boolean
}

export async function listDepartmentPrintLayouts(departmentId: string): Promise<PrintLayout[]> {
  const response = await apiClient.get<PrintLayout[]>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/layouts`,
  )
  return response.data
}

export async function createDepartmentPrintLayout(
  departmentId: string,
  payload: PrintLayoutPayload,
): Promise<PrintLayout> {
  const response = await apiClient.post<PrintLayout>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/layouts`,
    payload,
  )
  return response.data
}

export async function updateDepartmentPrintLayout(
  departmentId: string,
  layoutId: string,
  payload: Partial<PrintLayoutPayload>,
): Promise<PrintLayout> {
  const response = await apiClient.patch<PrintLayout>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/layouts/${encodeURIComponent(layoutId)}`,
    payload,
  )
  return response.data
}

export async function deleteDepartmentPrintLayout(departmentId: string, layoutId: string): Promise<void> {
  await apiClient.delete(
    `/api/departments/${encodeURIComponent(departmentId)}/print/layouts/${encodeURIComponent(layoutId)}`,
  )
}

export async function uploadPrintLayoutTemplate(
  departmentId: string,
  layoutId: string,
  file: File,
): Promise<PrintLayoutUploadResult> {
  const response = await uploadMediaFile<PrintLayoutUploadResult>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/layouts/${encodeURIComponent(layoutId)}/template`,
    file,
    { fieldName: 'file' },
  )
  return response.data
}

export function printLayoutTemplateUrl(departmentId: string, layoutId: string): string {
  return `/api/departments/${encodeURIComponent(departmentId)}/print/layouts/${encodeURIComponent(layoutId)}/template`
}

export async function fetchPrintLayoutTemplateBytes(departmentId: string, layoutId: string): Promise<ArrayBuffer> {
  const response = await apiClient.get<ArrayBuffer>(printLayoutTemplateUrl(departmentId, layoutId), {
    responseType: 'arraybuffer',
  })
  return response.data
}

export async function requestDepartmentPrintLayoutGlobal(
  departmentId: string,
  layoutId: string,
): Promise<PrintLayout> {
  const response = await apiClient.post<PrintLayout>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/layouts/${encodeURIComponent(layoutId)}/request-global`,
  )
  return response.data
}

export async function copyDepartmentPrintLayout(
  departmentId: string,
  layoutId: string,
): Promise<PrintLayout> {
  const response = await apiClient.post<PrintLayout>(
    `/api/departments/${encodeURIComponent(departmentId)}/print/layouts/${encodeURIComponent(layoutId)}/copy`,
  )
  return response.data
}

export async function reviewAdminPrintLayout(
  id: string,
  action: 'approve' | 'reject' | 'promote_global',
): Promise<PrintLayout> {
  const response = await apiClient.post<PrintLayout>(
    `/api/admin/print-catalog/layouts/${encodeURIComponent(id)}/review`,
    { action },
  )
  return response.data
}
