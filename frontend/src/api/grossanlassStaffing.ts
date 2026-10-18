import apiClient from './apiClient'

/** Helferpool V1: Helfer, Verfügbarkeit, Personalbedarf, Zuteilung, Deckung. Zeiten sind lokale ISO-Zeitpunkte `YYYY-MM-DDTHH:mm:ss`. */

export type GaHelperStatus = 'active' | 'archived'

export type GaHelper = {
  id: string
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  note: string | null
  origin_department_id: string | null
  has_login: boolean
  /** Verknüpftes eMatChef-Konto; Name und E-Mail kommen dann aus dem zentralen Profil. */
  user_id?: string | null
  source: string
  status: GaHelperStatus
  archived_at: string | null
  availability?: GaHelperAvailability[]
  occupation?: string | null
  area?: string | null
  skills?: string[]
  qualifications?: GaHelperQualification[]
  /** `profile`: zentraler Führerausweis des verknüpften Kontos (nur lesend); `local`: am Helfer erfasst (extern). */
  drive_license?: GaHelperDriveLicense
}

export type GaHelperQualification = { label: string; valid_until: string | null }
export type GaHelperDriveLicense = { classes: string[]; valid_until: string | null; source: 'profile' | 'local' }
export type GaLinkableUser = { user_id: string; name: string; first_name: string; last_name: string; email: string; helper_id: string | null }

export type GaHelperInput = {
  first_name?: string
  last_name?: string
  email?: string | null
  phone?: string | null
  note?: string | null
  origin_department_id?: string | null
  user_id?: string | null
  occupation?: string | null
  area?: string | null
  skills?: string[]
  drive_classes?: string[]
  drive_valid_until?: string | null
  qualifications?: GaHelperQualification[]
}

export type GaHelperAvailability = {
  id: string
  helper_id: string
  starts_at: string
  ends_at: string
}

export type GaCoverageSegment = { from: string; to: string; assigned: number; missing: number }

export type GaCoverage = {
  required: number
  minutes: number
  segments: GaCoverageSegment[]
  gaps: GaCoverageSegment[]
  min_assigned: number
  peak_assigned: number
  fill_ratio: number | null
  overbooked: boolean
}

/** Bedarfszeile der Übersicht — ohne Helfernamen. */
export type GaStaffingNeedRow = {
  id: string
  task_id: string
  task_title: string | null
  is_default_task: boolean
  group_id: string
  label: string
  required_count: number
  starts_at: string | null
  ends_at: string | null
  dated: boolean
  assigned_count: number
  coverage: GaCoverage | null
}

export type GaStaffingGroupRow = {
  group_id: string
  required_total: number
  peak_required: number
  fill_ratio: number | null
  open_needs: number
  undated_needs: number
  overbooked_needs: number
}

export type GaStaffingOverview = {
  needs: GaStaffingNeedRow[]
  groups: GaStaffingGroupRow[]
  totals: { required_total: number; fill_ratio: number | null; open_needs: number; undated_needs: number }
  /** Darf der aktuelle Benutzer den Helferpool verwalten (Backend-Entscheid inkl. GA-Einstellungen). */
  can_manage?: boolean
}

export type GaStaffingNeedInput = {
  task_id?: string
  label?: string
  required_count?: number
  starts_at?: string | null
  ends_at?: string | null
}

export type GaStaffingNeed = {
  id: string
  task_id: string
  label: string
  required_count: number
  starts_at: string | null
  ends_at: string | null
  dated: boolean
  sort_order: number
  group_id?: string
  is_default_task?: boolean
}

export type GaNeedAssignment = {
  id: string
  helper_id: string
  helper_name: string
  starts_at: string
  ends_at: string
  status: string
  /** Gesetzt bei Status `conflict`: die externe Buchung wurde verschoben oder storniert. */
  conflict?: { reason: string; detail: 'booking_cancelled' | 'shift_changed'; source_name?: string } | null
  override_reason: string | null
  version: number
}

/** Detail mit Namen — nur GA-HV/GA-SUBHV. */
export type GaStaffingNeedDetail = Omit<GaStaffingNeedRow, 'assigned_count' | 'is_default_task'> & { assignments: GaNeedAssignment[] }

