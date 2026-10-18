import apiClient from './apiClient'

/** Helferpool, Reiter «API-Daten»: externe Datenquellen. Der Zugangsschlüssel wird nur gesendet, nie zurückgegeben (`has_secret`). */

export type GaExtAuthType = 'none' | 'bearer' | 'header' | 'basic'
export type GaExtTarget =
  | 'booking_id'
  | 'helper_key'
  | 'helper_name'
  | 'helper_first_name'
  | 'helper_last_name'
  | 'helper_email'
  | 'helper_phone'
  | 'mission'
  | 'shift_start'
  | 'shift_end'
  | 'booking_status'

export type GaExtMapping = {
  records_path?: string
  fields?: Partial<Record<GaExtTarget, string>>
  cancelled_values?: string[]
  total_path?: string
  next_path?: string
}

export type GaExtPayloadMeta = {
  id: string
  origin: 'fetch' | 'import'
  http_status: number | null
  byte_size: number
  fetched_at: string
  last_seen_at: string
  seen_count: number
}
export type GaExtPayload = GaExtPayloadMeta & { body: string }

export type GaExtCounts = { created: number; updated: number; unchanged: number; removed: number; conflicts: number }

export type GaExtSource = {
  id: string
  name: string
  base_url: string
  endpoint_path: string
  auth_type: GaExtAuthType
  auth_header: string | null
  auth_user: string | null
  has_secret: boolean
  has_url: boolean
  mapping: GaExtMapping
  mapping_problems: { field: string; code: string }[]
  snapshot_complete: boolean
  connection_status: 'never' | 'ok' | 'error'
  last_tested_at: string | null
  last_error: string | null
  last_success_sync_at: string | null
  latest_payload: GaExtPayloadMeta | null
  last_run: { id: string; started_at: string; outcome: GaExtOutcome; counts: GaExtCounts } | null
  record_counts: Partial<Record<'active' | 'cancelled' | 'removed', number>>
}

export type GaExtOutcome = 'ok' | 'partial' | 'failed'

export type GaExtIssue = { level: 'error' | 'warning'; code: string; field?: string | null; key?: string | null; index?: number; count?: number; message?: string }

export type GaExtSyncResult = {
  dry_run: boolean
  outcome: GaExtOutcome
  run_id: string | null
  payload_id: string
  counts: GaExtCounts
  resolved: number
  items: {
    key: string
    action: 'created' | 'updated' | 'cancelled' | 'restored' | 'removed'
    helper: string
    mission: string | null
    shift_start: string | null
    shift_end: string | null
    changes: { field: string; old: unknown; new: unknown }[]
  }[]
  conflicts: { assignment_id: string; helper: string; starts_at: string; ends_at: string; detail: 'booking_cancelled' | 'shift_changed' }[]
  errors: GaExtIssue[]
}

export type GaExtRun = { id: string; payload_id: string | null; started_at: string; finished_at: string | null; outcome: GaExtOutcome; counts: GaExtCounts; errors: GaExtIssue[] }

export type GaExtSourceInput = {
  name?: string
  base_url?: string
  endpoint_path?: string
  auth_type?: GaExtAuthType
  auth_header?: string | null
  auth_user?: string | null
  /** Nur senden, wenn neu gesetzt; leer lässt den gespeicherten Schlüssel unverändert. */
  secret?: string
  clear_secret?: boolean
  snapshot_complete?: boolean
}

const base = (departmentId: string) => `/api/departments/${departmentId}/grossanlass/external-sources`

export async function listGaExtSources(departmentId: string): Promise<GaExtSource[]> {
  return (await apiClient.get<GaExtSource[]>(base(departmentId))).data
}
export async function createGaExtSource(departmentId: string, data: GaExtSourceInput): Promise<GaExtSource> {
  return (await apiClient.post<GaExtSource>(base(departmentId), data)).data
}
export async function updateGaExtSource(departmentId: string, id: string, data: GaExtSourceInput): Promise<GaExtSource> {
  return (await apiClient.patch<GaExtSource>(`${base(departmentId)}/${id}`, data)).data
}
export async function saveGaExtMapping(departmentId: string, id: string, mapping: GaExtMapping): Promise<GaExtSource> {
  return (await apiClient.put<GaExtSource>(`${base(departmentId)}/${id}/mapping`, mapping)).data
}
export async function testGaExtSource(departmentId: string, id: string): Promise<{ ok: boolean; error?: string; http_status?: number; source: GaExtSource }> {
  return (await apiClient.post(`${base(departmentId)}/${id}/test`)).data
}
export async function fetchGaExtPayload(departmentId: string, id: string): Promise<GaExtPayload> {
  return (await apiClient.post<GaExtPayload>(`${base(departmentId)}/${id}/fetch`)).data
}
export async function importGaExtJson(departmentId: string, id: string, json: string): Promise<GaExtPayload> {
  return (await apiClient.post<GaExtPayload>(`${base(departmentId)}/${id}/import-json`, { json })).data
}
export async function getGaExtPayload(departmentId: string, id: string, payloadId = 'latest'): Promise<GaExtPayload> {
  return (await apiClient.get<GaExtPayload>(`${base(departmentId)}/${id}/payloads/${payloadId}`)).data
}
export async function listGaExtPayloads(departmentId: string, id: string): Promise<GaExtPayloadMeta[]> {
  return (await apiClient.get<GaExtPayloadMeta[]>(`${base(departmentId)}/${id}/payloads`)).data
}
export async function previewGaExtSync(departmentId: string, id: string, payloadId?: string): Promise<GaExtSyncResult> {
  return (await apiClient.post<GaExtSyncResult>(`${base(departmentId)}/${id}/preview`, { payload_id: payloadId })).data
}
export async function syncGaExtSource(departmentId: string, id: string, opts: { fetch: boolean; payloadId?: string }): Promise<GaExtSyncResult> {
  return (await apiClient.post<GaExtSyncResult>(`${base(departmentId)}/${id}/sync`, { fetch: opts.fetch, payload_id: opts.payloadId })).data
}
export async function listGaExtRuns(departmentId: string, id: string): Promise<GaExtRun[]> {
  return (await apiClient.get<GaExtRun[]>(`${base(departmentId)}/${id}/runs`)).data
}
