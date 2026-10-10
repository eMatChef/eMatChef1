import apiClient from './apiClient'

export type AdditionalEmail = {
  id: string
  email: string
  verified: boolean
  verified_at: string | null
  login_enabled: boolean
  /** Nur bei unbestätigten Adressen: Ablauf des Bestätigungslinks */
  verification_expires_at: string | null
}

export type ProfileEmails = {
  primary: { email: string | null; verified: boolean }
  emails: AdditionalEmail[]
}

const base = (profileId: string) => `/api/profiles/${profileId}/emails`

export async function getProfileEmails(profileId: string): Promise<ProfileEmails> {
  const { data } = await apiClient.get<ProfileEmails>(base(profileId))
  return data
}

export async function addProfileEmail(profileId: string, email: string): Promise<ProfileEmails> {
  const { data } = await apiClient.post<ProfileEmails>(base(profileId), { email })
  return data
}

export async function resendProfileEmailVerification(profileId: string, aliasId: string): Promise<ProfileEmails> {
  const { data } = await apiClient.post<ProfileEmails>(`${base(profileId)}/${aliasId}/resend`)
  return data
}

export async function makeProfileEmailPrimary(profileId: string, aliasId: string): Promise<ProfileEmails> {
  const { data } = await apiClient.put<ProfileEmails>(`${base(profileId)}/primary`, { alias_id: aliasId })
  return data
}

export async function removeProfileEmail(profileId: string, aliasId: string): Promise<ProfileEmails> {
  const { data } = await apiClient.delete<ProfileEmails>(`${base(profileId)}/${aliasId}`)
  return data
}

export type DepartmentEmailAssignment = {
  department_id: string
  name: string
  parent_name: string | null
  is_grossanlass: boolean
  /** Adresse, an die Benachrichtigungen dieser Mitgliedschaft tatsächlich gehen */
  effective_email: string
  /** null = Standard (Hauptadresse) */
  selected_email: string | null
}

export type DepartmentEmailAssignments = {
  assignments: DepartmentEmailAssignment[]
  /** Hauptadresse und eigene verifizierte Adressen */
  options: string[]
}

/** Nur eigene Mitgliedschaften; ändern über setDepartmentNotificationEmail (api/departmentNotificationEmail). */
export async function getDepartmentEmailAssignments(profileId: string): Promise<DepartmentEmailAssignments> {
  const { data } = await apiClient.get<DepartmentEmailAssignments>(`${base(profileId)}/department-assignments`)
  return data
}
