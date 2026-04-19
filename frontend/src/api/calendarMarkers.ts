import apiClient from './apiClient'

export type CalendarMarkerKind = 'school'

export interface CalendarMarkerDto {
  date: string
  label: string
  kind: CalendarMarkerKind
}

export interface DepartmentCalendarMarkersResponse {
  markers: CalendarMarkerDto[]
  location: string | null
  source: 'fcal' | 'none'
  message?: string
  geoId?: number
}

/**
 * Schulferien-Marker (fcal) für die Abteilung — nur wenn calendar.fcal_geo_id und FCAL_API_KEY gesetzt.
 */
export async function getDepartmentCalendarMarkers(
  departmentId: string,
  years?: number[],
): Promise<DepartmentCalendarMarkersResponse> {
  const params: Record<string, string> = {}
  if (years?.length) {
    params.years = years.join(',')
  }
  const { data } = await apiClient.get<DepartmentCalendarMarkersResponse>(
    `/api/departments/${departmentId}/calendar-markers`,
    { params },
  )
  return data
}
