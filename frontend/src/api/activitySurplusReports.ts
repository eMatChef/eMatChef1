import apiClient from './apiClient'

export type ActivitySurplusReportStatus = 'open' | 'matched' | 'resolved' | 'dismissed'
export type ActivitySurplusReportKind = 'food' | 'consumable' | 'other'

export interface ActivitySurplusReport {
  id: string
  activityId: string
  departmentId: string
  nameFreeText: string
  qty: number
  kind: ActivitySurplusReportKind
  expiryDate: string | null
  materialItemId: string | null
  materialName: string | null
  resolvedBatchId: string | null
  inventoryTaskId: string | null
  status: ActivitySurplusReportStatus
  notes: string | null
  reportedByUserId: string | null
  reportedByDisplayName: string | null
  batchLabel: string | null
  createdAt: string
  updatedAt: string
}

export interface CreateActivitySurplusReportBody {
  name_free_text: string
  qty?: number
  kind?: ActivitySurplusReportKind
  expiry_date?: string | null
  notes?: string | null
  material_item_id?: string | null
}

export interface PatchActivitySurplusReportBody {
  name_free_text?: string
  qty?: number
  kind?: ActivitySurplusReportKind
  expiry_date?: string | null
  notes?: string | null
  status?: ActivitySurplusReportStatus
  material_item_id?: string | null
  resolved_batch_id?: string | null
  inventory_task_id?: string | null
}

function mapReport(raw: Record<string, unknown>): ActivitySurplusReport {
  return {
    id: String(raw.id ?? ''),
    activityId: String(raw.activity_id ?? ''),
    departmentId: String(raw.department_id ?? ''),
    nameFreeText: String(raw.name_free_text ?? ''),
    qty: Number(raw.qty ?? 1),
    kind: String(raw.kind ?? 'food') as ActivitySurplusReportKind,
    expiryDate: raw.expiry_date != null ? String(raw.expiry_date) : null,
    materialItemId: raw.material_item_id != null ? String(raw.material_item_id) : null,
    materialName: raw.material_name != null ? String(raw.material_name) : null,
    resolvedBatchId: raw.resolved_batch_id != null ? String(raw.resolved_batch_id) : null,
    inventoryTaskId: raw.inventory_task_id != null ? String(raw.inventory_task_id) : null,
    status: String(raw.status ?? 'open') as ActivitySurplusReportStatus,
    notes: raw.notes != null ? String(raw.notes) : null,
    reportedByUserId: raw.reported_by_user_id != null ? String(raw.reported_by_user_id) : null,
    reportedByDisplayName:
      raw.reported_by_display_name != null ? String(raw.reported_by_display_name) : null,
    batchLabel: raw.batch_label != null ? String(raw.batch_label) : null,
    createdAt: String(raw.created_at ?? ''),
    updatedAt: String(raw.updated_at ?? ''),
  }
}

export async function getActivitySurplusReports(
  activityId: string,
  params?: { status?: string },
): Promise<ActivitySurplusReport[]> {
  const { data } = await apiClient.get<{ reports?: Record<string, unknown>[] }>(
    `/api/activities/${activityId}/surplus-reports`,
    { params },
  )
  const list = Array.isArray(data?.reports) ? data.reports : []
  return list.map((row) => mapReport(row as Record<string, unknown>))
}

export async function postActivitySurplusReport(
  activityId: string,
  body: CreateActivitySurplusReportBody,
): Promise<ActivitySurplusReport> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/surplus-reports`,
    body,
  )
  return mapReport((data ?? {}) as Record<string, unknown>)
}

export async function patchActivitySurplusReport(
  activityId: string,
  reportId: string,
  body: PatchActivitySurplusReportBody,
): Promise<ActivitySurplusReport> {
  const { data } = await apiClient.patch<Record<string, unknown>>(
    `/api/activities/${activityId}/surplus-reports/${reportId}`,
    body,
  )
  return mapReport((data ?? {}) as Record<string, unknown>)
}

export async function deleteActivitySurplusReport(
  activityId: string,
  reportId: string,
): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/surplus-reports/${reportId}`)
}