export type GaStaffingAssignment = {
  id: string
  need_id: string
  helper_id: string
  starts_at: string
  ends_at: string
  status: string
  has_override: boolean
  override_reason: string | null
  version: number
}

export type GaStaffingWarning = { kind: 'overbooked' | 'double_booking_override'; [key: string]: unknown }

export type GaStaffingAssignResult = { assignment: GaStaffingAssignment; warnings: GaStaffingWarning[] }

export type GaAssignInput = {
  need_id: string
  helper_id: string
  starts_at?: string
  ends_at?: string
  override_reason?: string
}

export type GaScheduleHelper = {
  helper_id: string
  name: string
  status: GaHelperStatus
  availability: { starts_at: string; ends_at: string }[]
  assignments: {
    id: string
    need_id: string
    group_id: string
    task_title: string | null
    label: string
    starts_at: string
    ends_at: string
    has_override: boolean
  }[]
}

export type GaStaffingConflictItem = {
  kind: 'double_booking' | 'outside_availability' | 'outside_need_window' | 'overbooked' | 'need_undated' | 'external_booking_invalid'
  detail?: 'booking_cancelled' | 'shift_changed' | null
  helper_id?: string
  assignment_ids?: string[]
  need_id?: string
  starts_at?: string
  ends_at?: string
  excused?: boolean
  required?: number
  peak_assigned?: number
}

/** Fachlicher Konflikt (HTTP 409) der Zuteilung. */
export type GaStaffingConflictPayload = {
  error: string
  code: 'staffing_conflict'
  conflict: {
    kind: 'double_booking' | 'no_availability' | 'outside_need_window' | 'need_undated' | 'helper_archived' | 'in_use' | 'stale'
    override_possible?: boolean
    conflicts?: { assignment_id: string; need_id: string; task_id: string; starts_at: string; ends_at: string }[]
    [key: string]: unknown
  }
}

/** Liefert den Konflikt, wenn ein Axios-Fehler ein 409 `staffing_conflict` ist. */
export function staffingConflictOf(err: unknown): GaStaffingConflictPayload | null {
  const response = (err as { response?: { status?: number; data?: unknown } } | null)?.response
  const data = response?.data as Partial<GaStaffingConflictPayload> | undefined
  return response?.status === 409 && data?.code === 'staffing_conflict' && data.conflict ? (data as GaStaffingConflictPayload) : null
}

const base = (departmentId: string) => `/api/departments/${departmentId}/grossanlass`

export async function listGaHelpers(departmentId: string, params: { status?: 'active' | 'archived' | 'all'; q?: string; limit?: number } = {}): Promise<GaHelper[]> {
  return (await apiClient.get<GaHelper[]>(`${base(departmentId)}/helpers`, { params })).data
}

export async function getGaHelper(departmentId: string, helperId: string): Promise<GaHelper> {
  return (await apiClient.get<GaHelper>(`${base(departmentId)}/helpers/${helperId}`)).data
}

export async function createGaHelper(departmentId: string, data: GaHelperInput): Promise<GaHelper> {
  return (await apiClient.post<GaHelper>(`${base(departmentId)}/helpers`, data)).data
}

export async function updateGaHelper(departmentId: string, helperId: string, data: GaHelperInput): Promise<GaHelper> {
  return (await apiClient.patch<GaHelper>(`${base(departmentId)}/helpers/${helperId}`, data)).data
}

/** Fähigkeitenkatalog des GA-Departments (Katalog plus verwendete Funktionswünsche). */
export async function listGaSkills(departmentId: string): Promise<string[]> {
  return (await apiClient.get<string[]>(`${base(departmentId)}/helper-skills`)).data
}

export async function addGaSkill(departmentId: string, name: string): Promise<string[]> {
  return (await apiClient.post<string[]>(`${base(departmentId)}/helper-skills`, { name })).data
}

export async function listGaLinkableUsers(departmentId: string): Promise<GaLinkableUser[]> {
  return (await apiClient.get<GaLinkableUser[]>(`${base(departmentId)}/helper-linkable-users`)).data
}

export async function archiveGaHelper(departmentId: string, helperId: string): Promise<GaHelper> {
  return (await apiClient.post<GaHelper>(`${base(departmentId)}/helpers/${helperId}/archive`)).data
}

