import apiClient from './apiClient'

export type ActivityApiType = 'activity' | 'camp' | 'event' | 'external'

/** Einladung weiterer Departments zu Lager/Event (Backend normalisiert Status/Zeiten) */
export interface InvitedDepartmentPayloadRow {
  id: string
  name: string
  organisation_name?: string
  group_id?: string | null
}

export interface CreateActivityPayload {
  department_id: string
  name: string
  type?: ActivityApiType
  status?: string
  group_id?: string | null
  /** Kunden-/Mieteradresse (z. B. bei Typ extern) */
  address_id?: string | null
  /** Eventstandort (Lager, Event, extern) */
  venue_address_id?: string | null
  usage_start?: string
  usage_end?: string
  planning_start?: string
  planning_end?: string
  invited_departments?: InvitedDepartmentPayloadRow[]
  /** Freitext-Notizen zur Aktivität */
  notes?: string
  /** Stepper: false solange Wizard nicht final abgeschlossen; Detail erst danach */
  create_wizard_completed?: boolean
}

export interface ActivityInvitedDepartmentApi {
  id: string
  name?: string
  organisation_name?: string
  status?: string
  group_id?: string | null
  group_name?: string | null
}

export interface ActivityCreatedResponse {
  id: string
  name: string
  type?: string
  status?: string
  department_id?: string
  usage_start?: string | null
  usage_end?: string | null
  no?: number
  invited_departments?: ActivityInvitedDepartmentApi[]
}

/** GET /api/activities/:id (serializeActivity detailed) */
export interface ActivityDetail extends ActivityCreatedResponse {
  group_id?: string | null
  department_name?: string
  color?: string | null
  item_count?: number
  pricing_mode?: string | null
  total_price?: number | null
  created_at?: string
  updated_at?: string
  planning_start?: string | null
  planning_end?: string | null
  address_id?: string | null
  venue_address_id?: string | null
  responsible_user_id?: string | null
  deposit_amount?: number | null
  deposit_paid?: boolean
  is_paid?: boolean
  notes?: string | null
  deleted_at?: string | null
  submitted_at?: string | null
  approved_at?: string | null
  issued_at?: string | null
  returned_at?: string | null
  completed_at?: string | null
  rejection_comment?: string | null
  is_material_editable?: boolean
  is_pack_list_editable?: boolean
  can_report_issues?: boolean
  is_return_editable?: boolean
  is_cancellable?: boolean
  /** GET /api/activities/:id — im Entwurf: darf User Material hinzufügen/entfernen */
  can_edit_draft_material?: boolean
  /** Einheitliche Berechtigung für die Material-UI (Entwurf: wie can_edit_draft_material; danach: Host-MW/DC bis packed) */
  can_edit_activity_material?: boolean
  /** submitted/approved: u–l3 dürfen noch Material ergänzen (vor «Annehmen & Packen») */
  can_add_forgotten_material?: boolean
  /** Nach Einreichung: Host-MW/DC dürfen Texte/Stammdaten per PATCH ändern */
  can_edit_submitted_activity_content?: boolean
  /** Im Entwurf: darf User «Einreichen» (typabhängig — API) */
  can_submit_activity?: boolean
  /** false = Erstell-Wizard noch nicht abgeschlossen → Detail gesperrt, Wizard fortsetzen */
  create_wizard_completed?: boolean
  public_code?: string | null
  public_url?: string | null
}

export interface ActivityTransitionRow {
  status: string
  label: string
  allowed: boolean
  reason: string | null
}

export interface ActivityCompletionBlockerFollowUp {
  id: string
  amount: string
  receipt_label?: string | null
  source_kind?: string | null
  department_id: string
  department_name?: string | null
  charge_target?: string | null
  material_department_name?: string | null
  external_customer_label?: string | null
  reported_by_display_name?: string | null
}

export interface ActivityCompletionBlockers {
  unstored_pack_items_count?: number
  open_issue_reports_count?: number
  open_workshop_tickets_count?: number
  pending_accounting_followups_count?: number
  unstored_pack_items?: Array<{
    id: string
    material_name?: string | null
    quantity_packed?: number
    quantity_returned?: number
    quantity_stored?: number
    pending_store?: number
  }>
  open_issue_reports?: Array<{
    id: string
    type: string
    quantity?: number
    material_name?: string | null
    reported_at?: string
  }>
  open_workshop_tickets?: Array<{
    id: string
    title?: string
    status?: string
    type?: string
  }>
  pending_accounting_followups?: ActivityCompletionBlockerFollowUp[]
}

export interface ActivityTransitionsResponse {
  current_status: string
  current_label?: string
  transitions: ActivityTransitionRow[]
  completion_blockers?: ActivityCompletionBlockers
}

export interface ActivityItemRow {
  id: string
  material_item_id: string
  material_name: string
  /** physical | physical_combo | virtual_combo */
  material_type?: string | null
  /** Bei physischer Kombi: Bezugskiste (Label oder Seriennummer) */
  linked_container_label?: string | null
  /** Effektiv für Anzeige: serialized | bulk (bei Kiste aus Packliste auch serialized, wenn Stammdaten bulk) */
  tracking_type?: 'serialized' | 'bulk' | null
  source_department_id?: string
  source_department_name?: string
  quantity: number
  priority?: string | null
  status?: string | null
  notes?: string | null
  unit_price?: string | number | null
  line_total?: string | number | null
  price_type?: string | null
  is_consumable?: boolean
  is_replenishment?: boolean
  created_by_user_id?: string | null
  created_by_display_name?: string | null
  submitter_department_id?: string | null
  submitter_department_name?: string | null
  recorded_at?: string | null
  sale_price?: string | number | null
  pack_size?: number | null
  pack_unit?: string | null
  is_js_material?: boolean
  /** Behälter/Kiste: Stammdaten, Packliste oder physischer Combo mit Bezugskiste */
  is_container?: boolean
  /** Lager-Kisten-Charge der Bezugskiste (physisch. Kombi / verknüpfte Kiste) — für Pack-Behälter anlegen */
  linked_container_batch_id?: string | null
  external_source?: string | null
}

