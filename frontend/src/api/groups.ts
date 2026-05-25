import apiClient from './apiClient'

// === Types ===

export interface GroupMember {
  user_id: string
  name: string
  first_name?: string | null
  last_name?: string | null
  nickname?: string | null
  email: string
  avatar_initials?: string | null
  background_color?: string | null
  text_color?: string | null
  role: string        // 'leader' | 'member' (+ Dept-Rolle in role_label)
  role_label: string
  is_leader: boolean
  is_primary: boolean
}

export interface Group {
  id: string
  name: string
  department_id: string
  parent_id: string | null
  sort_order: number
  member_count: number
  leader_count: number
  members: GroupMember[]
  leaders: GroupMember[]
  created_at: string
  updated_at: string
}

// === Rollen-Konfiguration ===

export const GROUP_ROLES = {
  leader: { label: 'Gruppenchef', short: 'GC', color: '#4f46e5', isLeader: true },
  member: { label: 'Mitglied', short: 'M', color: '#6b7280', isLeader: false },
} as const

export type GroupRoleKey = keyof typeof GROUP_ROLES

// === API Calls ===

/**
 * Alle Gruppen eines Departments laden (hierarchisch mit Mitgliedern)
 */
export async function getGroups(departmentId: string): Promise<Group[]> {
  const response = await apiClient.get<Group[]>('/api/groups', {
    params: { department_id: departmentId }
  })
  return response.data
}

/**
 * Einzelne Gruppe laden
 */
export async function getGroup(id: string): Promise<Group> {
  const response = await apiClient.get<Group>(`/api/groups/${id}`)
  return response.data
}

/**
 * Neue Gruppe erstellen
 */
export async function createGroup(data: {
  name: string
  department_id: string
  parent_id?: string | null
  sort_order?: number
}): Promise<Group> {
  const response = await apiClient.post<Group>('/api/groups', data)
  return response.data
}

/**
 * Gruppe aktualisieren
 */
export async function updateGroup(id: string, data: {
  name?: string
  parent_id?: string | null
  sort_order?: number
}): Promise<Group> {
  const response = await apiClient.patch<Group>(`/api/groups/${id}`, data)
  return response.data
}

/**
 * Gruppe löschen
 */
export async function deleteGroup(id: string): Promise<void> {
  await apiClient.delete(`/api/groups/${id}`)
}

/**
 * Mitglied zu Gruppe hinzufügen
 */
export async function addGroupMember(groupId: string, data: {
  user_id: string
  role?: string
  is_primary?: boolean
}): Promise<GroupMember> {
  const response = await apiClient.post<GroupMember>(`/api/groups/${groupId}/members`, data)
  return response.data
}

/**
 * Mitglied-Rolle aktualisieren
 */
export async function updateGroupMember(groupId: string, userId: string, data: {
  role?: string
  is_primary?: boolean
}): Promise<GroupMember> {
  const response = await apiClient.patch<GroupMember>(`/api/groups/${groupId}/members/${userId}`, data)
  return response.data
}

/**
 * Mitglied aus Gruppe entfernen
 */
export async function removeGroupMember(groupId: string, userId: string): Promise<void> {
  await apiClient.delete(`/api/groups/${groupId}/members/${userId}`)
}

