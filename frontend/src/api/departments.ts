import apiClient from './apiClient'

export interface DepartmentUser {
  id: string
  profile_id: string
  name: string
  first_name?: string | null
  last_name?: string | null
  nickname?: string | null
  email: string
  avatar_initials?: string | null
  background_color?: string | null
  text_color?: string | null
  role: string
  is_primary: boolean
}

export interface Department {
  id: string
  name: string
  organisation_id: string
  parent_id?: string | null
  users: DepartmentUser[]
  is_grossanlass?: boolean
}

/**
 * Lädt alle Departments mit ihren Usern
 */
export async function getDepartments(): Promise<Department[]> {
  const response = await apiClient.get<Department[]>('/api/departments')
  return response.data
}

/**
 * Lädt ein einzelnes Department
 */
export async function getDepartment(id: string): Promise<Department> {
  const response = await apiClient.get<Department>(`/api/departments/${id}`)
  return response.data
}

/**
 * Prüft ob das Department bereits mw oder dc hat
 */
export async function departmentHasManager(departmentId: string): Promise<boolean> {
  const response = await apiClient.get<{ has_mw_or_dc: boolean }>(`/api/departments/${departmentId}/has-manager`)
  return response.data.has_mw_or_dc
}

export interface CreateDepartmentRequest {
  name: string
  organisation_id: string
  parent_id?: string | null
}

export interface GrossanlassConfig {
  status: string
  struktur_modus?: string
  planned_event_start: string
  planned_event_end?: string | null
  main_activity_id?: string | null
}

export interface CreateGrossanlassDepartmentRequest {
  name: string
  organisation_id: string
  parent_id?: string | null
  planned_event_start: string
  planned_event_end?: string | null
  chief_mw_user_id?: string | null
}

export interface GrossanlassDepartment extends Department {
  is_grossanlass?: boolean
  grossanlass_config?: GrossanlassConfig
}

export interface UpdateDepartmentRequest {
  name?: string
  organisation_id?: string
  parent_id?: string | null
}

/**
 * Erstellt ein neues Department
 */
export async function createDepartment(data: CreateDepartmentRequest): Promise<Department> {
  const response = await apiClient.post<Department>('/api/departments', data)
  return response.data
}

/**
 * Erstellt ein Grossanlass-Department (Phase 1)
 */
export async function createGrossanlassDepartment(
  data: CreateGrossanlassDepartmentRequest
): Promise<GrossanlassDepartment> {
  const response = await apiClient.post<GrossanlassDepartment>('/api/departments/grossanlass', data)
  return response.data
}

/**
 * Globale User-Suche für Grossanlass-Wizard (Chief-MW), org-übergreifend.
 */
export async function getGrossanlassAvailableUsers(
  query: string,
  organisationId?: string | null,
): Promise<AvailableUser[]> {
  const response = await apiClient.get<AvailableUser[]>('/api/departments/grossanlass/available-users', {
    params: {
      q: query,
      ...(organisationId ? { organisation_id: organisationId } : {}),
    },
  })
  return response.data
}

/**
 * Aktualisiert ein Department
 */
export async function updateDepartment(id: string, data: UpdateDepartmentRequest): Promise<Department> {
  const response = await apiClient.patch<Department>(`/api/departments/${id}`, data)
  return response.data
}

// ========================================
// Department-Mitglieder Verwaltung
// ========================================

export interface DepartmentMember {
  user_id: string
  profile_id: string
  name: string
  first_name: string | null
  last_name: string | null
  nickname: string | null
  email: string
  avatar_initials?: string | null
  background_color?: string | null
  text_color?: string | null
  language?: string | null
  pending_email?: string | null
  role: string
  is_primary: boolean
  is_js_coach?: boolean
  state: string
}

export interface AvailableUser {
  id: string
  name: string
  email: string
  first_name: string | null
  last_name: string | null
  nickname: string | null
  primary_department_name?: string | null
  departments_label?: string | null
}

/**
 * Alle Mitglieder eines Departments laden
 */
export async function getDepartmentMembers(departmentId: string): Promise<DepartmentMember[]> {
  const response = await apiClient.get<DepartmentMember[]>(`/api/departments/${departmentId}/members`)
  return response.data
}

/**
 * Mitglied zu Department hinzufügen
 */
export interface AddDepartmentMemberResult extends DepartmentMember {
  notification_email_sent?: boolean
}

export async function addDepartmentMember(departmentId: string, data: {
  user_id: string
  role?: string
  is_primary?: boolean
  is_js_coach?: boolean
}): Promise<AddDepartmentMemberResult> {
  const response = await apiClient.post<AddDepartmentMemberResult>(`/api/departments/${departmentId}/members`, data)
  return response.data
}

/**
 * Mitglied-Rolle im Department ändern
 */
export async function updateDepartmentMember(departmentId: string, userId: string, data: {
  role?: string
  is_primary?: boolean
  is_js_coach?: boolean
}): Promise<DepartmentMember> {
  const response = await apiClient.patch<DepartmentMember>(`/api/departments/${departmentId}/members/${userId}`, data)
  return response.data
}

export interface UpdateDepartmentMemberProfilePayload {
  first_name?: string | null
  last_name?: string | null
  nickname?: string | null
  email?: string
  avatar_initials?: string | null
  language?: string
}

export interface DepartmentMemberProfileResult extends DepartmentMember {
  pending_email?: string | null
}

/**
 * Profil eines Department-Mitglieds bearbeiten (Hierarchie)
 */
export async function updateDepartmentMemberProfile(
  departmentId: string,
  userId: string,
  data: UpdateDepartmentMemberProfilePayload,
): Promise<DepartmentMemberProfileResult> {
  const response = await apiClient.patch<DepartmentMemberProfileResult>(
    `/api/departments/${departmentId}/members/${userId}/profile`,
    data,
  )
  return response.data
}

/**
 * Passwort-Reset an Mitglied senden
 */
export async function sendDepartmentMemberPasswordReset(
  departmentId: string,
  userId: string,
): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post<{ success: boolean; message: string }>(
    `/api/departments/${departmentId}/members/${userId}/send-password-reset`,
  )
  return response.data
}

/**
 * Mitglied aus Department entfernen
 */
export async function removeDepartmentMember(departmentId: string, userId: string): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/members/${userId}`)
}

/**
 * Verfügbare User laden (die noch NICHT im Department sind)
 */
export async function getAvailableUsersForDepartment(departmentId: string, query?: string): Promise<AvailableUser[]> {
  const response = await apiClient.get<AvailableUser[]>(`/api/departments/${departmentId}/available-users`, {
    params: query ? { q: query } : undefined
  })
  return response.data
}