export async function getActivity(activityId: string): Promise<ActivityDetail> {
  const { data } = await apiClient.get<ActivityDetail>(`/api/activities/${activityId}`)
  return data
}

export async function ensureActivityPublicCode(activityId: string): Promise<ActivityDetail> {
  const { data } = await apiClient.post<ActivityDetail>(`/api/activities/${activityId}/public-code`)
  return data
}

export async function getActivityTransitions(activityId: string): Promise<ActivityTransitionsResponse> {
  const { data } = await apiClient.get<ActivityTransitionsResponse>(`/api/activities/${activityId}/transitions`)
  return data
}

export async function getActivityItems(activityId: string): Promise<ActivityItemRow[]> {
  const { data } = await apiClient.get<ActivityItemRow[]>(`/api/activities/${activityId}/items`)
  return data
}

export async function patchActivityStatus(
  activityId: string,
  body: { status: string; comment?: string | null },
): Promise<ActivityDetail> {
  const { data } = await apiClient.patch<ActivityDetail>(`/api/activities/${activityId}/status`, body)
  return data
}

export async function createActivity(payload: CreateActivityPayload): Promise<ActivityCreatedResponse> {
  const { data } = await apiClient.post<ActivityCreatedResponse>('/api/activities', payload)
  return data
}

/** Aktualisieren (Entwurf / wie v4.01 PATCH im Detail) — ohne department_id im Body */
export type PatchActivityPayload = Partial<Omit<CreateActivityPayload, 'department_id' | 'notes'>> & {
  /** PATCH erlaubt explizites Leeren (null) wie im Backend setNotes */
  notes?: string | null
}

export async function patchActivity(
  activityId: string,
  payload: PatchActivityPayload,
): Promise<ActivityCreatedResponse> {
  const { data } = await apiClient.patch<ActivityCreatedResponse>(`/api/activities/${activityId}`, payload)
  return data
}

export interface SyncActivityItemPayload {
  material_item_id: string
  quantity: number
  priority?: string
}

/**
 * Materialpositionen einer Aktivität setzen (ersetzt die Liste).
 */
export async function syncActivityItems(
  activityId: string,
  payload: { items: SyncActivityItemPayload[] },
): Promise<{ message?: string; item_count?: number; total_price?: string | null }> {
  const { data } = await apiClient.put(`/api/activities/${activityId}/items`, payload)
  return data
}

export async function addActivityItem(
  activityId: string,
  body: {
    material_item_id: string
    quantity?: number
    replenishment?: boolean
    /** Department-Kontext der Erfassung (Route/UI), wichtig bei Partner-Aktivitäten */
    acting_department_id?: string
    unit_price?: number | string
    line_total?: number | string
    price_type?: string
  },
): Promise<{ message?: string; total_price?: string | null }> {
  const { data } = await apiClient.post(`/api/activities/${activityId}/items`, body)
  return data
}

export async function releaseConsumableSurplus(
  activityId: string,
  body: { material_item_id: string; quantity: number },
): Promise<{ message?: string; released?: number; total_price?: string | null }> {
  const { data } = await apiClient.post(`/api/activities/${activityId}/items/release-surplus`, body)
  return data
}

export async function removeActivityItem(activityId: string, itemId: string): Promise<{ message?: string; total_price?: string | null }> {
  const { data } = await apiClient.delete(`/api/activities/${activityId}/items/${itemId}`)
  return data
}

/** GET /api/activities/:id/issues */
export interface ActivityIssueReportRow {
  id: string
  activity_id: string
  material_item_id: string | null
  material_name?: string | null
  type: string
  type_label?: string
  quantity: number
  description?: string | null
  resolved: boolean
  resolved_at?: string | null
  reported_at: string
  reported_by?: string | null
  reported_by_display_name?: string | null
  is_js_material?: boolean
}

export async function getActivityIssues(activityId: string): Promise<ActivityIssueReportRow[]> {
  const { data } = await apiClient.get<ActivityIssueReportRow[]>(`/api/activities/${activityId}/issues`)
  return data ?? []
}

/** GET /api/activities/:id/history */
export interface ActivityHistoryEntryRow {
  id: string
  action: string
  snapshot: Record<string, unknown>
  changes: Record<string, unknown>
  created_at: string
  user: {
    id: string
    name: string
    nickname?: string | null
    first_name?: string | null
    last_name?: string | null
  } | null
}

export async function getActivityHistory(activityId: string): Promise<ActivityHistoryEntryRow[]> {
  const { data } = await apiClient.get<ActivityHistoryEntryRow[]>(
    `/api/activities/${activityId}/history`,
  )
  return data ?? []
}

/** POST /api/activities/:id/issues — Verlust, Reparatur, Verbrauch, Schaden */
export async function createActivityIssue(
  activityId: string,
  body: {
    material_item_id: string
    type: 'damage' | 'repair' | 'loss' | 'consumption' | 'not_taken'
    quantity: number
    description?: string | null
  },
): Promise<unknown> {
  const { data } = await apiClient.post(`/api/activities/${activityId}/issues`, body)
  return data
}
