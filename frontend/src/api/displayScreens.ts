import apiClient from './apiClient'
import type { DepartmentDisplayData } from './display'

export interface DisplayScreenSettings {
  id: string
  department_id: string
  name: string
  public_id: string
  display_url: string
  subtitle_text?: string | null
  show_activities?: boolean
  show_workshop?: boolean
  activity_types?: string[]
  activity_statuses?: string[]
  workshop_statuses?: string[]
  show_statistics?: boolean
  access_code_hint: string | null
  code_version: number
  revoked_at: string | null
  last_used_at: string | null
  created_at: string
  updated_at: string
  access_code?: string
}

export interface PublicDisplaySession {
  authenticated: boolean
  screen_name?: string
  public_id?: string
}

export type PublicDisplayData = DepartmentDisplayData & {
  screen_name?: string
  department_name?: string
  subtitle_text?: string | null
  show_activities?: boolean
  show_workshop?: boolean
  activity_types?: string[]
  activity_statuses?: string[]
  workshop_statuses?: string[]
  show_statistics?: boolean
}

export interface DisplayScreenSettingsUpdate {
  subtitle_text?: string | null
  show_activities?: boolean
  show_workshop?: boolean
  activity_types?: string[]
  activity_statuses?: string[]
  workshop_statuses?: string[]
  show_statistics?: boolean
}

export async function listDisplayScreens(departmentId: string): Promise<DisplayScreenSettings[]> {
  const res = await apiClient.get<DisplayScreenSettings[]>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens`,
  )
  return res.data || []
}

export async function createDisplayScreen(
  departmentId: string,
  name: string,
): Promise<DisplayScreenSettings> {
  const res = await apiClient.post<DisplayScreenSettings>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens`,
    { name },
  )
  return res.data
}

export async function rotateDisplayScreenCode(
  departmentId: string,
  screenId: string,
): Promise<DisplayScreenSettings> {
  const res = await apiClient.post<DisplayScreenSettings>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}/rotate-code`,
  )
  return res.data
}

export async function updateDisplayScreenSettings(
  departmentId: string,
  screenId: string,
  body: DisplayScreenSettingsUpdate,
): Promise<DisplayScreenSettings> {
  const res = await apiClient.patch<DisplayScreenSettings>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}`,
    body,
  )
  return res.data
}

export async function revokeDisplayScreen(departmentId: string, screenId: string): Promise<DisplayScreenSettings> {
  const res = await apiClient.post<DisplayScreenSettings>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}/revoke`,
  )
  return res.data
}

export async function reactivateDisplayScreen(
  departmentId: string,
  screenId: string,
): Promise<DisplayScreenSettings> {
  const res = await apiClient.post<DisplayScreenSettings>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}/reactivate`,
  )
  return res.data
}

/** Prüft ob Infoscreen-ID existiert (ohne Zugangscode). */
export async function lookupPublicDisplay(publicId: string): Promise<boolean> {
  try {
    const res = await apiClient.get<{ valid?: boolean }>(
      `/api/public/display/${encodeURIComponent(publicId)}/lookup`,
      { withCredentials: true },
    )
    return res.data?.valid === true
  } catch {
    return false
  }
}

export async function getPublicDisplaySession(publicId: string): Promise<PublicDisplaySession> {
  const res = await apiClient.get<PublicDisplaySession>(
    `/api/public/display/${encodeURIComponent(publicId)}/session`,
    { withCredentials: true },
  )
  return res.data
}

export async function authenticatePublicDisplay(
  publicId: string,
  accessCode: string,
): Promise<PublicDisplaySession> {
  const res = await apiClient.post<PublicDisplaySession>(
    `/api/public/display/${encodeURIComponent(publicId)}/authenticate`,
    { access_code: accessCode },
    { withCredentials: true },
  )
  return res.data
}

type PublicDisplayDataResponse = PublicDisplayData & {
  workshop_tickets?: PublicDisplayData['workshopTickets']
}

export async function getPublicDisplayData(publicId: string): Promise<PublicDisplayData> {
  const res = await apiClient.get<PublicDisplayDataResponse>(
    `/api/public/display/${encodeURIComponent(publicId)}/data`,
    { withCredentials: true },
  )
  const data = res.data
  return {
    activities: data.activities || [],
    workshopTickets: data.workshop_tickets || data.workshopTickets || [],
    department_name: data.department_name,
    screen_name: data.screen_name,
    subtitle_text: data.subtitle_text ?? null,
    show_activities: data.show_activities !== false,
    show_workshop: data.show_workshop !== false,
    activity_types: data.activity_types,
    activity_statuses: data.activity_statuses,
    workshop_statuses: data.workshop_statuses,
    show_statistics: data.show_statistics === true,
    statistics: data.statistics ?? null,
  }
}
