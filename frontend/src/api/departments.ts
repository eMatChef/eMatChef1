import apiClient from './apiClient'

export interface DepartmentUser {
  id: string
  profile_id: string
  name: string
  email: string
  role: string
  is_primary: boolean
}

export interface Department {
  id: string
  name: string
  organisation_id: string
  parent_id?: string | null
  users: DepartmentUser[]
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
  role: string
  is_primary: boolean
  state: string
}

export interface AvailableUser {
  id: string
  name: string
  email: string
  first_name: string | null
  last_name: string | null
  nickname: string | null
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
export async function addDepartmentMember(departmentId: string, data: {
  user_id: string
  role?: string
  is_primary?: boolean
}): Promise<DepartmentMember> {
  const response = await apiClient.post<DepartmentMember>(`/api/departments/${departmentId}/members`, data)
  return response.data
}

/**
 * Mitglied-Rolle im Department ändern
 */
export async function updateDepartmentMember(departmentId: string, userId: string, data: {
  role?: string
  is_primary?: boolean
}): Promise<DepartmentMember> {
  const response = await apiClient.patch<DepartmentMember>(`/api/departments/${departmentId}/members/${userId}`, data)
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