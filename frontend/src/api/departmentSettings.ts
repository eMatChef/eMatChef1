import apiClient from './apiClient'

export interface GeneralSettings {
  timezone: string
  dateFormat: string
  timeFormat: string
}

export interface ActivityDefaults {
  defaultTimeStart: string
  defaultTimeEnd: string
  materialLeadMinutes: number
  materialLagMinutes: number
  campMaterialLeadDays: number
  campMaterialLagDays: number
}

export interface DepartmentOnboardingStatus {
  doneAll: boolean
}

/**
 * Alle Settings eines Departments laden
 */
export async function getDepartmentSettings(departmentId: string): Promise<Record<string, string>> {
  const { data } = await apiClient.get(`/api/departments/${departmentId}/settings`)
  return data
}

/**
 * Settings nach Prefix laden (z.B. "activity")
 */
export async function getDepartmentSettingsGroup(departmentId: string, prefix: string): Promise<Record<string, string>> {
  const { data } = await apiClient.get(`/api/departments/${departmentId}/settings/group/${prefix}`)
  return data
}

/**
 * Settings speichern (Batch-Update)
 */
export async function updateDepartmentSettings(departmentId: string, settings: Record<string, string>): Promise<Record<string, string>> {
  const { data } = await apiClient.put(`/api/departments/${departmentId}/settings`, settings)
  return data
}

/**
 * Allgemeine Settings als typisiertes Objekt laden
 */
export async function getGeneralSettings(departmentId: string): Promise<GeneralSettings> {
  const raw = await getDepartmentSettingsGroup(departmentId, 'general')
  return {
    timezone: raw['general.timezone'] || 'Europe/Zurich',
    dateFormat: raw['general.date_format'] || 'dd.MM.yyyy',
    timeFormat: raw['general.time_format'] || 'HH:mm',
  }
}

/**
 * Allgemeine Settings speichern
 */
export async function saveGeneralSettings(departmentId: string, settings: GeneralSettings): Promise<Record<string, string>> {
  return updateDepartmentSettings(departmentId, {
    'general.timezone': settings.timezone,
    'general.date_format': settings.dateFormat,
    'general.time_format': settings.timeFormat,
  })
}

/**
 * Aktivitäts-Settings als typisiertes Objekt laden
 */
export async function getActivityDefaults(departmentId: string): Promise<ActivityDefaults> {
  const raw = await getDepartmentSettingsGroup(departmentId, 'activity')
  return {
    defaultTimeStart: raw['activity.default_time_start'] || '14:00',
    defaultTimeEnd: raw['activity.default_time_end'] || '17:00',
    materialLeadMinutes: parseInt(raw['activity.material_lead_minutes'] || '60', 10),
    materialLagMinutes: parseInt(raw['activity.material_lag_minutes'] || '60', 10),
    campMaterialLeadDays: parseInt(raw['activity.camp_material_lead_days'] || '1', 10),
    campMaterialLagDays: parseInt(raw['activity.camp_material_lag_days'] || '1', 10),
  }
}

/**
 * Aktivitäts-Settings speichern
 */
export async function saveActivityDefaults(departmentId: string, defaults: ActivityDefaults): Promise<Record<string, string>> {
  return updateDepartmentSettings(departmentId, {
    'activity.default_time_start': defaults.defaultTimeStart,
    'activity.default_time_end': defaults.defaultTimeEnd,
    'activity.material_lead_minutes': String(defaults.materialLeadMinutes),
    'activity.material_lag_minutes': String(defaults.materialLagMinutes),
    'activity.camp_material_lead_days': String(defaults.campMaterialLeadDays),
    'activity.camp_material_lag_days': String(defaults.campMaterialLagDays),
  })
}

/**
 * Onboarding-Status laden
 */
export async function getDepartmentOnboardingStatus(departmentId: string): Promise<DepartmentOnboardingStatus> {
  const raw = await getDepartmentSettings(departmentId)
  return {
    doneAll: String(raw['onboarding.done_all'] || '0') === '1',
  }
}

/**
 * Onboarding als abgeschlossen markieren (department-weit)
 */
export async function markDepartmentOnboardingDone(departmentId: string): Promise<Record<string, string>> {
  return updateDepartmentSettings(departmentId, {
    'onboarding.done_all': '1',
  })
}

/**
 * Onboarding für ein Department zurücksetzen
 */
export async function resetDepartmentOnboardingDone(departmentId: string): Promise<Record<string, string>> {
  return updateDepartmentSettings(departmentId, {
    'onboarding.done_all': '0',
    'onboarding.phase1_done': '0',
    'onboarding.phase1_settings_done': '0',
  })
}

/**
 * DB zuruecksetzen – loescht alle Daten des Departments (Aktivitaeten, Materialien, Adressen, etc.)
 */
export async function resetDepartmentDb(departmentId: string): Promise<{ success: boolean; message: string; deleted: Record<string, number> }> {
  const { data } = await apiClient.post(`/api/departments/${departmentId}/reset-db`)
  return data
}
