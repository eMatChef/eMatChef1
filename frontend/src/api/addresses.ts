import apiClient from './apiClient'

export interface Address {
  id: string
  department_id: string
  type: string
  type_label: string
  name: string | null
  company: string | null
  address_line2: string | null
  street: string
  street_number: string | null
  street_line: string
  postal_code: string
  city: string
  city_line: string
  canton: string | null
  country: string
  latitude: number | null
  longitude: number | null
  has_coordinates: boolean
  email: string | null
  phone: string | null
  mobile: string | null
  additional_info: string | null
  is_primary: boolean
  full_address: string
  deleted_at?: string | null
  is_deleted?: boolean
}

export interface AddressFormData {
  department_id: string
  type?: string
  name?: string | null
  company?: string | null
  address_line2?: string | null
  street?: string | null
  street_number?: string | null
  postal_code?: string | null
  city?: string | null
  canton?: string | null
  country?: string
  latitude?: number | null
  longitude?: number | null
  email?: string | null
  phone?: string | null
  mobile?: string | null
  additional_info?: string | null
  is_primary?: boolean
}

export interface AddressTypes {
  [key: string]: string
}

export interface SwissCantons {
  [key: string]: string
}

/**
 * Alle Adressen eines Departments laden (optional gefiltert nach Typ)
 * WICHTIG: department_id ist erforderlich für Multi-Tenant!
 */
export async function getAddresses(
  departmentId: string,
  typeOrOptions?: string | { type?: string; includeDeleted?: boolean }
): Promise<{
  addresses: Address[]
  types: AddressTypes
  cantons: SwissCantons
}> {
  const params: Record<string, string> = { department_id: departmentId }
  if (typeof typeOrOptions === 'string') {
    params.type = typeOrOptions
  } else {
    if (typeOrOptions?.type) params.type = typeOrOptions.type
    if (typeOrOptions?.includeDeleted) params.include_deleted = '1'
  }
  const { data } = await apiClient.get('/api/addresses', { params })
  return data
}

/**
 * Einzelne Adresse laden
 */
export async function getAddress(id: string): Promise<{
  address: Address
  types: AddressTypes
  cantons: SwissCantons
}> {
  const { data } = await apiClient.get(`/api/addresses/${id}`)
  return data
}

/**
 * Neue Adresse erstellen
 */
export async function createAddress(formData: AddressFormData): Promise<{
  address: Address
  message: string
}> {
  const { data } = await apiClient.post('/api/addresses', formData)
  return data
}

/**
 * Adresse aktualisieren
 */
export async function updateAddress(id: string, formData: Partial<AddressFormData>): Promise<{
  address: Address
  message: string
}> {
  const { data } = await apiClient.put(`/api/addresses/${id}`, formData)
  return data
}

/** Adresse in den Papierkorb verschieben (Soft-Delete). */
export async function deleteAddress(id: string): Promise<{ message: string; address?: Address }> {
  const { data } = await apiClient.delete(`/api/addresses/${id}`)
  return data
}

/** Gelöschte Adresse wiederherstellen (nur MW/DC). */
export async function restoreAddress(id: string): Promise<{ message: string; address: Address }> {
  const { data } = await apiClient.post(`/api/addresses/${id}/restore`)
  return data
}

/** Adresse endgültig löschen (nur MW/DC, nur aus dem Papierkorb). */
export async function permanentDeleteAddress(id: string): Promise<{ message: string }> {
  const { data } = await apiClient.delete(`/api/addresses/${id}/permanent`)
  return data
}

/**
 * Adresse als primär setzen (für diesen Typ im Department)
 */
export async function setAddressPrimary(id: string): Promise<{
  address: Address
  message: string
}> {
  const { data } = await apiClient.post(`/api/addresses/${id}/set-primary`)
  return data
}

/**
 * Verfügbare Optionen (Typen, Kantone) laden
 */
export async function getAddressOptions(): Promise<{
  types: AddressTypes
  cantons: SwissCantons
}> {
  const { data } = await apiClient.get('/api/addresses/meta/options')
  return data
}

/**
 * Schweizer Kantone (statisch für schnellen Zugriff)
 */
export const SWISS_CANTONS: SwissCantons = {
  'AG': 'Aargau',
  'AI': 'Appenzell Innerrhoden',
  'AR': 'Appenzell Ausserrhoden',
  'BE': 'Bern',
  'BL': 'Basel-Landschaft',
  'BS': 'Basel-Stadt',
  'FR': 'Freiburg',
  'GE': 'Genf',
  'GL': 'Glarus',
  'GR': 'Graubünden',
  'JU': 'Jura',
  'LU': 'Luzern',
  'NE': 'Neuenburg',
  'NW': 'Nidwalden',
  'OW': 'Obwalden',
  'SG': 'St. Gallen',
  'SH': 'Schaffhausen',
  'SO': 'Solothurn',
  'SZ': 'Schwyz',
  'TG': 'Thurgau',
  'TI': 'Tessin',
  'UR': 'Uri',
  'VD': 'Waadt',
  'VS': 'Wallis',
  'ZG': 'Zug',
  'ZH': 'Zürich',
}

/**
 * Adress-Typen (statisch für schnellen Zugriff)
 */
export const ADDRESS_TYPES: AddressTypes = {
  'general': 'Allgemein',
  'billing': 'Rechnungsadresse',
  'delivery': 'Lieferadresse',
  'supplier': 'Hersteller/Lieferant',
  'storage': 'Lagerstandort',
  'customer': 'Kundenadresse',
  'event': 'Eventstandort',
  'meeting': 'Treffpunkt',
  'office': 'Büro',
  'private': 'Privat',
  'postal': 'Postadresse',
}
