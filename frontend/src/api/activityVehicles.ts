import apiClient from './apiClient'
import type { DepartmentVehicle } from '@/api/departmentVehicles'

export interface ActivityVehicleOwnerContact {
  company: string | null
  contact_full_name: string | null
  phone: string | null
  email: string | null
}

export interface ActivityVehicleAssignment {
  id: string
  activity_id: string
  vehicle_id: string
  sort_order: number
  notes: string | null
  vehicle: DepartmentVehicle & {
    owner_label?: string | null
    owner_contact?: ActivityVehicleOwnerContact | null
  }
}

function mapAssignment(raw: Record<string, unknown>): ActivityVehicleAssignment {
  const vehicleRaw = (raw.vehicle ?? {}) as Record<string, unknown>
  const num = (v: unknown): number | null => {
    if (v == null || v === '') return null
    const n = typeof v === 'number' ? v : parseFloat(String(v))
    return Number.isFinite(n) ? n : null
  }
  const ownerContactRaw = vehicleRaw.owner_contact as Record<string, unknown> | null | undefined
  return {
    id: String(raw.id ?? ''),
    activity_id: String(raw.activity_id ?? ''),
    vehicle_id: String(raw.vehicle_id ?? ''),
    sort_order: Number(raw.sort_order ?? 0),
    notes: raw.notes != null ? String(raw.notes) : null,
    vehicle: {
      id: String(vehicleRaw.id ?? ''),
      department_id: String(vehicleRaw.department_id ?? ''),
      name: String(vehicleRaw.name ?? ''),
      plate: vehicleRaw.plate != null ? String(vehicleRaw.plate) : null,
      length_m: num(vehicleRaw.length_m),
      width_m: num(vehicleRaw.width_m),
      height_m: num(vehicleRaw.height_m),
      max_payload_kg: num(vehicleRaw.max_payload_kg),
      max_volume_m3: num(vehicleRaw.max_volume_m3),
      is_active: vehicleRaw.is_active !== false,
      notes: vehicleRaw.notes != null ? String(vehicleRaw.notes) : null,
      owner_address_id: vehicleRaw.owner_address_id != null ? String(vehicleRaw.owner_address_id) : null,
      owner_label: vehicleRaw.owner_label != null ? String(vehicleRaw.owner_label) : null,
      owner_contact: ownerContactRaw
        ? {
            company: ownerContactRaw.company != null ? String(ownerContactRaw.company) : null,
            contact_full_name:
              ownerContactRaw.contact_full_name != null
                ? String(ownerContactRaw.contact_full_name)
                : null,
            phone: ownerContactRaw.phone != null ? String(ownerContactRaw.phone) : null,
            email: ownerContactRaw.email != null ? String(ownerContactRaw.email) : null,
          }
        : null,
    },
  }
}

export async function getActivityVehicles(activityId: string): Promise<ActivityVehicleAssignment[]> {
  const { data } = await apiClient.get<Record<string, unknown>[]>(
    `/api/activities/${activityId}/vehicles`,
  )
  return (Array.isArray(data) ? data : []).map((row) => mapAssignment(row))
}

export async function assignActivityVehicle(
  activityId: string,
  body: { vehicle_id: string; notes?: string },
): Promise<ActivityVehicleAssignment> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/vehicles`,
    body,
  )
  return mapAssignment(data ?? {})
}

export async function createAndAssignActivityVehicle(
  activityId: string,
  body: {
    vehicle: {
      name: string
      plate?: string
      max_payload_kg?: number
      max_volume_m3?: number
      notes?: string
      owner_address_id?: string
    }
    notes?: string
  },
): Promise<ActivityVehicleAssignment> {
  const { data } = await apiClient.post<Record<string, unknown>>(
    `/api/activities/${activityId}/vehicles`,
    body,
  )
  return mapAssignment(data ?? {})
}

export async function updateActivityVehicle(
  activityId: string,
  assignmentId: string,
  body: {
    notes?: string
    vehicle?: {
      name?: string
      plate?: string | null
      max_payload_kg?: number | null
      max_volume_m3?: number | null
      notes?: string | null
      owner_address_id?: string | null
    }
  },
): Promise<ActivityVehicleAssignment> {
  const { data } = await apiClient.patch<Record<string, unknown>>(
    `/api/activities/${activityId}/vehicles/${assignmentId}`,
    body,
  )
  return mapAssignment(data ?? {})
}

export async function removeActivityVehicle(
  activityId: string,
  assignmentId: string,
): Promise<void> {
  await apiClient.delete(`/api/activities/${activityId}/vehicles/${assignmentId}`)
}