export async function restoreGaHelper(departmentId: string, helperId: string): Promise<GaHelper> {
  return (await apiClient.post<GaHelper>(`${base(departmentId)}/helpers/${helperId}/restore`)).data
}

export async function addGaHelperAvailability(departmentId: string, helperId: string, startsAt: string, endsAt: string): Promise<GaHelperAvailability> {
  return (await apiClient.post<GaHelperAvailability>(`${base(departmentId)}/helpers/${helperId}/availability`, { starts_at: startsAt, ends_at: endsAt })).data
}

export async function updateGaHelperAvailability(departmentId: string, availabilityId: string, startsAt: string, endsAt: string): Promise<GaHelperAvailability> {
  return (await apiClient.patch<GaHelperAvailability>(`${base(departmentId)}/helper-availability/${availabilityId}`, { starts_at: startsAt, ends_at: endsAt })).data
}

export async function deleteGaHelperAvailability(departmentId: string, availabilityId: string): Promise<void> {
  await apiClient.delete(`${base(departmentId)}/helper-availability/${availabilityId}`)
}

export async function getGaStaffingOverview(departmentId: string, params: { group_ids?: string; from?: string; to?: string } = {}): Promise<GaStaffingOverview> {
  return (await apiClient.get<GaStaffingOverview>(`${base(departmentId)}/staffing/overview`, { params })).data
}

export async function getGaStaffingNeedDetail(departmentId: string, needId: string): Promise<GaStaffingNeedDetail> {
  return (await apiClient.get<GaStaffingNeedDetail>(`${base(departmentId)}/staffing/needs/${needId}`)).data
}

export async function listGaGroupStaffingNeeds(departmentId: string, groupId: string): Promise<GaStaffingNeed[]> {
  return (await apiClient.get<GaStaffingNeed[]>(`${base(departmentId)}/groups/${groupId}/staffing-needs`)).data
}

export async function createGaGroupStaffingNeed(departmentId: string, groupId: string, data: GaStaffingNeedInput): Promise<GaStaffingNeed> {
  return (await apiClient.post<GaStaffingNeed>(`${base(departmentId)}/groups/${groupId}/staffing-needs`, data)).data
}

export async function updateGaGroupStaffingNeed(departmentId: string, groupId: string, needId: string, data: GaStaffingNeedInput): Promise<GaStaffingNeed> {
  return (await apiClient.patch<GaStaffingNeed>(`${base(departmentId)}/groups/${groupId}/staffing-needs/${needId}`, data)).data
}

export async function deleteGaGroupStaffingNeed(departmentId: string, groupId: string, needId: string): Promise<void> {
  await apiClient.delete(`${base(departmentId)}/groups/${groupId}/staffing-needs/${needId}`)
}

export async function assignGaHelper(departmentId: string, data: GaAssignInput): Promise<GaStaffingAssignResult> {
  return (await apiClient.post<GaStaffingAssignResult>(`${base(departmentId)}/staffing/assignments`, data)).data
}

export async function updateGaAssignment(
  departmentId: string,
  assignmentId: string,
  data: { starts_at?: string; ends_at?: string; override_reason?: string; version?: number },
): Promise<GaStaffingAssignResult> {
  return (await apiClient.patch<GaStaffingAssignResult>(`${base(departmentId)}/staffing/assignments/${assignmentId}`, data)).data
}

export async function cancelGaAssignment(departmentId: string, assignmentId: string): Promise<GaStaffingAssignment> {
  return (await apiClient.delete<GaStaffingAssignment>(`${base(departmentId)}/staffing/assignments/${assignmentId}`)).data
}

export async function getGaStaffingSchedule(departmentId: string, from: string, to: string): Promise<GaScheduleHelper[]> {
  return (await apiClient.get<GaScheduleHelper[]>(`${base(departmentId)}/staffing/schedule`, { params: { from, to } })).data
}

export async function getGaStaffingConflicts(departmentId: string): Promise<GaStaffingConflictItem[]> {
  return (await apiClient.get<GaStaffingConflictItem[]>(`${base(departmentId)}/staffing/conflicts`)).data
}
