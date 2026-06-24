import apiClient from './apiClient'

export interface DepartmentVehicle {
  id: string
  department_id: string
  name: string
  plate: string | null
  length_m: number | null
  width_m: number | null
  height_m: number | null
  max_payload_kg: number | null
  max_volume_m3: number | null
  is_active: boolean
  notes: string | null
  owner_address_id?: string | null
  owner_label?: string | null
}

function mapVehicle(raw: Record<string, unknown>): DepartmentVehicle {
  const num = (v: unknown): number | null => {
    if (v == null || v === '') return null
    const n = typeof v === 'number' ? v : parseFloat(String(v))
    return Number.isFinite(n) ? n : null
  }
  return {
    id: String(raw.id ?? ''),
    department_id: String(raw.department_id ?? ''),
    name: String(raw.name ?? ''),
    plate: raw.plate != null ? String(raw.plate) : null,
    length_m: num(raw.length_m),
    width_m: num(raw.width_m),
    height_m: num(raw.height_m),
    max_payload_kg: num(raw.max_payload_kg),
    max_volume_m3: num(raw.max_volume_m3),
    is_active: raw.is_active !== false,
    notes: raw.notes != null ? String(raw.notes) : null,
    owner_address_id: raw.owner_address_id != null ? String(raw.owner_address_id) : null,
    owner_label: raw.owner_label != null ? String(raw.owner_label) : null,
  }
}

export async function getDepartmentVehicles(
  departmentId: string,
  options?: { activityId?: string; search?: string },
): Promise<DepartmentVehicle[]> {
  const params = new URLSearchParams()
  if (options?.activityId) params.set('activity_id', options.activityId)
  if (options?.search) params.set('search', options.search)
  const qs = params.toString()
  const { data } = await apiClient.get<Record<string, unknown>[]>(
    `/api/departments/${departmentId}/vehicles${qs ? `?${qs}` : ''}`,
  )
  return (Array.isArray(data) ? data : []).map((row) => mapVehicle(row))
}

export async function getRecentDepartmentVehicles(
  departmentId: string,
  options?: { activityId?: string; limit?: number },
): Promise<DepartmentVehicle[]> {
  const params = new URLSearchParams()
  if (options?.activityId) params.set('activity_id', options.activityId)
  if (options?.limit != null) params.set('limit', String(options.limit))
  const qs = params.toString()
  const { data } = await apiClient.get<Record<string, unknown>[]>(
    `/api/departments/${departmentId}/vehicles/recent${qs ? `?${qs}` : ''}`,
  )
  return (Array.isArray(data) ? data : []).map((row) => mapVehicle(row))
}

export async function createDepartmentVehicle(
  departmentId: string,
  body: {
    name: string
    plate?: string
    max_payload_kg?: number
    notes?: string
    owner_address_id?: string
  },
): Promise<DepartmentVehicle> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/departments/${departmentId}/vehicles`,
    body,
  )
  return mapVehicle(data ?? {})
}
