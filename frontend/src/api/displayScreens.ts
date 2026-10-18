import apiClient from './apiClient'
import type { DepartmentDisplayData } from './display'
import type { DisplayClockPayload, DisplayLayoutPayload, DisplayMapData, GaLiveData } from './displayLayout'

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
  /** Layout-Status (Entwurf/Veröffentlichung); nur in Kontexten mit Layouts relevant. */
  layout?: { has_draft: boolean; draft_version: number; published_number: number | null }
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
  /** department | grossanlass: bestimmt künftig die verfügbaren Inhaltsmodule. */
  scope?: 'department' | 'grossanlass'
  screen_name?: string
  department_name?: string
  subtitle_text?: string | null
  show_activities?: boolean
  show_workshop?: boolean
  activity_types?: string[]
  activity_statuses?: string[]
  workshop_statuses?: string[]
  show_statistics?: boolean
  /** Layout des Infoscreens; null = Layouts sind im Kontext nicht aktiv (Checkbox-Konfiguration). */
  layout?: DisplayLayoutPayload | null
  /** Fachzeit des Departments (Demo: BusinessClock), siehe `utils/displayClock.ts`. */
  clock?: DisplayClockPayload
}

export interface DisplayScreenSettingsUpdate {
  name?: string
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

/** Infoscreen ohne Gerät anlegen, optional mit Vorlage (`blank` = leer) oder als Kopie eines anderen Infoscreens. */
export async function createDisplayScreen(
  departmentId: string,
  name: string,
  seed?: { template_id?: string; source_screen_id?: string },
): Promise<DisplayScreenSettings> {
  const res = await apiClient.post<DisplayScreenSettings>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens`,
    { name, ...seed },
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

export type PublicDisplayDataResponse = PublicDisplayData & {
  workshop_tickets?: PublicDisplayData['workshopTickets']
}

export function mapDisplayPayload(data: PublicDisplayDataResponse): PublicDisplayData {
  return {
    scope: data.scope,
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
    layout: data.layout ?? null,
    clock: data.clock,
  }
}

/** Vorschau für Verwalter: gleicher Payload wie der echte Infoscreen, über die normale User-Anmeldung. */
export async function getDisplayPreviewData(
  departmentId: string,
  screenId: string,
  options?: { source?: 'draft' | 'published'; variantId?: string },
): Promise<PublicDisplayData> {
  const res = await apiClient.get<PublicDisplayDataResponse>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}/preview-data`,
    { params: { source: options?.source, variant_id: options?.variantId } },
  )
  return mapDisplayPayload(res.data)
}

/** Neuen Infoscreen im Department anlegen und mit dem gescannten Fernseher koppeln (alles oder nichts). */
export async function createAndPairDisplayScreen(
  departmentId: string,
  token: string,
  name: string,
  deviceName: string,
): Promise<DisplayScreenSettings> {
  const res = await apiClient.post<DisplayScreenSettings>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/pairing`,
    { token, name, device_name: deviceName },
  )
  return res.data
}

/** Widerrufenen Infoscreen endgültig löschen. */
export async function deleteDisplayScreen(departmentId: string, screenId: string): Promise<void> {
  await apiClient.delete(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}`,
  )
}

/** Kartendaten für Editor und Vorschau (Verwalter): gleiche Daten wie auf dem Gerät. */
export async function getDisplayPreviewMapData(departmentId: string, screenId: string): Promise<DisplayMapData> {
  const res = await apiClient.get<DisplayMapData>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}/map-data`,
  )
  return res.data
}

/** Live-Daten für die Editor-Live-Vorschau und die Vorschau (Verwalter): dieselben Daten wie auf dem Gerät. */
export async function getDisplayPreviewLiveData(departmentId: string, screenId: string): Promise<GaLiveData> {
  const res = await apiClient.get<GaLiveData>(
    `/api/departments/${encodeURIComponent(departmentId)}/display-screens/${encodeURIComponent(screenId)}/live-data`,
  )
  return res.data
}
