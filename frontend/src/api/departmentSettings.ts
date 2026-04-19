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

/** Fallback, wenn die API-Gruppe „activity“ nicht geladen werden kann */
export const FALLBACK_ACTIVITY_DEFAULTS: ActivityDefaults = {
  defaultTimeStart: '14:00',
  defaultTimeEnd: '17:00',
  materialLeadMinutes: 60,
  materialLagMinutes: 60,
  campMaterialLeadDays: 1,
  campMaterialLagDays: 1,
}

/** Abteilungs-Standards für den Vermiet-Amortisationsrechner */
export interface RentalAmortizationDefaults {
  priceIncreasePercentPerYear: number
  yearsToReplacement: number
  internalDaysPerYear: number
  externalDaysPerYear: number
  markupPercent: number
}

/** Typische Planung: ~14 Sommerlager + 4 Pfingsten + 7 Herbst + ~5 Aktivitäten ≈ 30 interne Miettage/Jahr */
export const DEFAULT_RENTAL_AMORTIZATION: RentalAmortizationDefaults = {
  priceIncreasePercentPerYear: 0.2,
  yearsToReplacement: 5,
  internalDaysPerYear: 30,
  externalDaysPerYear: 0,
  markupPercent: 0,
}

export interface DepartmentOnboardingStatus {
  doneAll: boolean
}

/** Hinweise vom QR-Kontaktformular: Versand/Empfang */
export type PublicFoundContactDelivery = 'email' | 'in_app' | 'both'

export interface PublicSharingSettings {
  publicContactEmail: string
  publicContactNote: string
  /** Sichtbarkeit öffentliche QR-Seite (Standard: true) */
  publicShowContactForm: boolean
  publicShowContactEmail: boolean
  publicShowContactNote: boolean
  /** Standard: E-Mail und Nachrichtenzentrale */
  publicFoundContactDelivery: PublicFoundContactDelivery
}

function parseBoolSetting01(raw: string | undefined, defaultTrue: boolean): boolean {
  if (raw === undefined || raw === '') return defaultTrue
  const v = String(raw).toLowerCase().trim()
  if (['0', 'false', 'no', 'off', 'nein'].includes(v)) return false
  if (['1', 'true', 'yes', 'on', 'ja'].includes(v)) return true
  return defaultTrue
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
 * Public-Sharing-Settings laden
 */
function parsePublicFoundDelivery(raw: string | undefined): PublicFoundContactDelivery {
  const v = String(raw || 'both').toLowerCase().trim()
  if (v === 'in_app' || v === 'both' || v === 'email') return v
  return 'both'
}

export async function getPublicSharingSettings(departmentId: string): Promise<PublicSharingSettings> {
  const raw = await getDepartmentSettingsGroup(departmentId, 'general')
  return {
    publicContactEmail: String(raw['general.public_contact_email'] || ''),
    publicContactNote: String(raw['general.public_contact_note'] || ''),
    publicShowContactForm: parseBoolSetting01(raw['general.public_show_contact_form'], true),
    publicShowContactEmail: parseBoolSetting01(raw['general.public_show_contact_email'], true),
    publicShowContactNote: parseBoolSetting01(raw['general.public_show_contact_note'], true),
    publicFoundContactDelivery: parsePublicFoundDelivery(raw['general.public_found_contact_delivery']),
  }
}

/**
 * Public-Sharing-Settings speichern
 */
export async function savePublicSharingSettings(
  departmentId: string,
  settings: PublicSharingSettings
): Promise<Record<string, string>> {
  return updateDepartmentSettings(departmentId, {
    'general.public_contact_email': settings.publicContactEmail,
    'general.public_contact_note': settings.publicContactNote,
    'general.public_show_contact_form': settings.publicShowContactForm ? '1' : '0',
    'general.public_show_contact_email': settings.publicShowContactEmail ? '1' : '0',
    'general.public_show_contact_note': settings.publicShowContactNote ? '1' : '0',
    'general.public_found_contact_delivery': settings.publicFoundContactDelivery,
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

export async function getRentalAmortizationDefaults(departmentId: string): Promise<RentalAmortizationDefaults> {
  const raw = await getDepartmentSettingsGroup(departmentId, 'rental')
  return {
    priceIncreasePercentPerYear: parseFloat(raw['rental.amortization_price_increase_percent_per_year'] || '0.2'),
    yearsToReplacement: parseInt(raw['rental.amortization_years_to_replacement'] || '5', 10),
    internalDaysPerYear: parseInt(raw['rental.amortization_internal_days_per_year'] || '30', 10),
    externalDaysPerYear: parseInt(raw['rental.amortization_external_days_per_year'] || '0', 10),
    markupPercent: parseFloat(raw['rental.amortization_markup_percent'] || '0'),
  }
}

export async function saveRentalAmortizationDefaults(
  departmentId: string,
  defaults: RentalAmortizationDefaults
): Promise<Record<string, string>> {
  return updateDepartmentSettings(departmentId, {
    'rental.amortization_price_increase_percent_per_year': String(defaults.priceIncreasePercentPerYear),
    'rental.amortization_years_to_replacement': String(defaults.yearsToReplacement),
    'rental.amortization_internal_days_per_year': String(defaults.internalDaysPerYear),
    'rental.amortization_external_days_per_year': String(defaults.externalDaysPerYear),
    'rental.amortization_markup_percent': String(defaults.markupPercent),
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
export interface CalendarSettings {
  /** Geo-ID von feiertagskalender.ch (Schulferien im Aktivitäts-Kalender) */
  fcalGeoId: string
}

export async function getCalendarSettings(departmentId: string): Promise<CalendarSettings> {
  const raw = await getDepartmentSettingsGroup(departmentId, 'calendar')
  return {
    fcalGeoId: String(raw['calendar.fcal_geo_id'] ?? '').trim(),
  }
}

export async function saveCalendarSettings(departmentId: string, settings: CalendarSettings): Promise<Record<string, string>> {
  return updateDepartmentSettings(departmentId, {
    'calendar.fcal_geo_id': settings.fcalGeoId.trim(),
  })
}

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
