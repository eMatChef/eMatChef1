import apiClient from './apiClient'

export type DepartmentClockMode = 'real' | 'demo' | 'dev'

/** Schnellwahl der Demo-Uhr: vom Server aus Anlasszeitraum und fixen Daten (Aufbau, Abbau) abgeleitet. */
export type DepartmentClockPresetKey = 'months_before_5' | 'months_before_2' | 'build' | 'during_event' | 'teardown' | 'months_after_2'

export interface DepartmentClockPreset {
  key: DepartmentClockPresetKey
  /** Naive Wandzeit `YYYY-MM-DDTHH:mm:ss`. */
  at: string
}

/** Fachzeit eines Departments (BusinessClock). Security-/Token-Zeit ist davon unabhängig. */
export interface DepartmentClock {
  mode: DepartmentClockMode
  /** Naive Wandzeit `YYYY-MM-DDTHH:mm:ss` (ohne Zeitzone, wie alle Grossanlass-Zeiten). */
  now: string
  real_now: string
  offset_seconds: number
  can_travel: boolean
  /** Leer für Departments ohne Grossanlass-Konfiguration. */
  presets?: DepartmentClockPreset[]
}

const base = (departmentId: string) => `/api/departments/${departmentId}/clock`

export async function getDepartmentClock(departmentId: string): Promise<DepartmentClock> {
  const response = await apiClient.get<DepartmentClock>(base(departmentId))
  return response.data
}

/** Setzt die simulierte Zeit (naive Wandzeit); der Server berechnet den Offset. */
export async function setDepartmentClock(departmentId: string, now: string): Promise<DepartmentClock> {
  const response = await apiClient.put<DepartmentClock>(base(departmentId), { now })
  return response.data
}

/** Zurück zum Demo-Ausgangspunkt (Demo) bzw. zur realen Zeit (Dev). */
export async function resetDepartmentClock(departmentId: string): Promise<DepartmentClock> {
  const response = await apiClient.delete<DepartmentClock>(base(departmentId))
  return response.data
}
