import apiClient from './apiClient'

export type CalendarPeriodLabel =
  | 'school_vacation'
  | 'department_break'
  | 'camp_week'
  | 'other'
  | 'grossanlass'
  | 'aufbau'
  | 'abbau'

/** Event Durchführung, Aufbau, Abbau — Schnellauswahl + Wichtige Zeiträume. */
export const GROSSANLASS_TIME_MODULE_LABELS: CalendarPeriodLabel[] = [
  'grossanlass',
  'aufbau',
  'abbau',
]

export interface DepartmentCalendarPeriod {
  id: string
  department_id: string
  label: CalendarPeriodLabel
  name: string
  start_date: string
  end_date: string
  start_time?: string
  end_time?: string
  created_by_user_id: string | null
  created_at: string
  updated_at: string
}

export interface CalendarPeriodPayload {
  label: CalendarPeriodLabel
  name: string
  start_date: string
  end_date: string
  start_time: string
  end_time: string
}

export async function listDepartmentCalendarPeriods(
  departmentId: string,
  years?: number[],
): Promise<DepartmentCalendarPeriod[]> {
  const params: Record<string, string> = {}
  if (years?.length) {
    params.years = years.join(',')
  }
  const { data } = await apiClient.get<DepartmentCalendarPeriod[]>(
    `/api/departments/${encodeURIComponent(departmentId)}/calendar-periods`,
    { params },
  )
  return data
}

export async function createDepartmentCalendarPeriod(
  departmentId: string,
  payload: CalendarPeriodPayload,
): Promise<DepartmentCalendarPeriod> {
  const { data } = await apiClient.post<DepartmentCalendarPeriod>(
    `/api/departments/${encodeURIComponent(departmentId)}/calendar-periods`,
    payload,
  )
  return data
}

export async function updateDepartmentCalendarPeriod(
  departmentId: string,
  periodId: string,
  payload: Partial<CalendarPeriodPayload>,
): Promise<DepartmentCalendarPeriod> {
  const { data } = await apiClient.patch<DepartmentCalendarPeriod>(
    `/api/departments/${encodeURIComponent(departmentId)}/calendar-periods/${encodeURIComponent(periodId)}`,
    payload,
  )
  return data
}

export async function deleteDepartmentCalendarPeriod(
  departmentId: string,
  periodId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${encodeURIComponent(departmentId)}/calendar-periods/${encodeURIComponent(periodId)}`,
  )
}

export function calendarPeriodTime(value: string | null | undefined, fallback: string): string {
  const raw = (value || '').trim()
  if (/^\d{2}:\d{2}/.test(raw)) return raw.slice(0, 5)
  return fallback
}

export function calendarPeriodSortStamp(dateIso: string, timeIso: string | null | undefined, fallback: string): string {
  return `${dateIso}T${calendarPeriodTime(timeIso, fallback)}`
}
